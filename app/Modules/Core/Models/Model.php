<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * @method static Builder|null forTenant(int|string|null $tenantId)
 */
abstract class Model extends Eloquent
{
    use HasUuid;

    protected $guarded = ['id', 'uuid'];

    public function scopeForTenant(Builder $query, int|string|null $tenantId): Builder
    {
        if ($tenantId === null) {
            return $query;
        }

        $column = config('project.tenancy.tenant_column', 'tenant_id');

        return $query->where($column, $tenantId);
    }
}
