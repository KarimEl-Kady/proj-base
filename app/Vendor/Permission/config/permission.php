<?php

use Local\Permission\Models\Permission;
use Local\Permission\Models\Role;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Override if you need custom Role/Permission models (must extend the
    | package's base classes).
    |
    */

    'models' => [
        'role' => Role::class,
        'permission' => Permission::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_roles' => 'model_has_roles',
        'model_has_permissions' => 'model_has_permissions',
        'role_has_permissions' => 'role_has_permissions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    |
    | The morph key used on the two model_has_* pivot tables — matches
    | whatever `$table->morphs('model')` generates in the migration.
    |
    */

    'column_names' => [
        'model_morph_key' => 'model_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | The role -> permission map is small and read on every permission check,
    | so it's cached as a whole (not per-model) via the default cache store.
    | Anything that writes to roles/permissions/role_has_permissions flushes
    | this automatically — you never need to clear it by hand.
    |
    */

    'cache' => [
        'enabled' => env('PERMISSION_CACHE_ENABLED', true),
        'ttl_seconds' => env('PERMISSION_CACHE_TTL', 3600),
        'key' => 'local.permission.role_permission_map',
    ],

    /*
    |--------------------------------------------------------------------------
    | Declarative Roles & Permissions
    |--------------------------------------------------------------------------
    |
    | Project-wide roles and permissions — mirrors local/geo-seeder's
    | config('geo_seeder.countries') convention. Edit here, then run
    | `php artisan permission:seed` (it prints its plan before writing
    | anything, and is safe to re-run).
    |
    | Permissions that belong to one module live in that module's own
    | definition file (see definition_paths below) — keep this array for
    | roles and any cross-cutting permissions that have no single owner.
    |
    | A role's permission list may include '*' to mean "every permission
    | defined anywhere" (this array + every definition_paths file).
    |
    */

    'definitions' => [
        'permissions' => [
            // Module-owned permissions are declared in each module's
            // Config/permissions.php — only cross-cutting ones go here.
        ],

        'roles' => [
            'admin' => ['*'],
            'manager' => ['users.view', 'countries.view', 'cities.view'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Module-Owned Definition Files
    |--------------------------------------------------------------------------
    |
    | Glob patterns (relative to base_path) of extra definition files, each
    | returning the same ['permissions' => [], 'roles' => []] shape as
    | "definitions" above. permission:seed merges every match with the
    | central definitions — same-named roles union their permission lists.
    | This is how a module declares the permissions of the resource it
    | ships without editing this file.
    |
    */

    'definition_paths' => [
        'app/Modules/*/Config/permissions.php',
    ],

];
