<?php

declare(strict_types=1);

namespace App\Foundation\Permissions;

use App\Foundation\Permissions\Contracts\ScopeResolver;
use App\Foundation\Permissions\Exceptions\UnknownScopeException;

/**
 * Holds the set of registered scope resolvers, keyed by their scope key.
 *
 * Resolvers are typically registered once in a service provider:
 *
 *     $registry->register(new WildcardResolver());
 *     $registry->register(new OwnResolver());
 *     $registry->register(new OwnCenterResolver(...));
 */
final class ScopeResolverRegistry
{
    /** @var array<string, ScopeResolver> */
    private array $resolvers = [];

    public function register(ScopeResolver $resolver): void
    {
        $this->resolvers[$resolver->key()] = $resolver;
    }

    /** @throws UnknownScopeException */
    public function get(string $scope): ScopeResolver
    {
        return $this->resolvers[$scope]
            ?? throw UnknownScopeException::for($scope, array_keys($this->resolvers));
    }

    public function has(string $scope): bool
    {
        return isset($this->resolvers[$scope]);
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->resolvers);
    }
}
