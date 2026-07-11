<?php

namespace App\Modules\Core\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;

/**
 * Tenant scoping for models, active in single and multi tenancy modes
 * (a no-op when tenancy.mode is "none"). Queries are narrowed to the
 * current tenant and new rows are stamped with it — the current tenant
 * comes from Context, which TenantMiddleware fills per request.
 */
trait HasTenantScope
{
    protected static function bootHasTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (! has_tenancy()) {
                return;
            }

            $tenantId = Context::get('tenant_id');

            if ($tenantId !== null) {
                $column = config('project.tenancy.tenant_column', 'tenant_id');
                $builder->where($builder->qualifyColumn($column), $tenantId);
            }
        });

        static::creating(function ($model) {
            if (! has_tenancy()) {
                return;
            }

            $tenantId = Context::get('tenant_id');
            $column = config('project.tenancy.tenant_column', 'tenant_id');

            if ($tenantId !== null && empty($model->getAttribute($column))) {
                $model->setAttribute($column, $tenantId);
            }
        });
    }
}
