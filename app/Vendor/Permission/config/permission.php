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
    | The single place that decides what roles/permissions exist in this
    | project — mirrors local/geo-seeder's config('geo_seeder.countries')
    | convention. Edit here, then run `php artisan permission:seed` (it
    | prints its plan before writing anything, and is safe to re-run).
    |
    | A role's permission list may include '*' to mean "every permission
    | defined below".
    |
    */

    'definitions' => [
        'permissions' => [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'countries.view', 'countries.manage',
            'cities.view', 'cities.manage',
        ],

        'roles' => [
            'admin' => ['*'],
            'manager' => ['users.view', 'countries.view', 'cities.view'],
        ],
    ],

];
