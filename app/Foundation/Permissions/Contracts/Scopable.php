<?php

declare(strict_types=1);

namespace App\Foundation\Permissions\Contracts;

/**
 * Models opt into scope filtering by declaring which database column
 * corresponds to which scope key.
 *
 * Returning `null` for a given scope means "this model does not support
 * that scope" — in which case the resolver denies (safe default).
 *
 * Example:
 *   final class Candidat extends BaseModel implements Scopable {
 *       public function scopeColumnFor(string $scope): ?string {
 *           return match ($scope) {
 *               'own'          => 'user_id',
 *               'own_center'   => 'centre_id',
 *               'own_session'  => 'concours_session_id',
 *               default        => null,
 *           };
 *       }
 *   }
 */
interface Scopable
{
    public function scopeColumnFor(string $scope): ?string;
}
