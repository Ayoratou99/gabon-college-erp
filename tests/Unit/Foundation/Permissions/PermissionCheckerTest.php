<?php

declare(strict_types=1);

use App\Foundation\Permissions\Contracts\PermissionHolder;
use App\Foundation\Permissions\Contracts\Scopable;
use App\Foundation\Permissions\PermissionChecker;
use App\Foundation\Permissions\Permission;
use App\Foundation\Permissions\Resolvers\OwnCenterResolver;
use App\Foundation\Permissions\Resolvers\OwnResolver;
use App\Foundation\Permissions\Resolvers\WildcardResolver;
use App\Foundation\Permissions\ScopeResolverRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;

/**
 * Minimal fakes — we don't want to boot the Laravel app for unit tests.
 */
function makeHolder(array $permissions = [], array $centres = [], array $regions = [], ?string $session = null): PermissionHolder
{
    return new class($permissions, $centres, $regions, $session) implements PermissionHolder {
        public function __construct(
            private readonly array $permissionStrings,
            private readonly array $centres,
            private readonly array $regions,
            private readonly ?string $session,
            private readonly string $id = 'user-1',
        ) {}
        public function getKey(): mixed { return $this->id; }
        public function permissions(): Collection {
            return collect($this->permissionStrings)->map(fn (string $p) => Permission::parse($p));
        }
        public function accessibleCentreIds(): array { return $this->centres; }
        public function accessibleRegionIds(): array { return $this->regions; }
        public function currentSessionId(): ?string { return $this->session; }
    };
}

function makeScopableTarget(array $columns, array $attributes): Model & Scopable
{
    return new class($columns, $attributes) extends Model implements Scopable {
        public function __construct(
            private readonly array $columnMap,
            array $attributes,
        ) {
            parent::__construct();
            $this->setRawAttributes($attributes);
            $this->exists = true;
        }
        public function scopeColumnFor(string $scope): ?string {
            return $this->columnMap[$scope] ?? null;
        }
    };
}

function makeChecker(): PermissionChecker
{
    $registry = new ScopeResolverRegistry();
    $registry->register(new WildcardResolver());
    $registry->register(new OwnResolver());
    $registry->register(new OwnCenterResolver());

    return new PermissionChecker($registry, new Dispatcher());
}

describe('PermissionChecker::can()', function (): void {

    it('denies an unauthenticated holder', function (): void {
        expect(makeChecker()->can(null, 'view:candidats:*'))->toBeFalse();
    });

    it('denies when the actor holds no matching permission', function (): void {
        $holder = makeHolder(['view:centres:*']);
        expect(makeChecker()->can($holder, 'view:candidats:*'))->toBeFalse();
    });

    it('grants when an exact permission is held', function (): void {
        $holder = makeHolder(['edit:candidats:*']);
        expect(makeChecker()->can($holder, 'edit:candidats:*'))->toBeTrue();
    });

    it('grants via the full wildcard', function (): void {
        $holder = makeHolder(['*:*:*']);
        expect(makeChecker()->can($holder, 'delete:anything:*'))->toBeTrue();
    });

    it('grants own scope when target belongs to the holder', function (): void {
        $holder = makeHolder(['edit:candidats:own']);
        $target = makeScopableTarget(['own' => 'user_id'], ['id' => 'c1', 'user_id' => 'user-1']);

        expect(makeChecker()->can($holder, 'edit:candidats:*', $target))->toBeTrue();
    });

    it('denies own scope when target belongs to another user', function (): void {
        $holder = makeHolder(['edit:candidats:own']);
        $target = makeScopableTarget(['own' => 'user_id'], ['id' => 'c1', 'user_id' => 'user-999']);

        expect(makeChecker()->can($holder, 'edit:candidats:*', $target))->toBeFalse();
    });

    it('grants own_center scope when target centre is in the holder set', function (): void {
        $holder = makeHolder(['view:candidats:own_center'], centres: ['centre-1', 'centre-2']);
        $target = makeScopableTarget(['own_center' => 'centre_id'], ['id' => 'c1', 'centre_id' => 'centre-2']);

        expect(makeChecker()->can($holder, 'view:candidats:*', $target))->toBeTrue();
    });

    it('denies own_center scope when target centre is outside the holder set', function (): void {
        $holder = makeHolder(['view:candidats:own_center'], centres: ['centre-1']);
        $target = makeScopableTarget(['own_center' => 'centre_id'], ['id' => 'c1', 'centre_id' => 'centre-999']);

        expect(makeChecker()->can($holder, 'view:candidats:*', $target))->toBeFalse();
    });

    it('denies own_center scope when the holder has no accessible centres', function (): void {
        $holder = makeHolder(['view:candidats:own_center'], centres: []);
        $target = makeScopableTarget(['own_center' => 'centre_id'], ['id' => 'c1', 'centre_id' => 'centre-1']);

        expect(makeChecker()->can($holder, 'view:candidats:*', $target))->toBeFalse();
    });

    it('first matching grant wins', function (): void {
        // Holder has both a denying-narrow and a granting-wide permission.
        $holder = makeHolder(['view:candidats:own', 'view:candidats:*']);
        $target = makeScopableTarget(['own' => 'user_id'], ['id' => 'c1', 'user_id' => 'other-user']);

        expect(makeChecker()->can($holder, 'view:candidats:*', $target))->toBeTrue();
    });
});
