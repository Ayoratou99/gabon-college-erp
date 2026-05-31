<?php

declare(strict_types=1);

use App\Foundation\Exports\ExportBuilder;
use App\Foundation\Exports\Exceptions\ExportFormatNotSupportedException;
use Illuminate\Database\Eloquent\Builder;

function fakeBuilder(): Builder
{
    return Mockery::mock(Builder::class);
}

it('refuses to download without columns declared', function (): void {
    ExportBuilder::for(fakeBuilder())->download('xlsx');
})->throws(LogicException::class, 'No columns declared');

it('rejects an unsupported format', function (): void {
    ExportBuilder::for(fakeBuilder())
        ->columns([['header' => 'X', 'accessor' => 'x']])
        ->download('docx');
})->throws(ExportFormatNotSupportedException::class);

it('normalises filenames into safe slugs', function (): void {
    $b = ExportBuilder::for(fakeBuilder())
        ->columns([['header' => 'X', 'accessor' => 'x']])
        ->filename('Candidats — Session 2025/2026!');

    // The slugged value is private; sanity-check by reflecting on the protected state.
    $ref = new ReflectionClass($b);
    $prop = $ref->getProperty('filenameBase');
    $prop->setAccessible(true);

    // Our regex /[^a-z0-9\-_]+/i collapses consecutive non-alnum into a single dash.
    expect($prop->getValue($b))->toBe('candidats-session-2025-2026-');
});
