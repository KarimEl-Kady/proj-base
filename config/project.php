<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Project Name & Version
    |--------------------------------------------------------------------------
    */

    'name' => env('PROJECT_NAME', env('APP_NAME', 'BaseProject')),
    'version' => env('PROJECT_VERSION', '1.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Database Driver
    |--------------------------------------------------------------------------
    |
    | Override the database connection driver at the project level.
    | Supported: "mysql", "pgsql", "sqlite", "mariadb", "sqlsrv"
    |
    */

    'db_driver' => env('PROJECT_DB_DRIVER', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | mode: "single" or "multi"
    | tenant_column: column name used for tenant scoping on models
    | tenant_identification: how tenants are identified — "subdomain", "header", "path"
    |
    */

    'tenancy' => [
        'mode' => env('PROJECT_TENANCY_MODE', 'single'),
        'tenant_column' => env('PROJECT_TENANT_COLUMN', 'tenant_id'),
        'tenant_identification' => env('PROJECT_TENANT_IDENTIFICATION', 'subdomain'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform
    |--------------------------------------------------------------------------
    |
    | The primary platform this project targets.
    | Supported: "web", "api", "hybrid"
    |
    */

    'platform' => env('PROJECT_PLATFORM', 'web'),

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */

    'api' => [
        'enabled' => env('PROJECT_API_ENABLED', true),
        'prefix' => env('PROJECT_API_PREFIX', 'api'),
        'version' => env('PROJECT_API_VERSION', 'v1'),
        'rate_limit' => env('PROJECT_API_RATE_LIMIT', 60),
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Attributes
    |--------------------------------------------------------------------------
    |
    | When enabled, module controllers use PHP 8 attributes (spatie/laravel-
    | route-attributes) instead of manual route files. The CoreServiceProvider
    | registers each active module's Controllers directory automatically.
    |
    */

    'route_attributes' => [
        'enabled' => env('PROJECT_ROUTE_ATTRIBUTES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Resources
    |--------------------------------------------------------------------------
    |
    | Controls JSON resource wrapping behaviour globally.
    |
    */

    'api_resources' => [
        'wrap' => env('PROJECT_API_RESOURCE_WRAP', 'data'),
        'without_wrapping' => env('PROJECT_API_RESOURCE_WITHOUT_WRAPPING', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | driver: "session", "token", "sanctum"
    | token_expiration: token lifetime in minutes
    |
    */

    'auth' => [
        'driver' => env('PROJECT_AUTH_DRIVER', 'session'),
        'token_expiration' => env('PROJECT_AUTH_TOKEN_EXPIRATION', 1440),
    ],

    /*
    |--------------------------------------------------------------------------
    | Enabled Modules
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of modules to load. Their service providers
    | are registered automatically by the Core module provider.
    |
    */

    'modules' => array_filter(explode(',', (string) env('PROJECT_MODULES', 'User'))),

    /*
    |--------------------------------------------------------------------------
    | Module Structure
    |--------------------------------------------------------------------------
    |
    | Subdirectories each module is expected to contain. Used to ensure
    | consistent HMVC layout across all modules.
    |
    */

    'module_structure' => [
        'Controllers/Api',
        'Controllers/Web',
        'Models',
        'Providers',
        'Repositories',
        'Requests',
        'Resources',
        'Services',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */

    'features' => [
        'registration' => env('PROJECT_FEATURE_REGISTRATION', true),
        'email_verification' => env('PROJECT_FEATURE_EMAIL_VERIFICATION', false),
        'two_factor_auth' => env('PROJECT_FEATURE_2FA', false),
        'api_tokens' => env('PROJECT_FEATURE_API_TOKENS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache_prefix' => env('PROJECT_CACHE_PREFIX', 'proj_base_'),

];
