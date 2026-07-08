<?php

namespace App\Modules\Core\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the current tenant id into Context, according to the tenancy mode
 * (config/project.php → tenancy.mode):
 *
 * - none:   pass-through, no tenant is ever set.
 * - single: every request runs under the implicit default tenant
 *           (tenancy.default_tenant), created on first use.
 * - multi:  the tenant is resolved from the request via
 *           tenancy.tenant_identification; unidentifiable requests get 400.
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! has_tenancy() || $this->isExempt($request)) {
            return $next($request);
        }

        $tenantId = is_single_tenant()
            ? $this->resolveDefaultTenantId()
            : $this->resolveTenantId($request);

        if ($tenantId === null) {
            abort(400, 'Tenant could not be identified.');
        }

        Context::add('tenant_id', $tenantId);

        return $next($request);
    }

    /**
     * Paths that never require a tenant (health checks, probes) — see
     * project.tenancy.exempt_paths.
     */
    protected function isExempt(Request $request): bool
    {
        $paths = config('project.tenancy.exempt_paths', []);

        return $paths !== [] && $request->is(...$paths);
    }

    /**
     * Single mode: the one tenant everything belongs to. Created on first
     * use so single mode needs no seeding step.
     */
    protected function resolveDefaultTenantId(): ?int
    {
        $tenantModel = $this->tenantModel();

        if (! class_exists($tenantModel)) {
            return null;
        }

        $default = config('project.tenancy.default_tenant', []);

        $tenant = $tenantModel::query()->firstOrCreate(
            ['slug' => $default['slug'] ?? 'default'],
            ['name' => $default['name'] ?? 'Default', 'is_active' => true],
        );

        return $tenant->id;
    }

    protected function resolveTenantId(Request $request): ?int
    {
        $method = config('project.tenancy.tenant_identification', 'subdomain');

        return match ($method) {
            'subdomain' => $this->resolveFromSubdomain($request),
            'header' => $this->resolveFromHeader($request),
            'path' => $this->resolveFromPath($request),
            default => null,
        };
    }

    protected function resolveFromSubdomain(Request $request): ?int
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];

        return $this->lookupTenant($subdomain);
    }

    protected function resolveFromHeader(Request $request): ?int
    {
        $tenant = $request->header('X-Tenant-ID');

        return $tenant !== null ? $this->lookupTenant($tenant) : null;
    }

    protected function resolveFromPath(Request $request): ?int
    {
        $segment = $request->segment(1);

        return $segment !== null ? $this->lookupTenant($segment) : null;
    }

    protected function lookupTenant(string $identifier): ?int
    {
        $tenantModel = $this->tenantModel();

        if (! class_exists($tenantModel)) {
            return null;
        }

        $tenant = $tenantModel::where('is_active', true)
            ->where(function ($query) use ($identifier) {
                $query->where('slug', $identifier)
                    ->orWhere('subdomain', $identifier);
            })
            ->first();

        return $tenant?->id;
    }

    /** @return class-string */
    protected function tenantModel(): string
    {
        return config('project.tenancy.tenant_model', Tenant::class);
    }
}
