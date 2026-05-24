<?php

declare(strict_types=1);

use App\Foundation\Permissions\Contracts\PermissionHolder;
use App\Foundation\Permissions\Contracts\Scopable;
use App\Foundation\Permissions\Permission;
use App\Foundation\Permissions\Resolvers\OwnCenterResolver;
use App\Foundation\Permissions\Resolvers\OwnResolver;
use App\Foundation\Permissions\Resolvers\WildcardResolver;
use App\Foundation\Permissions\ScopedQuery;
use App\Foundation\Permissions\ScopeResolverRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The ScopedQuery test focuses on the *SQL fragment* produced, not on
 * running queries. This keeps the unit suite fast and DB-free.
 */
function fakeHolderForQuery(array $perms = [], array $centres = []): PermissionHolder
{
    return new class($perms, $centres) implements PermissionHolder {
        public function __construct(private readonly array $perms, private readonly array $centres) {}
        public function getKey(): mixed { return 'user-1'; }
        public function permissions(): Collection {
            return collect($this->perms)->map(fn (string $p) => Permission::parse($p));
        }
        public function accessibleCentreIds(): array { return $this->centres; }
        public function accessibleRegionIds(): array { return []; }
        public function currentSessionId(): ?string { return null; }
    };
}

function fakeScopableModel(): Model & Scopable
{
    return new class extends Model implements Scopable {
        protected $table = 'candidats';
        public $incrementing = false;
        protected $keyType = 'string';

        public function scopeColumnFor(string $scope): ?string {
            return match ($scope) {
                'own'         => 'user_id',
                'own_center'  => 'centre_id',
                default       => null,
            };
        }
    };
}

function builderFor(Model $model): Builder
{
    // A Builder that doesn't need a connection to render its SQL fragments.
    $grammar = new Illuminate\Database\Query\Grammars\PostgresGrammar();
    $processor = new Illuminate\Database\Query\Processors\PostgresProcessor();
    $conn = Mockery::mock(Illuminate\Database\ConnectionInterface::class);
    $conn->shouldReceive('getQueryGrammar')->andReturn($grammar);
    $conn->shouldReceive('getPostProcessor')->andReturn($processor);
    $conn->shouldReceive('query')->andReturnUsing(fn () => new Illuminate\Database\Query\Builder($conn, $grammar, $processor));

    $query = new Illuminate\Database\Query\Builder($conn, $grammar, $processor);
    return (new Builder($query))->setModel($model);
}

function registryWithDefaults(): ScopeResolverRegistry
{
    $r = new ScopeResolverRegistry();
    $r->register(new WildcardResolver());
    $r->register(new OwnResolver());
    $r->register(new OwnCenterResolver());
    return $r;
}

describe('ScopedQuery::apply()', function (): void {

    it('denies completely when no permission covers the request', function (): void {
        $sq = new ScopedQuery(registryWithDefaults());
        $q = builderFor(fakeScopableModel());
        $out = $sq->apply($q, fakeHolderForQuery(['view:centres:*']), 'view', 'candidats');

        expect($out->toSql())->toContain('1 = 0');
    });

    it('does not restrict when a wildcard scope is held', function (): void {
        $sq = new ScopedQuery(registryWithDefaults());
        $q = builderFor(fakeScopableModel());
        $out = $sq->apply($q, fakeHolderForQuery(['view:candidats:*']), 'view', 'candidats');

        expect($out->toSql())->not->toContain('where');
    });

    it('filters by own column when only own is granted', function (): void {
        $sq = new ScopedQuery(registryWithDefaults());
        $q = builderFor(fakeScopableModel());
        $out = $sq->apply($q, fakeHolderForQuery(['view:candidats:own']), 'view', 'candidats');

        expect($out->toSql())->toContain('"user_id" =');
    });

    it('filters by centre_id IN (...) when own_center is granted', function (): void {
        $sq = new ScopedQuery(registryWithDefaults());
        $q = builderFor(fakeScopableModel());
        $out = $sq->apply(
            $q,
            fakeHolderForQuery(['view:candidats:own_center'], centres: ['c-1', 'c-2']),
            'view',
            'candidats',
        );

        expect($out->toSql())->toContain('"centre_id" in');
    });

    it('OR-combines multiple scopes', function (): void {
        $sq = new ScopedQuery(registryWithDefaults());
        $q = builderFor(fakeScopableModel());
        $out = $sq->apply(
            $q,
            fakeHolderForQuery(['view:candidats:own', 'view:candidats:own_center'], centres: ['c-1']),
            'view',
            'candidats',
        );

        $sql = $out->toSql();
        expect($sql)->toContain('"user_id" =')
            ->and($sql)->toContain('"centre_id" in')
            ->and($sql)->toContain(' or '); // nested OR between branches
    });
});
