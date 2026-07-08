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
    | mode: "none", "single" or "multi" (unknown values behave as "none")
    |   - none:   no tenancy at all — no tenant column, no scoping, no
    |             tenant resolution.
    |   - single: one implicit tenant. The tenant column is added to
    |             tenant-scoped tables and every request runs under the
    |             default_tenant below (created on first use), so data is
    |             tenant-stamped from day one and switching to "multi"
    |             later is just a config change.
    |   - multi:  full multi-tenancy — the tenant is resolved per request
    |             via tenant_identification.
    |
    | tenant_column: column name used for tenant scoping on models
    | tenant_identification: how tenants are identified in multi mode —
    |   "subdomain", "header" (X-Tenant-ID), "path"
    | tenant_model: the Eloquent model tenants are looked up through
    | default_tenant: name/slug of the implicit tenant used in single mode
    | exempt_paths: request paths (wildcards allowed) that never require a
    |   tenant in multi mode — probes and monitors hit these tenant-less
    |
    */

    'tenancy' => [
        'mode' => env('PROJECT_TENANCY_MODE', 'none'),
        'tenant_column' => env('PROJECT_TENANT_COLUMN', 'tenant_id'),
        'tenant_identification' => env('PROJECT_TENANT_IDENTIFICATION', 'subdomain'),
        'tenant_model' => env('PROJECT_TENANT_MODEL', 'App\Models\Tenant'),
        'default_tenant' => [
            'name' => env('PROJECT_TENANT_DEFAULT_NAME', 'Default'),
            'slug' => env('PROJECT_TENANT_DEFAULT_SLUG', 'default'),
        ],
        'exempt_paths' => [
            'api/health',
        ],
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
    | Dashboard Routes
    |--------------------------------------------------------------------------
    |
    | Applied centrally to every module's Routes/dashboard.php — a separate,
    | typically authenticated, backoffice section distinct from the public
    | web/api routes.
    |
    */

    'routes' => [
        'dashboard' => [
            'prefix' => env('PROJECT_DASHBOARD_PREFIX', 'dashboard'),
            'name_prefix' => 'dashboard.',
            'middleware' => ['web', 'auth'],
        ],
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

        // Role (from local/permission) assigned to every newly registered
        // user by Auth's AssignDefaultRole listener. null = don't assign.
        'default_role' => env('PROJECT_AUTH_DEFAULT_ROLE'),
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
        'Events',
        'Lang',
        'Listeners',
        'Models',
        'Providers',
        'Repositories',
        'Requests',
        'Resources',
        'Routes',
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
            // Country <-> City is a genuinely bidirectional domain coupling:
            // City::belongsTo(Country) + Country::hasMany(City), and the
            // geo:seed command (in Country) orchestrates City's seeder too.
            'Country' => ['City'],
            'City' => ['Country'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Async Events
    |--------------------------------------------------------------------------
    |
    | Defaults for queued listeners extending Core's QueuedListener.
    | queue: null = the default queue (what the shipped workers consume);
    | if you set a named queue here, add it to the workers' --queue= list.
    |
    */

    'events' => [
        'queue' => env('PROJECT_EVENTS_QUEUE'),
        'tries' => (int) env('PROJECT_EVENTS_TRIES', 3),
        'backoff' => [10, 60, 300],
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
