<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Parametrage\Models\Setting;
use Modules\Parametrage\Models\SettingChangeLog;
use Modules\Parametrage\Services\SettingsService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Modules\Parametrage\Database\Seeders\SettingsSeeder::class);
    $this->settings = app(SettingsService::class);
});

it('seeds the canonical concours fee', function (): void {
    expect($this->settings->get('concours.fee.amount'))->toBe(10300);
});

it('returns null + default for missing keys', function (): void {
    expect($this->settings->get('nope'))->toBeNull()
        ->and($this->settings->get('nope', 'fallback'))->toBe('fallback');
});

it('updates a typed value and records an audit log', function (): void {
    $this->settings->set('concours.fee.amount', 12500);

    expect($this->settings->get('concours.fee.amount'))->toBe(12500);

    $setting = Setting::query()->where('key', 'concours.fee.amount')->first();
    expect(SettingChangeLog::query()->where('setting_id', $setting->id)->count())->toBe(1);
});

it('rejects a wrongly-typed value', function (): void {
    $this->settings->set('concours.fee.amount', 'not a number');
})->throws(Modules\Parametrage\Exceptions\InvalidSettingValueException::class);

it('roundtrips an encrypted setting', function (): void {
    $this->settings->set('ebilling.shared_key', 'super-secret-key');

    expect($this->settings->get('ebilling.shared_key'))->toBe('super-secret-key');

    // The stored value is ciphertext, not plaintext.
    $row = Setting::query()->where('key', 'ebilling.shared_key')->first();
    expect($row->value)->not->toBe('super-secret-key');
});

it('exposes only public settings via publicMap()', function (): void {
    $public = $this->settings->publicMap();

    expect($public)->toHaveKey('concours.fee.amount')
        ->and($public)->toHaveKey('site.banner.title')
        ->and($public)->not->toHaveKey('ebilling.shared_key')
        ->and($public)->not->toHaveKey('security.2fa.force_for_roles');
});

it('returns settings grouped by category', function (): void {
    $concours = $this->settings->byCategory('concours');

    expect($concours)->toHaveCount(3)
        ->and($concours->pluck('key')->all())->toContain('concours.fee.amount');
});

it('is idempotent on declare()', function (): void {
    // Run the seeder a second time — values stay, schema may be refreshed.
    $this->settings->set('concours.fee.amount', 15000);
    $this->seed(Modules\Parametrage\Database\Seeders\SettingsSeeder::class);

    expect($this->settings->get('concours.fee.amount'))->toBe(15000); // not overwritten
});
