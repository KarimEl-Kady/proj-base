<?php

namespace App\Modules\Core\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Populates the model's "uuid" column on create. The primary key stays a
 * regular auto-increment integer (internal); the uuid is the public
 * identifier exposed through API resources and used for route binding.
 */
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::orderedUuid();
            }
        });
    }

    public function scopeWhereUuid(Builder $query, string $uuid): Builder
    {
        return $query->where($this->qualifyColumn('uuid'), $uuid);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
