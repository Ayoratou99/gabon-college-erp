<?php

declare(strict_types=1);

use App\Foundation\Permissions\Exceptions\InvalidPermissionFormatException;
use App\Foundation\Permissions\Permission;

describe('Permission value object', function (): void {
    it('parses a well-formed pattern', function (): void {
        $p = Permission::parse('edit:candidats:own_center');

        expect($p->action)->toBe('edit')
            ->and($p->resource)->toBe('candidats')
            ->and($p->scope)->toBe('own_center')
            ->and((string) $p)->toBe('edit:candidats:own_center');
    });

    it('accepts wildcards in every segment', function (): void {
        $p = Permission::parse('*:*:*');
        expect($p->isFullWildcard())->toBeTrue();
    });

    it('rejects the wrong number of segments', function (): void {
        Permission::parse('edit:candidats');
    })->throws(InvalidPermissionFormatException::class, 'exactly 3 segments');

    it('rejects empty segments', function (): void {
        Permission::parse('edit::own');
    })->throws(InvalidPermissionFormatException::class);

    it('rejects uppercase segments', function (): void {
        Permission::parse('EDIT:candidats:own');
    })->throws(InvalidPermissionFormatException::class);

    it('rejects non-ascii segments', function (): void {
        Permission::parse('édit:candidats:own');
    })->throws(InvalidPermissionFormatException::class);

    it('tryParse returns null instead of throwing', function (): void {
        expect(Permission::tryParse('not-valid'))->toBeNull()
            ->and(Permission::tryParse('view:users:*'))->not->toBeNull();
    });

    describe('coversActionAndResource()', function (): void {
        $cases = [
            ['view:users:*',     'view:users:*',         true],
            ['view:*:*',         'view:users:*',         true],
            ['*:*:*',            'edit:candidats:*',     true],
            ['view:users:*',     'edit:users:*',         false], // action mismatch
            ['view:users:*',     'view:centres:*',       false], // resource mismatch
            ['view:*:own_center','view:candidats:*',     true],  // resource wildcard
            ['edit:candidats:*', 'view:candidats:*',     false],
        ];
        foreach ($cases as [$granted, $required, $expected]) {
            it(sprintf('"%s" covers "%s" → %s', $granted, $required, $expected ? 'yes' : 'no'),
                function () use ($granted, $required, $expected): void {
                    expect(Permission::parse($granted)->coversActionAndResource(Permission::parse($required)))
                        ->toBe($expected);
                }
            );
        }
    });

    it('equals() does structural comparison', function (): void {
        expect(Permission::parse('view:users:*')->equals(Permission::parse('view:users:*')))->toBeTrue()
            ->and(Permission::parse('view:users:*')->equals(Permission::parse('view:users:own')))->toBeFalse();
    });
});
