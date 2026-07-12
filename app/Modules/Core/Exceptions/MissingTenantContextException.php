<?php

namespace App\Modules\Core\Exceptions;

use RuntimeException;

/**
 * Thrown by HasTenantScope in strict mode (project.tenancy.strict, the
 * default) when a tenant-scoped model is queried or created while tenancy
 * is active but no tenant is in Context — instead of silently running
 * unscoped (querying every tenant's rows / creating unstamped rows).
 *
 * Happens in code paths that don't go through TenantMiddleware: artisan
 * commands, seeders, scheduled tasks, tinker. Queued listeners are fine —
 * Context (including tenant_id) is captured at dispatch and restored when
 * the job runs.
 */
class MissingTenantContextException extends RuntimeException
{
    public static function for(string $model, string $operation): static
    {
        return new static(
            "Cannot {$operation} tenant-scoped model [{$model}] without a tenant in Context. ".
            'Wrap the call in with_tenant($tenantId, fn () => ...) to act as one tenant, '.
            'or without_tenant_scope(fn () => ...) to deliberately run across all tenants. '.
            'Set PROJECT_TENANCY_STRICT=false to restore the legacy fail-open behavior.'
        );
    }
}
