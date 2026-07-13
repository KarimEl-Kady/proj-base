<?php

namespace App\Modules\Core\Support;

use App\Models\Tenant;

/**
 * Tenant resolution shared by TenantMiddleware (per request) and CLI/console
 * code that needs to establish a tenant context by hand (e.g.
 * user:make-admin, seeders — typically via the with_tenant() helper).
 * All lookups go through TenantCache.
 */
class Tenancy
{
    /**
     * Single mode's implicit tenant — created on first use so single mode
     * needs no seeding step. Returns null when the default tenant has been
     * deactivated (the tenant-level kill switch) or the model is missing.
     */
    public static function defaultTenantId(): ?int
    {
        $tenantModel = static::tenantModel();

        if (! class_exists($tenantModel)) {
            return null;
        }

        $default = config('project.tenancy.default_tenant', []);
        $slug = $default['slug'] ?? 'default';

        return TenantCache::remember($slug, function () use ($tenantModel, $default, $slug): ?int {
            $tenant = $tenantModel::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $default['name'] ?? 'Default', 'is_active' => true],
            );

            return $tenant->is_active ? $tenant->id : null;
        });
    }

    /**
     * Active tenant by slug or subdomain; null when unknown or inactive.
     */
    public static function lookupTenantId(string $identifier): ?int
    {
        $tenantModel = static::tenantModel();

        if (! class_exists($tenantModel)) {
            return null;
        }

        return TenantCache::remember($identifier, function () use ($tenantModel, $identifier): ?int {
            $tenant = $tenantModel::where('is_active', true)
                ->where(function ($query) use ($identifier) {
                    $query->where('slug', $identifier)
                        ->orWhere('subdomain', $identifier);
                })
                ->first();

            return $tenant?->id;
        });
    }

    public static function identifierForId(int|string $tenantId): ?string
    {
        $tenantModel = static::tenantModel();

        if (! class_exists($tenantModel)) {
            return null;
        }

        $tenant = $tenantModel::query()->find($tenantId);

        if ($tenant === null) {
            return null;
        }

        if (config('project.tenancy.tenant_identification') === 'subdomain') {
            return $tenant->subdomain ?: $tenant->slug;
        }

        return $tenant->slug;
    }

    /** @return class-string */
    protected static function tenantModel(): string
    {
        return config('project.tenancy.tenant_model', Tenant::class);
    }
}
