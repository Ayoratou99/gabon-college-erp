<?php

declare(strict_types=1);

uses(
    Tests\DuskTestCase::class,
    // Illuminate\Foundation\Testing\DatabaseMigrations::class,
)->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Foundation/unit tests run with the bare PHPUnit TestCase (no Laravel boot)
| for speed. Feature tests (Stage 2+) extend Tests\TestCase which boots the
| application via Laravel's CreatesApplication trait.
*/

uses(Tests\TestCase::class)->in('Feature');
// Module test suites live under Modules/*/tests/Feature — bind the same
// TestCase so Laravel's testing helpers (->seed(), ->postJson(), …) work.
uses(Tests\TestCase::class)->in(__DIR__ . '/../Modules');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeAuthorized', function () {
    return $this->toBeTrue();
});

expect()->extend('toBeForbidden', function () {
    return $this->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Architecture tests (preset)
|--------------------------------------------------------------------------
*/

arch('no debug statements left behind')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->each->not->toBeUsed();

arch('controllers stay thin (no Eloquent imports)')
    ->expect('Modules')
    ->classes()
    ->that->haveSuffix('Controller')
    ->not->toUse(['Illuminate\Database\Eloquent\Builder', 'Illuminate\Database\Eloquent\Model']);
