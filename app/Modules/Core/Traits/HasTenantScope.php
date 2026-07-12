<?php

namespace App\Modules\Core\Traits;

use App\Modules\Core\Exceptions\MissingTenantContextException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;

/**
 * Tenant scoping for models, active in single and multi tenancy modes
 * (a no-op when tenancy.mode is "none"). Queries are narrowed to the
 * current tenant and new rows are stamped with it — the current tenant
 * comes from Context, which TenantMiddleware fills per request (and which
 * Laravel carries into queued jobs automatically).
 *
 * Fail-closed by default: in strict mode (project.tenancy.strict, on by
 * default) using a tenant-scoped model while tenancy is active but no
 * tenant is in Context throws MissingTenantContextException instead of
 * silently running unscoped. Code paths outside TenantMiddleware (artisan
 * commands, seeders, scheduled tasks) must choose explicitly:
 *
 *     with_tenant($tenantId, fn () => ...)     // act as one tenant
 *     without_tenant_scope(fn () => ...)       // deliberately cross-tenant
 */
trait HasTenantScope
{
    protected static function bootHasTenantScope(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (! has_tenancy() || Context::get('tenancy_bypass') === true) {
                return;
            }

            $tenantId = Context::get('tenant_id');

            if ($tenantId === null) {
                if (config('project.tenancy.strict', true)) {
                    throw MissingTenantContextException::for($builder->getModel()::class, 'query');
                }

                return;
            }

            $column = config('project.tenancy.tenant_column', 'tenant_id');
            $builder->where($builder->qualifyColumn($column), $tenantId);
        });

        static::creating(function ($model) {
            if (! has_tenancy() || Context::get('tenancy_bypass') === true) {
                return;
            }

            $column = config('project.tenancy.tenant_column', 'tenant_id');

            // An explicitly pre-set tenant column always wins — the caller
            // has already decided which tenant this row belongs to.
            if (! empty($model->getAttribute($column))) {
                return;
            }

            $tenantId = Context::get('tenant_id');

            if ($tenantId === null) {
                if (config('project.tenancy.strict', true)) {
                    throw MissingTenantContextException::for($model::class, 'create');
                }

                return;
            }

            $model->setAttribute($column, $tenantId);
        });
    }
}
