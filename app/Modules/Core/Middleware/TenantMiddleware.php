<?php

namespace App\Modules\Core\Middleware;

use App\Modules\Core\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\URL;
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
 *
 * Resolution lives in Core\Support\Tenancy (shared with CLI code) and is
 * cached through TenantCache (tenancy.cache config) so steady-state
 * requests skip the tenants-table query; tenant writes flush their entries
 * via the model hooks in App\Models\Tenant.
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! has_tenancy() || $this->isExempt($request)) {
            return $next($request);
        }

        $tenantId = is_single_tenant()
            ? Tenancy::defaultTenantId()
            : $this->resolveTenantId($request);

        if ($tenantId === null) {
            abort(400, 'Tenant could not be identified.');
        }

        Context::add('tenant_id', $tenantId);

        if (config('project.tenancy.tenant_identification') === 'path') {
            $request->route()?->forgetParameter('tenant');
        }

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

        return Tenancy::lookupTenantId($parts[0]);
    }

    protected function resolveFromHeader(Request $request): ?int
    {
        $tenant = $request->header('X-Tenant-ID');

        if ($tenant === null
            && $request->query('tenant') !== null
            && URL::hasValidSignature($request, absolute: false)) {
            $tenant = $request->query('tenant');
        }

        return $tenant !== null ? Tenancy::lookupTenantId($tenant) : null;
    }

    protected function resolveFromPath(Request $request): ?int
    {
        $segment = $request->segment(1);

        return $segment !== null ? Tenancy::lookupTenantId($segment) : null;
    }
}
