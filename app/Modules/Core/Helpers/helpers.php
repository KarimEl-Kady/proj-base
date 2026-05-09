<?php

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

if (! function_exists('tenant_id')) {
    function tenant_id(): int|string|null
    {
        return Context::get('tenant_id');
    }
}

if (! function_exists('is_multi_tenant')) {
    function is_multi_tenant(): bool
    {
        return config('project.tenancy.mode') === 'multi';
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
