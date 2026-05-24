<?php

declare(strict_types=1);

namespace App\Foundation\Concerns;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

/**
 * Trait for Eloquent models that use a UUID v7 primary key.
 *
 * UUID v7 is time-ordered, so it gives us monotonic insertion order
 * (good for B-tree indexes) while keeping the opacity / shardability
 * of a UUID. Falls back to v4 if the host PHP doesn't expose v7
 * (older ramsey/uuid).
 *
 * Migration helper:
 *
 *     $table->uuid('id')->primary();
 *
 * Model usage:
 *
 *     final class Candidat extends BaseModel { use HasUuid; }
 */
trait HasUuid
{
    public function initializeHasUuid(): void
    {
        $this->setKeyType('string');
        $this->setIncrementing(false);
    }

    public static function bootHasUuid(): void
    {
        static::creating(static function (Model $model): void {
            $key = $model->getKeyName();
            if (empty($model->getAttribute($key))) {
                $model->setAttribute($key, self::generateUuid());
            }
        });
    }

    private static function generateUuid(): string
    {
        return method_exists(Uuid::class, 'uuid7')
            ? Uuid::uuid7()->toString()
            : Uuid::uuid4()->toString();
    }
}
