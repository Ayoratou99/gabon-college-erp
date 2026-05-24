<?php

declare(strict_types=1);

namespace App\Foundation\Permissions;

use App\Foundation\Permissions\Exceptions\InvalidPermissionFormatException;
use Stringable;

/**
 * Value object representing a single permission pattern.
 *
 * Format: action:resource:scope  (three segments, separator ":")
 *
 *   action    one of: view, create, edit, delete, validate, export, manage, *
 *   resource  the domain noun: candidats, centres, sessions, parametrage, *
 *   scope     restricts which rows of `resource` the actor can touch:
 *               *               no restriction
 *               own             actor's own row(s)
 *               own_center      rows belonging to a center the actor is bound to
 *               own_region      rows in the actor's region
 *               own_session     rows in the current concours session
 *
 * Segments are case-sensitive lowercase ASCII, optionally `[a-z0-9_]+`.
 * The literal `*` is the wildcard.
 *
 * Permissions are immutable; the only mutation entry point is `parse()`.
 */
final readonly class Permission implements Stringable
{
    private const string SEGMENT_PATTERN = '/^(?:\*|[a-z][a-z0-9_]*)$/';

    public function __construct(
        public string $action,
        public string $resource,
        public string $scope,
    ) {
        foreach (['action' => $action, 'resource' => $resource, 'scope' => $scope] as $name => $value) {
            if (preg_match(self::SEGMENT_PATTERN, $value) !== 1) {
                throw InvalidPermissionFormatException::invalidSegment($name, $value);
            }
        }
    }

    /**
     * Parse a colon-separated permission string into a value object.
     *
     * @throws InvalidPermissionFormatException
     */
    public static function parse(string $pattern): self
    {
        $parts = explode(':', trim($pattern));
        if (count($parts) !== 3) {
            throw InvalidPermissionFormatException::wrongSegmentCount($pattern, count($parts));
        }

        return new self($parts[0], $parts[1], $parts[2]);
    }

    /**
     * Best-effort parser: returns null on malformed input instead of throwing.
     */
    public static function tryParse(string $pattern): ?self
    {
        try {
            return self::parse($pattern);
        } catch (InvalidPermissionFormatException) {
            return null;
        }
    }

    /**
     * Does this *granted* permission cover the given *required* permission,
     * ignoring the scope segment? Scope semantics are evaluated separately
     * by the scope resolver because they may depend on the target row.
     */
    public function coversActionAndResource(self $required): bool
    {
        return $this->segmentMatches($this->action, $required->action)
            && $this->segmentMatches($this->resource, $required->resource);
    }

    public function isWildcardScope(): bool
    {
        return $this->scope === '*';
    }

    public function isWildcardAction(): bool
    {
        return $this->action === '*';
    }

    public function isWildcardResource(): bool
    {
        return $this->resource === '*';
    }

    /**
     * Full wildcard — i.e. the super-admin permission.
     */
    public function isFullWildcard(): bool
    {
        return $this->isWildcardAction() && $this->isWildcardResource() && $this->isWildcardScope();
    }

    public function toString(): string
    {
        return "{$this->action}:{$this->resource}:{$this->scope}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function equals(self $other): bool
    {
        return $this->action === $other->action
            && $this->resource === $other->resource
            && $this->scope === $other->scope;
    }

    /**
     * A granted segment matches a required segment when it's `*` or equal.
     */
    private function segmentMatches(string $granted, string $required): bool
    {
        return $granted === '*' || $granted === $required;
    }
}
