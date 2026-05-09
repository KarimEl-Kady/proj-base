<?php

namespace App\Modules\Core\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('project.tenancy.mode') !== 'multi') {
            return $next($request);
        }

        $tenantId = $this->resolveTenantId($request);

        if ($tenantId === null) {
            abort(400, 'Tenant could not be identified.');
        }

        Context::add('tenant_id', $tenantId);

        return $next($request);
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
        $tenantModel = config('project.tenancy.tenant_model', Tenant::class);

        if (! class_exists($tenantModel)) {
            return (int) $identifier ?: null;
        }

        $tenant = $tenantModel::where('slug', $identifier)
            ->orWhere('subdomain', $identifier)
            ->first();

        return $tenant?->id;
    }
}
