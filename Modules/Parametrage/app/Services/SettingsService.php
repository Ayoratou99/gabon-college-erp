<?php

declare(strict_types=1);

namespace Modules\Parametrage\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Modules\Parametrage\Exceptions\InvalidSettingValueException;
use Modules\Parametrage\Exceptions\SettingNotFoundException;
use Modules\Parametrage\Models\Setting;
use Modules\Parametrage\Models\SettingChangeLog;

/**
 * High-traffic settings store with Redis caching.
 *
 *   - get(key)                 → typed value (or default)
 *   - set(key, value, user)    → validated update + audit row + cache bust
 *   - byCategory(category)     → ordered list for an admin UI section
 *   - publicMap()              → key→value map of `is_public=true` settings
 *                                 (cacheable for use in the public homepage)
 *
 * The "raw map" is loaded once per cache-window and held in memory for
 * subsequent calls in the same request. Mutations clear both layers.
 */
final class SettingsService
{
    /** @var array<string, mixed>|null In-process memo, repopulated lazily. */
    private ?array $resolved = null;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ValidatorFactory $validator,
        private readonly SettingValueCaster $caster,
        private readonly ConnectionInterface $db,
    ) {}

    // -------------------------------------------------------------- reads

    public function get(string $key, mixed $default = null): mixed
    {
        $map = $this->resolveMap();
        return array_key_exists($key, $map) ? $map[$key] : $default;
    }

    public function getOrFail(string $key): mixed
    {
        $map = $this->resolveMap();
        if (! array_key_exists($key, $map)) {
            throw SettingNotFoundException::for($key);
        }
        return $map[$key];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->resolveMap());
    }

    /** @return Collection<int, Setting> */
    public function byCategory(string $category): Collection
    {
        return Setting::query()
            ->where('category', $category)
            ->orderBy('display_order')
            ->orderBy('key')
            ->get();
    }

    /** @return array<string, mixed> */
    public function publicMap(): array
    {
        return $this->cache->remember(
            $this->cacheKey() . ':public',
            $this->cacheTtl(),
            function (): array {
                $map = [];
                foreach (Setting::query()->where('is_public', true)->get() as $setting) {
                    $map[$setting->key] = $this->caster->deserialize($setting);
                }
                return $map;
            },
        );
    }

    // -------------------------------------------------------------- writes

    public function set(string $key, mixed $value, ?Authenticatable $author = null, ?string $ipAddress = null): Setting
    {
        $setting = Setting::query()->where('key', $key)->first()
            ?? throw SettingNotFoundException::for($key);

        $this->assertValid($setting, $value);

        $newSerialised = $this->caster->serialize($setting, $value);
        $oldRaw = $setting->value;

        if ($newSerialised === $oldRaw) {
            return $setting; // idempotent: skip the no-op write
        }

        $this->db->transaction(function () use ($setting, $newSerialised, $oldRaw, $author, $ipAddress): void {
            $setting->update(['value' => $newSerialised]);

            SettingChangeLog::query()->create([
                'setting_id'  => $setting->getKey(),
                'user_id'     => $author?->getAuthIdentifier(),
                'old_value'   => $setting->is_encrypted ? '[encrypted]' : $oldRaw,
                'new_value'   => $setting->is_encrypted ? '[encrypted]' : $newSerialised,
                'ip_address'  => $ipAddress,
                'changed_at'  => now(),
            ]);
        });

        $this->flush();

        return $setting->refresh();
    }

    /**
     * Idempotent upsert used by seeders. Does NOT write to the change log
     * (seeders aren't user actions). Returns true if a row was created.
     */
    public function declare(array $definition): bool
    {
        $key = $definition['key'];
        $existing = Setting::query()->where('key', $key)->first();

        if ($existing !== null) {
            // Only update declarative metadata (label/description/category etc.) —
            // never overwrite a value an admin may have customised in prod.
            $existing->fill([
                'category'         => $definition['category'],
                'type'             => $definition['type'],
                'label'            => $definition['label'] ?? $existing->label,
                'description'      => $definition['description'] ?? $existing->description,
                'validation_rules' => $definition['validation_rules'] ?? $existing->validation_rules,
                'is_encrypted'     => $definition['is_encrypted'] ?? $existing->is_encrypted,
                'is_public'        => $definition['is_public'] ?? $existing->is_public,
                'is_system'        => $definition['is_system'] ?? $existing->is_system,
                'display_order'    => $definition['display_order'] ?? $existing->display_order,
                'default_value'    => $definition['default_value'] ?? $existing->default_value,
            ])->save();
            return false;
        }

        $setting = new Setting();
        $setting->fill([
            'key'              => $key,
            'category'         => $definition['category'],
            'type'             => $definition['type'],
            'label'            => $definition['label'] ?? null,
            'description'      => $definition['description'] ?? null,
            'validation_rules' => $definition['validation_rules'] ?? null,
            'is_encrypted'     => $definition['is_encrypted'] ?? false,
            'is_public'        => $definition['is_public'] ?? false,
            'is_system'        => $definition['is_system'] ?? true,
            'display_order'    => $definition['display_order'] ?? 0,
        ]);

        if (array_key_exists('default_value', $definition)) {
            $setting->default_value = $this->caster->serialize($setting, $definition['default_value']);
        }
        if (array_key_exists('value', $definition)) {
            $setting->value = $this->caster->serialize($setting, $definition['value']);
        }
        $setting->save();

        $this->flush();
        return true;
    }

    public function flush(): void
    {
        $this->resolved = null;
        $this->cache->forget($this->cacheKey());
        $this->cache->forget($this->cacheKey() . ':public');
    }

    // -------------------------------------------------------------- helpers

    /** @return array<string, mixed> */
    private function resolveMap(): array
    {
        return $this->resolved ??= $this->cache->remember(
            $this->cacheKey(),
            $this->cacheTtl(),
            function (): array {
                $map = [];
                foreach (Setting::query()->get() as $setting) {
                    $map[$setting->key] = $this->caster->deserialize($setting);
                }
                return $map;
            },
        );
    }

    private function assertValid(Setting $setting, mixed $value): void
    {
        $rules = $setting->validation_rules;
        if (! is_array($rules) || $rules === []) {
            return;
        }

        $validator = $this->validator->make(
            ['value' => $value],
            ['value' => $rules],
            ['value.*' => __('Le paramètre :attribute est invalide.')],
            ['value' => $setting->key],
        );

        if ($validator->fails()) {
            throw InvalidSettingValueException::validationFailed(
                $setting->key,
                (string) $validator->errors()->first('value'),
            );
        }
    }

    private function cacheKey(): string
    {
        return (string) config('parametrage.cache.key', 'cuk:settings:map');
    }

    private function cacheTtl(): int
    {
        return (int) config('parametrage.cache.ttl', 3600);
    }
}
