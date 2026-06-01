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

it('seeds the canonical concours currency', function (): void {
    // The fee AMOUNT now lives on the session (frais_inscription_override),
    // not in Parametrage — currency is the representative concours setting
    // that remains here.
    expect($this->settings->get('concours.fee.currency'))->toBe('FCFA');
});

it('returns null + default for missing keys', function (): void {
    expect($this->settings->get('nope'))->toBeNull()
        ->and($this->settings->get('nope', 'fallback'))->toBe('fallback');
});

it('updates a typed value and records an audit log', function (): void {
    $this->settings->set('concours.fee.currency', 'EURO');

    expect($this->settings->get('concours.fee.currency'))->toBe('EURO');

    $setting = Setting::query()->where('key', 'concours.fee.currency')->first();
    expect(SettingChangeLog::query()->where('setting_id', $setting->id)->count())->toBe(1);
});

it('rejects a value that violates its rules', function (): void {
    // concours.fee.currency enforces size:4 — a longer value must be rejected.
    $this->settings->set('concours.fee.currency', 'TOO LONG');
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

    expect($public)->toHaveKey('concours.fee.currency')
        ->and($public)->toHaveKey('site.banner.title')
        ->and($public)->not->toHaveKey('ebilling.shared_key')
        ->and($public)->not->toHaveKey('security.2fa.force_for_roles');
});

it('returns settings grouped by category', function (): void {
    $concours = $this->settings->byCategory('concours');

    expect($concours)->toHaveCount(2)
        ->and($concours->pluck('key')->all())->toContain('concours.fee.currency');
});

it('is idempotent on declare()', function (): void {
    // Run the seeder a second time — values stay, schema may be refreshed.
    $this->settings->set('concours.fee.currency', 'EURO');
    $this->seed(Modules\Parametrage\Database\Seeders\SettingsSeeder::class);

    expect($this->settings->get('concours.fee.currency'))->toBe('EURO'); // not overwritten
});
