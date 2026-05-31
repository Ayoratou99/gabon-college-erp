<?php

declare(strict_types=1);

namespace Modules\Parametrage\Services;

use Illuminate\Contracts\Encryption\Encrypter;
use Modules\Parametrage\Exceptions\InvalidSettingValueException;
use Modules\Parametrage\Models\Setting;

/**
 * Bidirectional caster between the raw TEXT stored in `settings.value` and
 * the typed PHP value the application expects.
 *
 * Encryption (when `setting.is_encrypted = true`) is applied AFTER serialisation
 * on store, and BEFORE deserialisation on read. This means a JSON-typed
 * encrypted setting is fully decrypted then JSON-decoded — never the other
 * way around — so partial decryption failures can't leak structure.
 */
final class SettingValueCaster
{
    public function __construct(
        private readonly Encrypter $encrypter,
    ) {}

    /**
     * Decode the stored TEXT into the typed PHP value.
     */
    public function deserialize(Setting $setting): mixed
    {
        $raw = $setting->value ?? $setting->default_value;
        if ($raw === null) {
            return null;
        }

        if ($setting->is_encrypted) {
            $raw = $this->encrypter->decryptString($raw);
        }

        return $this->coerceFromString($raw, $setting->type);
    }

    /**
     * Encode the typed PHP value into the TEXT that goes into `settings.value`.
     */
    public function serialize(Setting $setting, mixed $value): string
    {
        $serialised = $this->coerceToString($value, $setting->type, $setting->key);

        return $setting->is_encrypted
            ? $this->encrypter->encryptString($serialised)
            : $serialised;
    }

    /**
     * Helpful for the admin UI to know how to render a field.
     */
    public function formInputType(string $type): string
    {
        return match ($type) {
            'integer', 'decimal' => 'number',
            'boolean'            => 'checkbox',
            'text', 'json'       => 'textarea',
            'email'              => 'email',
            'phone'              => 'tel',
            'url', 'image_url'   => 'url',
            'color'              => 'color',
            default              => 'text',
        };
    }

    private function coerceFromString(string $raw, string $type): mixed
    {
        return match ($type) {
            'string', 'text', 'email', 'phone', 'url', 'image_url', 'color' => $raw,
            'integer'  => (int) $raw,
            'decimal'  => (float) $raw,
            'boolean'  => in_array(mb_strtolower($raw), ['1', 'true', 'yes', 'on'], true),
            'json'     => json_decode($raw, true, flags: JSON_THROW_ON_ERROR),
            default    => throw InvalidSettingValueException::unknownType($type),
        };
    }

    private function coerceToString(mixed $value, string $type, string $key): string
    {
        return match ($type) {
            'string', 'text', 'email', 'phone', 'url', 'image_url' => $this->asString($value, $type, $key),
            'color'   => $this->asColor($value, $key),
            'integer' => is_numeric($value)
                ? (string) (int) $value
                : throw InvalidSettingValueException::wrongType($key, 'integer', $value),
            'decimal' => is_numeric($value)
                ? (string) (float) $value
                : throw InvalidSettingValueException::wrongType($key, 'decimal', $value),
            'boolean' => $value ? '1' : '0',
            'json'    => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default   => throw InvalidSettingValueException::unknownType($type),
        };
    }

    private function asString(mixed $value, string $type, string $key): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        throw InvalidSettingValueException::wrongType($key, $type, $value);
    }

    private function asColor(mixed $value, string $key): string
    {
        if (! is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
            throw InvalidSettingValueException::wrongType($key, 'color (#RRGGBB)', $value);
        }
        return mb_strtolower($value);
    }
}
