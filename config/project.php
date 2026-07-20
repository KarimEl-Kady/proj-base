<?php

use App\Modules\Geo\Models\City;
use App\Modules\Geo\Models\Country;

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
    | Tenancy Configuration
    |--------------------------------------------------------------------------
    |
    | mode: "none", "single" or "multi" (unknown values behave as "none")
    | strict: fail closed — using a tenant-scoped model while tenancy is
    |   active but no tenant is in Context (CLI, seeders, cron) throws
    |   instead of silently running unscoped; wrap such code in
    |   with_tenant() or without_tenant_scope(). Off = legacy fail-open.
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
    | cache: identifier → tenant id resolutions are cached (via Core's
    |   TenantCache) so steady-state requests skip the tenants-table query;
    |   tenant writes flush their own entries, so this is safe to leave on
    |
    */

    'tenancy' => [
        'mode' => env('PROJECT_TENANCY_MODE', 'none'),
        'strict' => env('PROJECT_TENANCY_STRICT', true),
        'tenant_column' => env('PROJECT_TENANT_COLUMN', 'tenant_id'),
        'tenant_identification' => env('PROJECT_TENANT_IDENTIFICATION', 'subdomain'),
        'tenant_model' => env('PROJECT_TENANT_MODEL', 'App\Models\Tenant'),
        'default_tenant' => [
            'name' => env('PROJECT_TENANT_DEFAULT_NAME', 'Default'),
            'slug' => env('PROJECT_TENANT_DEFAULT_SLUG', 'default'),
        ],
        'exempt_paths' => [
            'api/health*',
        ],
        'cache' => [
            'enabled' => env('PROJECT_TENANCY_CACHE_ENABLED', true),
            'ttl_seconds' => env('PROJECT_TENANCY_CACHE_TTL', 3600),
        ],

        // Every concrete model in an active, non-Core module must either use
        // HasTenantScope or be listed here — `php artisan tenant:classify`
        // (run in CI) fails otherwise. This is deliberately independent of
        // HasTenantScope itself: tenant:migrations/tenant:check discover
        // their targets *by* the trait, so a model that simply forgot to add
        // it is invisible to both. This list is the other half — it forces
        // every model to be classified one way or the other, so "nobody
        // decided" is never a silent third option. Add a model here only
        // when it's genuinely shared, unscoped reference data across every
        // tenant (see Geo's Country/City — global geography data, not
        // per-tenant).
        'global_models' => [
            Country::class,
            City::class,
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
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Applied to the "web" group (and therefore the dashboard) by
    | App\Modules\Core\Middleware\SecurityHeadersMiddleware — the API group
    | serves JSON to non-browser clients and doesn't need clickjacking/MIME
    | -sniffing protection the same way. `csp` is left null by default: the
    | shipped modules have no real dashboard UI yet (see AGENTS.md), so a
    | one-size-fits-all Content-Security-Policy here would only ever be a
    | guess. Set PROJECT_SECURITY_CSP once your project has a real frontend
    | to write a policy against.
    |
    */

    'security' => [
        'csp' => env('PROJECT_SECURITY_CSP'),
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
    |   sanctum (default) issues Bearer tokens — what the API routes expect.
    |   session authenticates on the web guard; it only works on routes that
    |   have session state (the "web" group / statefulApi), not the plain
    |   "api" group.
    | token_expiration: login token lifetime in minutes
    | personal_token_expiration: named integration token lifetime in minutes
    |
    */

    'auth' => [
        'driver' => env('PROJECT_AUTH_DRIVER', 'sanctum'),
        'token_expiration' => env('PROJECT_AUTH_TOKEN_EXPIRATION', 1440),
        'personal_token_expiration' => env('PROJECT_AUTH_PERSONAL_TOKEN_EXPIRATION', 43200),

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
        // Cycles are rejected unless their complete module set is listed
        // here. Country and City used to be separate modules with a
        // declared cycle between them (City::belongsTo(Country) +
        // Country::hasMany(City)); they were merged into one Geo module
        // instead, since neither could be enabled/extracted independently
        // of the other — see docs/architecture.md's "Module Or Package"
        // section. Prefer a merge like that over adding a new entry here;
        // allow_cycles exists for cases where the coupling is real but the
        // modules still have independent reasons to change.
        'allow_cycles' => [],
        'allow' => [
            'Auth' => ['User'],
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
        'tries' => (int) env('PROJECT_EVENTS_TRIES', 3),
        'backoff' => [10, 60, 300],

        // Named lanes so one workload can't starve another sharing the same
        // worker pool — e.g. a bulk import job delaying a password-reset
        // email queued right behind it. QueuedListener::$lane picks one of
        // these (default: "default"); every lane is null (the connection's
        // default queue) out of the box, so this changes nothing until a
        // project actually needs isolation. The shipped workers
        // (docker-compose `queue` service, `composer dev`) already listen
        // on all three names — set a lane's env var to a distinct queue
        // name and jobs on it start draining separately with no worker
        // redeploy needed.
        'lanes' => [
            'default' => env('PROJECT_EVENTS_QUEUE_DEFAULT'),
            'bulk' => env('PROJECT_EVENTS_QUEUE_BULK'),
            'notifications' => env('PROJECT_EVENTS_QUEUE_NOTIFICATIONS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transactional Outbox
    |--------------------------------------------------------------------------
    |
    | Publisher claims expire so a crashed publisher cannot strand work.
    | Failed publishes back off and become dead-lettered at max_attempts;
    | release them after investigation with `outbox:retry`.
    |
    */

    'outbox' => [
        'max_attempts' => (int) env('PROJECT_OUTBOX_MAX_ATTEMPTS', 10),
        'claim_ttl_seconds' => (int) env('PROJECT_OUTBOX_CLAIM_TTL', 300),
        'backoff' => [10, 60, 300, 900, 3600],
        'retention' => [
            'published_hours' => (int) env('PROJECT_OUTBOX_PUBLISHED_RETENTION', 168),
            'failed_hours' => (int) env('PROJECT_OUTBOX_FAILED_RETENTION', 720),
            'processed_hours' => (int) env('PROJECT_INBOX_PROCESSED_RETENTION', 720),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Project-wide pagination defaults. "per_page" is used by BaseService /
    | BaseRepository when no explicit size is given; "max_per_page" caps any
    | client-supplied ?per_page= value; "unpaginated_cap" bounds how many
    | rows ?pagination=false may return — a client toggle must never be able
    | to pull an entire multi-million-row table into memory. Raise it (or
    | the env var) deliberately for endpoints that truly need full exports.
    |
    */

    'pagination' => [
        'per_page' => (int) env('PROJECT_PAGINATION_PER_PAGE', 15),
        'max_per_page' => (int) env('PROJECT_PAGINATION_MAX_PER_PAGE', 100),
        'unpaginated_cap' => (int) env('PROJECT_PAGINATION_UNPAGINATED_CAP', 1000),
    ],

    'health' => [
        'require_queue_worker' => env('PROJECT_HEALTH_REQUIRE_QUEUE_WORKER', false),
        'queue_heartbeat_ttl' => (int) env('PROJECT_HEALTH_QUEUE_HEARTBEAT_TTL', 120),
        'queue_backlog_warning' => (int) env('PROJECT_HEALTH_QUEUE_BACKLOG_WARNING', 1000),
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
        // Named personal access tokens (Auth's /tokens endpoints) — for
        // integrations/CLI. Login always issues a session token regardless;
        // this flag only gates *named* token management.
        'personal_access_tokens' => env('PROJECT_FEATURE_PERSONAL_ACCESS_TOKENS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    */

    'cache_prefix' => env('PROJECT_CACHE_PREFIX', 'proj_base_'),

];
