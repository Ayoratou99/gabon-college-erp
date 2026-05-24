<?php

declare(strict_types=1);

use App\Foundation\Exports\ColumnDefinition;

it('resolves a simple attribute via dotted path', function (): void {
    $row = (object) ['nom' => 'NDONG'];
    $col = ColumnDefinition::fromArray(['header' => 'Nom', 'accessor' => 'nom']);

    expect($col->valueFor($row))->toBe('NDONG');
});

it('walks dotted paths through nested objects, returning null on a broken segment', function (): void {
    $row = (object) ['centre' => (object) ['nom' => 'Libreville']];
    $col = ColumnDefinition::fromArray(['header' => 'Centre', 'accessor' => 'centre.nom']);
    expect($col->valueFor($row))->toBe('Libreville');

    $orphan = (object) ['centre' => null];
    expect($col->valueFor($orphan))->toBeNull();
});

it('honours Closure accessors', function (): void {
    $row = (object) ['nom' => 'NDONG', 'prenom' => 'Alex'];
    $col = ColumnDefinition::fromArray([
        'header' => 'Identité',
        'accessor' => fn ($r): string => $r->nom . ' / ' . $r->prenom,
    ]);
    expect($col->valueFor($row))->toBe('NDONG / Alex');
});

it('casts boolean to French Oui/Non', function (): void {
    $col = ColumnDefinition::fromArray(['header' => 'Bac ?', 'accessor' => 'deja_bac', 'format' => 'boolean']);

    expect($col->valueFor((object) ['deja_bac' => true]))->toBe('Oui')
        ->and($col->valueFor((object) ['deja_bac' => false]))->toBe('Non');
});

it('formats DateTimeInterface for date / datetime', function (): void {
    $dt = new \DateTimeImmutable('2025-04-10 09:30');
    $dateCol     = ColumnDefinition::fromArray(['header' => 'Jour',   'accessor' => 'd', 'format' => 'date']);
    $datetimeCol = ColumnDefinition::fromArray(['header' => 'Moment', 'accessor' => 'd', 'format' => 'datetime']);
    $row = (object) ['d' => $dt];

    expect($dateCol->valueFor($row))->toBe('2025-04-10')
        ->and($datetimeCol->valueFor($row))->toBe('2025-04-10 09:30');
});

it('coerces numeric strings on integer/decimal columns', function (): void {
    $intCol = ColumnDefinition::fromArray(['header' => 'N',  'accessor' => 'n', 'format' => 'integer']);
    $decCol = ColumnDefinition::fromArray(['header' => 'V',  'accessor' => 'v', 'format' => 'decimal']);

    expect($intCol->valueFor((object) ['n' => '42']))->toBe(42)
        ->and($decCol->valueFor((object) ['v' => '12.5']))->toBe(12.5);
});

it('returns null for null values regardless of format', function (): void {
    $col = ColumnDefinition::fromArray(['header' => 'X', 'accessor' => 'x', 'format' => 'date']);
    expect($col->valueFor((object) ['x' => null]))->toBeNull();
});
