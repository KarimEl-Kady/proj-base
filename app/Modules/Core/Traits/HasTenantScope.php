<?php

namespace App\Modules\Core\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Context;

trait HasTenantScope
{
    protected static function bootHasTenantScope(): void
    {
        if (config('project.tenancy.mode') !== 'multi') {
            return;
        }

        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = Context::get('tenant_id');

            if ($tenantId !== null) {
                $column = config('project.tenancy.tenant_column', 'tenant_id');
                $builder->where($builder->qualifyColumn($column), $tenantId);
            }
        });

        static::creating(function ($model) {
            if (config('project.tenancy.mode') !== 'multi') {
                return;
            }

            $tenantId = Context::get('tenant_id');

            if ($tenantId !== null && empty($model->getAttribute('tenant_id'))) {
                $column = config('project.tenancy.tenant_column', 'tenant_id');
                $model->setAttribute($column, $tenantId);
            }
        });
    }
}
