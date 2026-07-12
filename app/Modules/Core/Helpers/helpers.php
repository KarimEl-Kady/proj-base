<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

if (! function_exists('tenant_id')) {
    function tenant_id(): int|string|null
    {
        return Context::get('tenant_id');
    }
}

if (! function_exists('tenancy_mode')) {
    /**
     * Normalized tenancy mode: "none", "single" or "multi".
     * Unknown config values fall back to "none".
     */
    function tenancy_mode(): string
    {
        $mode = config('project.tenancy.mode', 'none');

        return in_array($mode, ['single', 'multi'], true) ? $mode : 'none';
    }
}

if (! function_exists('has_tenancy')) {
    function has_tenancy(): bool
    {
        return tenancy_mode() !== 'none';
    }
}

if (! function_exists('is_single_tenant')) {
    function is_single_tenant(): bool
    {
        return tenancy_mode() === 'single';
    }
}

if (! function_exists('is_multi_tenant')) {
    function is_multi_tenant(): bool
    {
        return tenancy_mode() === 'multi';
    }
}

if (! function_exists('with_tenant')) {
    /**
     * Run $callback with the given tenant in Context — the CLI/console
     * counterpart of TenantMiddleware for seeders, commands, and jobs
     * dispatched outside a request. Restores the previous tenant after.
     */
    function with_tenant(int|string|null $tenantId, Closure $callback): mixed
    {
        $previous = Context::get('tenant_id');
        Context::add('tenant_id', $tenantId);

        try {
            return $callback();
        } finally {
            $previous === null ? Context::forget('tenant_id') : Context::add('tenant_id', $previous);
        }
    }
}

if (! function_exists('without_tenant_scope')) {
    /**
     * Run $callback with tenant scoping deliberately disabled — queries see
     * every tenant's rows and creates are not stamped. The explicit opt-out
     * strict mode points to for genuinely cross-tenant work (reports,
     * global maintenance commands).
     */
    function without_tenant_scope(Closure $callback): mixed
    {
        $previous = Context::get('tenancy_bypass');
        Context::add('tenancy_bypass', true);

        try {
            return $callback();
        } finally {
            $previous === null ? Context::forget('tenancy_bypass') : Context::add('tenancy_bypass', $previous);
        }
    }
}

if (! function_exists('project_config')) {
    function project_config(string $key, mixed $default = null): mixed
    {
        return config("project.{$key}", $default);
    }
}

if (! function_exists('module_path')) {
    function module_path(string $module = '', string $path = ''): string
    {
        $base = app_path('Modules');

        if ($module === '') {
            return $base;
        }

        $modulePath = $base.DIRECTORY_SEPARATOR.Str::studly($module);

        if ($path === '') {
            return $modulePath;
        }

        return $modulePath.DIRECTORY_SEPARATOR.ltrim($path, '/');
    }
}
