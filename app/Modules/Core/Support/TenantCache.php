<?php

namespace App\Modules\Core\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Caches identifier → tenant id resolution so TenantMiddleware doesn't hit
 * the tenants table on every request (mirrors local/permission's
 * PermissionRegistry approach: small, hot, rarely-changing lookups).
 *
 * Only successful resolutions are cached — a null (unknown identifier or
 * inactive tenant) is re-checked against the database each time, so
 * activating a tenant takes effect immediately. Invalidation on writes is
 * wired in the tenant model's booted() hooks (see App\Models\Tenant);
 * custom tenant models (project.tenancy.tenant_model) should call
 * forgetTenant() from their own saved/deleted events the same way.
 */
class TenantCache
{
    /**
     * @param  Closure(): (int|null)  $resolver
     */
    public static function remember(string $identifier, Closure $resolver): ?int
    {
        if (! static::enabled()) {
            return $resolver();
        }

        $key = static::key($identifier);
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        $tenantId = $resolver();

        if ($tenantId !== null) {
            Cache::put($key, $tenantId, (int) config('project.tenancy.cache.ttl_seconds', 3600));
        }

        return $tenantId;
    }

    /**
     * Drop every cached resolution that could point at this tenant — both
     * identifier columns, old and new values (a slug rename must kill the
     * stale key, not just the current one).
     */
    public static function forgetTenant(Model $tenant): void
    {
        $identifiers = array_unique(array_filter([
            $tenant->getOriginal('slug'),
            $tenant->getAttribute('slug'),
            $tenant->getOriginal('subdomain'),
            $tenant->getAttribute('subdomain'),
        ]));

        foreach ($identifiers as $identifier) {
            Cache::forget(static::key($identifier));
        }
    }

    protected static function key(string $identifier): string
    {
        return config('project.cache_prefix', '').'tenant.'.$identifier;
    }

    protected static function enabled(): bool
    {
        return (bool) config('project.tenancy.cache.enabled', true);
    }
}
