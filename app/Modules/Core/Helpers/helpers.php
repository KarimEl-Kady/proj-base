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
