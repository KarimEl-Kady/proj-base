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
    | Paths
    |--------------------------------------------------------------------------
    |
    | Filesystem layout of the project's own building blocks, relative to the
    | application root. "modules" holds HMVC modules, "vendor" holds local
    | (non-Packagist) composer packages installed via path repositories.
    |
    */

    'paths' => [
        'modules' => 'app/Modules',
        'vendor' => 'app/Vendor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Vendor Packages
    |--------------------------------------------------------------------------
    |
    | Local packages live in app/Vendor/<Name> with their own composer.json
    | and are installed through the "path" repository declared in the root
    | composer.json. They are regular composer packages: autoloaded via PSR-4
    | and discovered through extra.laravel.providers.
    |
    | composer_vendor: the composer vendor prefix (e.g. local/media)
    | namespace: the PHP root namespace (e.g. Local\Media)
    |
    */

    'vendor' => [
        'composer_vendor' => env('PROJECT_VENDOR_COMPOSER', 'local'),
        'namespace' => env('PROJECT_VENDOR_NAMESPACE', 'Local'),
    ],

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
    | Derived from the module registry (config/project_modules.php) — the
    | single source of truth for module status. Manage it via artisan
    | (module:enable / module:disable / module:delete) or by editing that
    | file directly. Service providers of enabled modules are registered
    | automatically by the Core module provider.
    |
    */

    'modules' => array_keys(array_filter(
        is_file(__DIR__.'/project_modules.php') ? (array) require __DIR__.'/project_modules.php' : []
    )),

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
        'Database/Factories',
        'Database/Migrations',
        'Database/Seeders',
        'Lang',
        'Models',
        'Providers',
        'Repositories',
        'Requests',
        'Resources',
        'Services',
        'Tests/Feature',
        'Tests/Unit',
        'Views',
    ],

    /*
    |--------------------------------------------------------------------------
    | Module Boundaries
    |--------------------------------------------------------------------------
    |
    | Modules may always depend on Core. Any other cross-module dependency
    | must be declared here or `php artisan module:boundaries` (run in CI)
    | fails. Keeps modules honest about their coupling.
    |
    */

    'boundaries' => [
        'allow' => [
            'Auth' => ['User'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Project-wide pagination defaults. "per_page" is used by BaseService /
    | BaseRepository when no explicit size is given; "max_per_page" caps any
    | client-supplied ?per_page= value.
    |
    */

    'pagination' => [
        'per_page' => (int) env('PROJECT_PAGINATION_PER_PAGE', 15),
        'max_per_page' => (int) env('PROJECT_PAGINATION_MAX_PER_PAGE', 100),
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
