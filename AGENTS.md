# AGENTS.md

## Dev commands

| Task | Local | Docker |
|------|-------|--------|
| Setup | `composer setup` | `make setup` |
| Run dev | `composer dev` | `make dev` |
| Run tests | `composer test` | `make test` |
| Single test | `php artisan test --filter=ClassName` | `make artisan c="test --filter=ClassName"` |
| Lint (PHP) | `./vendor/bin/pint` | `make lint` |
| Static analysis | `composer analyse` (PHPStan/Larastan, level 5) | `make analyse` |
| Full gate (lint + analyse + boundaries + tests) | `composer check` | — |
| Frontend dev | `npm run dev` | started automatically with `make dev` |
| Frontend build | `npm run build` | `make npm c="run build"` |
| Make module | `php artisan make:module` (interactive) | `make module` |
| Module component | `php artisan module:make Blog model Post` | `make artisan c="module:make Blog model Post"` |
| List modules | `php artisan module:list` | `make artisan c="module:list"` |
| Make local package | `php artisan make:package Payment` | `make artisan c="make:package Payment"` |
| List local packages | `php artisan package:list` | `make artisan c="package:list"` |
| Project overview | `php artisan project:info` | `make artisan c="project:info"` |
| Shell | — | `make shell` |
| Tinker | `php artisan tinker` | `make tinker` |
| Logs | — | `make logs` or `make logs s=app` |

## Architecture

Laravel 13.7, PHP 8.3+, Vite 8 + Tailwind CSS 4. Modular app structure under `app/Modules/`.

### Module system

- Active modules are controlled by the **module registry** at `config/project_modules.php` (`'Name' => bool` map) — the single source of truth, managed via artisan or edited by hand. `config('project.modules')` derives the enabled list from it. The `App\Modules\Core\Support\ModuleRegistry` class reads/writes the file.
- `CoreServiceProvider` (`app/Modules/Core/Providers/CoreServiceProvider.php:32`) auto-registers each active module's `{Module}ServiceProvider` at `app/Modules/{Module}/Providers/{Module}ServiceProvider.php`.
- Use `module_path('Module')` helper to resolve module directories. Do NOT hardcode paths.
- Module directory structure is defined in `config/project.php` under `module_structure` key.
- Generate a new module: `php artisan make:module` — takes no arguments or flags, purely an **interactive wizard** (name, owning team, API/Web/both, extras: migration/seeder/factory, enable now). New modules are registered as enabled in the registry automatically. An owning team answer (a GitHub handle, e.g. `@org/blog-team`) is appended to `.github/CODEOWNERS` automatically — leave it blank to skip. See `.github/CODEOWNERS` for the current module-ownership map; edit it by hand for anything the wizard didn't cover.
- The command creates: ServiceProvider, Model, Repository, Service, ApiController (FetchRequest-driven index), WebController, Fetch/Create/Update Requests, Resource, and plain route files.
- Module lifecycle commands (all prompt for the module when the argument is omitted): `module:list` (status table), `module:enable` / `module:disable` (toggle in the registry), `module:delete` (removes directory + registry entry).
- Module creation, enable, disable, and deletion automatically clear config, route, and event caches so cached runtime state cannot disagree with the registry.
- **Module boundaries**: modules may depend only on Core plus dependencies declared in `config/project.php` → `boundaries.allow` (e.g. `'Auth' => ['User']`). `php artisan module:boundaries` verifies this and runs in CI — declare new cross-module deps there or the build fails.
- Generate a single component into an existing module: `php artisan module:make` (interactive) or `php artisan module:make {Module} {type} {Name}` — types: `model`, `migration`, `controller` (`--web` for web), `request` (`--fetch` to extend FetchRequest), `resource`, `service`, `repository`, `seeder`, `factory`, `command`, `job`, `event`, `listener` (`--event=` for the type-hint auto-discovery needs, `--queued` to extend Core's QueuedListener — see Events & listeners), `middleware`, `policy`, `observer`.
- `CoreServiceProvider` auto-loads each active module's `Database/Migrations`, `Views` (namespaced `view('blog::index')`), `Lang` (namespaced `__('blog::messages.key')`), and registers any artisan commands in the module's `Commands/` directory.
- Factories resolve per module: `App\Modules\Blog\Models\Post` → `App\Modules\Blog\Database\Factories\PostFactory` (via `newFactory()` on the Core base Model).

### Local packages (`app/Vendor`)

- Local (non-Packagist) composer packages live in `app/Vendor/{Name}` with their own `composer.json`, installed through the `path` repository (`app/Vendor/*`, symlinked) declared in the root `composer.json`.
- Package production code must not import `App\` classes; `module:boundaries` enforces this so packages remain reusable outside the host project.
- Convention: composer name `local/{kebab-name}`, PSR-4 namespace `Local\{Name}\` → `src/` (configurable via `project.vendor.*` config).
- Scaffold one: `php artisan make:package Payment`, then install: `composer require local/payment:"^1.0"`. Providers are auto-discovered via `extra.laravel.providers`.
- Inspect: `php artisan package:list` (shows each package's Source/Ref too).
- Packages that live in their own repository (global features like `Wallet`/`Blog`, reused across platforms) are pulled in and pushed out via `git subtree`: `vendor:install <repo-or-name> [--as=] [--ref=]`, `vendor:update <Name>`, `vendor:publish <Name> <repo> [--ref=]`, `vendor:remove <Name>` — see `app/Vendor/README.md`.
- First-party packages:
  - `local/data-response` (`app/Vendor/DataResponse`) — every JSON response in the app is built here, no exceptions. `Local\DataResponse\DataResponse::success()/error()` build the standard envelope (`{success, message, data|errors}`) directly; `Local\DataResponse\Concerns\BuildsDataResponses` is the trait that gives `successResponse()`/`failedResponse()` to any controller — Core's base `Controller` already uses it, and `Core\Exceptions\Handler` uses `error()` for validation/404/401/403/500. `DataResponse::raw($payload, $status)` is the escape hatch for responses that intentionally don't use the envelope (e.g. `HealthController`'s flat status shape) — it still funnels through the same class. Key names and default messages are configurable in `config/data_response.php` (publish tag `data-response-config`), so renaming the envelope is a config change, not a find-and-replace across controllers.
  - `local/media` (`app/Vendor/Media`) — polymorphic attachments. Add `Local\Media\Traits\HasMedia` to a model, then `$model->addMedia($uploadedFile, 'collection')`, `getFirstMediaUrl()`, `clearMedia()`. Config in `config/media.php` (publish tag `media-config`).
  - `local/geo-seeder` (`app/Vendor/GeoSeeder`) — country/city reference data for Egypt, Kuwait, UAE, KSA. Pure data (`src/Data/{ISO2}.php`) + `Local\GeoSeeder\GeoDataRepository` (`supported()`, `has()`, `country()`, `cities()`) — no models, no migrations, no artisan commands of its own; see the Country/City modules below for what consumes it. Which countries seed by default is `config/geo_seeder.php` → `countries`, driven by `GEO_SEED_COUNTRIES`.
  - `local/permission` (`app/Vendor/Permission`) — roles and permissions. Unlike the other packages, this one *does* own its tables/models (`Role`, `Permission`, polymorphic pivots), since roles/permissions are inherently data, not just reference config. `Local\Permission\Traits\HasRolesAndPermissions` is wired into `App\Modules\User\Models\User` (`$user->assignRole('admin')`, `$user->hasPermissionTo('posts.create')`). Middleware aliases `role:`, `permission:`, `role_or_permission:` are auto-registered. `php artisan permission:seed` syncs the merged declarative definitions — `config('permission.definitions')` plus every module's `Config/permissions.php` matched by `permission.definition_paths` (same plan-then-execute UX as `geo:seed`); `php artisan permission:list` inspects what's in the database; `php artisan user:make-admin` bootstraps the first admin (seeds + creates + grants in one step). The role→permission map is cached as one unit and auto-flushed on writes — see the package README for the guard_name design note (must not be nullable, or the uniqueness check silently stops working). **Per-tenant custom roles**: `roles.tenant_id` (nullable, indexed) plus a composite `unique(['tenant_id', 'name', 'guard_name'])` index let two tenants each own a role named e.g. `admin` without colliding, or with a same-named global role — `Role::findOrCreateForTenant($tenantId, 'admin')` / `Role::findByNameForTenant($tenantId, 'admin')` and `$user->assignRoleForTenant($tenantId, 'admin')` are the tenant-scoped counterparts to `findOrCreate()`/`findByName()`/`assignRole()`, which stay global-only (`tenant_id IS NULL`) and never resolve a tenant-scoped role by accident. Nothing infers a tenant from ambient request context automatically — a host app opts in explicitly, matching this package's host-agnostic, nothing-assumed-about-tenancy design. Permissions themselves stay global; only role-to-permission bundling is tenant-customizable.

### Routing (plain route files)

No route attributes, no directory scanning — routes are registered from ordinary files, so `grep`/`git diff`/`git blame` work on them like any other PHP. `CoreServiceProvider::loadModuleRouteFiles()` loads, for every active module (+ Core), whichever of these exist:
- `Routes/api.php` under the `api` middleware group.
- `Routes/web.php` under the `web` middleware group.
- `Routes/dashboard.php` under `project.routes.dashboard` config (prefix/middleware/name applied centrally — default prefix `dashboard`, middleware `['web', 'auth']`, name prefix `dashboard.`).

API files declare resource-relative prefixes and route names; Core applies the
global API prefix/version. Web files declare their own prefixes and names:
```php
// app/Modules/User/Routes/api.php
Route::prefix('users')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('api.users.index');
    // ...
});
```
`make:module` generates all three files automatically, matching whatever controllers it scaffolds — a new module works immediately without wiring anything by hand. `module:make ... controller` (single-component generator) can't safely append to an existing route file, so it prints the `Route::` line to add manually.

Controllers are plain classes with public action methods — no `#[Prefix]`/`#[Get]` attributes. (The project briefly supported `spatie/laravel-route-attributes` as an alternate registration mode; it was dropped in favor of a single, simpler mechanism.)

### Base class hierarchy

- Models: `App\Modules\Core\Models\Model` extends Eloquent, includes `HasUuid` trait, has `scopeForTenant()`.
- Repositories: `App\Modules\Core\Repositories\BaseRepository` provides standard CRUD.
- Services: `App\Modules\Core\Services\BaseService` wraps a repository.
- Controllers: `App\Modules\Core\Controllers\Controller` adds `successResponse()` and `failedResponse()` helpers (via the `local/data-response` package's `BuildsDataResponses` trait — see below). App-level `App\Http\Controllers\Controller` extends it.
- Resources: `App\Modules\Core\Resources\BaseResource` extends `JsonResource` for API response transformation.
- Requests: `App\Modules\Core\Requests\BaseRequest` (authorize defaults to true) — **all module requests extend it**. `App\Modules\Core\Requests\FetchRequest` extends it for listing endpoints.
- All modules follow: Controller → Service → Repository → Model, with Resources for API output.

### Fetch (listing) pipeline

- Index endpoints take a module request extending `FetchRequest` and call `$service->fetch($request)`.
- Global query keys validated by `FetchRequest`: `pagination` (bool, default true; `false` returns an unpaginated set, bounded by `project.pagination.unpaginated_cap` — default 1000 — so the toggle can never load an unbounded table), `per_page` (capped at `project.pagination.max_per_page`), `page`, `word` (free-text search), `sort_by`, `sort_dir` (`asc|desc`).
- `BaseRepository::fetch()` matches `word` with `LIKE` against the model's `protected array $searchable` columns and only sorts by columns in the model's `protected array $sortable` whitelist (default `id`, `created_at`, `updated_at`).
- Add module filters by overriding `rules()` in the module's fetch request: `return parent::rules() + ['status' => [...]];`.
- For filters that need to scope the query itself (not just word/sort), override `BaseRepository::baseQuery(FetchRequest $request): Builder` (default: `$this->query()`) — e.g. `CityRepository::baseQuery()` narrows to a `?country=EG` filter via `FetchCityRequest::countryCode()` before word search/sorting/pagination run on top.

### Events & listeners (auto-discovered per module)

- Listeners in each **active** module's `Listeners/` directory (plus Core's) are auto-discovered — no manual `Event::listen` or provider wiring anywhere. Wired in `bootstrap/app.php` via `->withEvents(discover: ...)`, which reads the module registry file directly (config isn't loaded yet at that point). Disabling a module removes its listeners.
- **Discovery keys off the type-hint** of `handle()`'s (or `__invoke()`'s) first parameter. An untyped `handle(object $event)` is never wired — this is why `module:make {M} listener {Name}` asks for the event (interactive) or takes `--event=`: an event name resolves to this module's `Events\{Name}`, a FQCN (e.g. `Illuminate\Auth\Events\Registered`) is used as-is. Generating without an event prints a warning and emits the untyped stub.
- Module events extend `App\Modules\Core\Events\DomainEvent` (`module:make {M} event {Name}` generates this): it implements `ShouldDispatchAfterCommit` (dispatch inside a DB transaction is held until commit, dropped on rollback — prevents queued listeners racing uncommitted data), carries `$eventId` (uuid) + `$occurredAt`, and uses `SerializesModels`. Subclasses with constructor promotion **must call `parent::__construct()`**. Dispatch with `OrderShipped::dispatch($order)`.
- Async listeners extend `App\Modules\Core\Listeners\QueuedListener` (`module:make {M} listener {Name} --event=X --queued`, or answer the interactive prompt): `ShouldQueue` with tries/backoff from `config('project.events')` and a queue **lane** from `QueuedListener::$lane` (default `'default'`; override to `'bulk'` or `'notifications'` in a subclass doing lower-priority or fan-out work, so it can't starve latency-sensitive listeners sharing a worker pool). Every lane resolves to **null (the connection's default queue)** out of the box — nothing changes until a lane's env var (`PROJECT_EVENTS_QUEUE_DEFAULT` / `_BULK` / `_NOTIFICATIONS`) is set to a real, distinct name. The shipped workers (`docker-compose` queue service, `composer dev`) already listen on all three lane names (`--queue=default,bulk,notifications`), so naming a lane is enough by itself — no worker redeploy needed.
- Cross-module decoupling: a module may listen to another module's (or the framework's) events without any `boundaries.allow` entry **only if** it doesn't import the other module's classes — type-hinting a framework event is always safe; type-hinting `App\Modules\X\Events\Y` from module Z is a dependency and must be declared in `project.boundaries`.
- `php artisan event:list` shows the discovered map (queued listeners are labeled `(ShouldQueue)`); `event:cache` caches it (already part of `deploy/deploy-dev.sh`) and `event:clear` resets. Verified: cached discovery includes module listeners.
- Living examples: `Auth\Listeners\AssignDefaultRole` (sync — role assignment must be atomic with registration; typed on Auth's `UserRegistered`, assigns `PROJECT_AUTH_DEFAULT_ROLE` via `local/permission`) and `User\Events\UserCreated` + `User\Listeners\RecordUserCreation` (async — a DomainEvent fired from the User model's `created` hook so it fires for every entry point, with a queued audit-log listener). Tests: `AssignDefaultRoleTest`, `UserCreatedEventTest`, `DomainEventTest`.

### Events vs. jobs — which to use

The base's messaging architecture is **event-driven over Laravel's queue** (the pattern general platforms actually run), with **jobs as the message-driven half** for directed work. A full broker (Kafka/RabbitMQ) is deliberately out of scope — Redis + the database queue are the transports, and the code wouldn't change shape if a broker were introduced later.

- **Event** (`Events/` + `Listeners/`): a *fact* that already happened, named in past tense (`UserCreated`, `OrderShipped`). The publisher must not care who reacts, how many listeners exist, or whether they fail independently. Fan-out, audit, notifications, cache invalidation, cross-module reactions.
- **Job** (`Jobs/`, `module:make {M} job {Name}`): a *command* — directed work with one owner and an expected outcome (`GenerateInvoicePdf`, `SyncCatalogToSearch`). The dispatcher knows exactly what should happen and typically cares about retries/failure of that specific work.
- Rule of thumb: if deleting the consumer should break the dispatcher, it's a job; if the dispatcher shouldn't even notice, it's an event.

### Auth module

- Full auth implementation under `app/Modules/Auth`, routes at `/api/v1/auth/*`: register, login, logout, me, password forgot/reset, email verification (signed URLs, route name `verification.verify`), TOTP 2FA (`App\Modules\Auth\Support\Totp`, dependency-free RFC 6238), and named personal access tokens.
- Driver via `PROJECT_AUTH_DRIVER` (default `sanctum`): `sanctum`/`token` issue Bearer tokens (lifetime `PROJECT_AUTH_TOKEN_EXPIRATION` minutes, wired to `sanctum.expiration`); `session` uses the web guard — it only works on routes with session state (the `web` group / `statefulApi`), not the plain `api` group, and regenerates the session id on login.
- Password policy is centralized via `Password::defaults()` in `AuthServiceProvider`: min 8 everywhere; production additionally requires mixed case + numbers and rejects pwned passwords (`uncompromised()`, HIBP range API — production only so tests/offline dev make no network calls). Register/reset requests use `Password::defaults()`, never inline `min:` rules.
- Every sub-feature is gated by its `PROJECT_FEATURE_*` flag and returns 403 when disabled. Login requires a `code` field when the user has confirmed 2FA.
- Protected endpoints use `auth:sanctum`. `two_factor_secret` is stored encrypted and hidden from serialization.
- New registrations optionally get a default role — set `PROJECT_AUTH_DEFAULT_ROLE` (see Events & listeners above).
- Password-reset tokens use Auth's UUID-bound database repository, never Laravel's email-keyed repository. This isolates same-email users across tenants; migration `2026_07_18_000001` invalidates legacy reset links while rebuilding that ephemeral table.

### Country / City modules

- Full CRUD API modules at `/api/v1/countries` and `/api/v1/cities`, seeded from the `local/geo-seeder` package (Egypt, Kuwait, UAE, KSA by default).
- `City belongsTo Country` / `Country hasMany City` — a genuinely bidirectional coupling, declared both ways in `config/project.php` → `boundaries.allow` (`'Country' => ['City'], 'City' => ['Country']`). Don't "fix" this into one direction; it's the real shape of the domain.
- Both models are global reference data, **not** tenant-scoped — they skip `HasTenantScope` on purpose (every tenant shares the same list).
- `country_id` in the API is always the country's public **uuid**, like every other identifier — `CityService::create()/update()` resolves it to the real internal id right before writing (`Country::where('uuid', ...)->value('id')`); `CityRepository::query()` eager-loads `country` so it's never an extra query.
- `php artisan geo:seed` (in `App\Modules\Country\Commands\SeedGeoDataCommand`) is the entry point: prints a table of exactly which countries will be seeded (and which requested codes have no shipped data, so they're skipped rather than fatal) before running `CountrySeeder` then `CitySeeder`. `--countries=EG,KW` overrides `config('geo_seeder.countries')` for one run; `--fresh` deletes existing rows for just those countries first. Both seeders `updateOrCreate` (by `iso2`, and by `[country_id, name]`), so re-running — with or without the command — is always safe.
- `FetchCityRequest` adds a `?country=EG` filter, implemented via `CityRepository::baseQuery()` (see Fetch pipeline above).

### Tenancy (three modes)

- Controlled by `PROJECT_TENANCY_MODE` (default `none`); unknown values behave as `none`. Read it via the helpers, never compare the raw config string: `tenancy_mode()` (normalized `none|single|multi`), `has_tenancy()`, `is_single_tenant()`, `is_multi_tenant()`, and `tenant_id()` for the current tenant.
  - **`none`** — no tenancy: no tenant column, no scoping, no resolution. `TenantMiddleware` is a pass-through.
  - **`single`** — one implicit tenant: every request runs under the **default tenant** (`project.tenancy.default_tenant`, env `PROJECT_TENANT_DEFAULT_NAME`/`_SLUG`), created on first use — no seeding step. The tenant column exists and rows are stamped, so flipping to `multi` later is a config change, not a data migration. Deactivating the default tenant (`is_active = false`) stops serving requests (400) — the tenant-level kill switch, same as multi mode.
  - **`multi`** — full multi-tenancy: `TenantMiddleware` resolves the tenant from subdomain, `X-Tenant-ID` header, or URL path segment per `PROJECT_TENANT_IDENTIFICATION`, and aborts 400 when unidentifiable. Paths in `project.tenancy.exempt_paths` (default: `api/health`) skip tenant resolution so probes/monitors keep working tenant-less.
- `TenantMiddleware` is always registered on both the `api` and `web` groups; the mode is honored at request time, so flipping modes never requires re-wiring. The tenant model is `project.tenancy.tenant_model` (default `App\Models\Tenant`).
- **Resolution caching**: identifier → tenant id lookups go through `Core\Support\TenantCache` (`project.tenancy.cache`, on by default, TTL `PROJECT_TENANCY_CACHE_TTL`), so steady-state requests never query the tenants table. Only successful resolutions are cached; `App\Models\Tenant` flushes its own entries (old + new slug/subdomain) from `saved`/`deleted` hooks, so deactivating or renaming a tenant takes effect on the next request, not at TTL expiry. A custom `tenant_model` must call `TenantCache::forgetTenant()` from the same hooks.
- Models opt in with the `HasTenantScope` trait: active in `single` and `multi` (no-op in `none`), it narrows queries to the Context tenant and stamps the tenant column on create. The `tenants` table itself is created in **every** mode (migration `0001_01_01_000000`) so a later mode flip finds it in place.
- **Strict mode (fail closed, default on)**: with tenancy active, using a tenant-scoped model while no tenant is in Context throws `MissingTenantContextException`. The active tenant is authoritative on creates; a conflicting preset tenant throws `TenantContextMismatchException`. Artisan commands, seeders, scheduled tasks, and tinker must choose explicitly: `with_tenant($tenantId, fn () => ...)` for one tenant, or `without_tenant_scope(fn () => ...)` for deliberate cross-tenant maintenance. `PROJECT_TENANCY_STRICT=false` restores legacy fail-open reads/creates and should not be used in production.

**Tenant-aware migrations** — one migration file, correct schema in all modes:

- Create-table migrations call `$table->tenantColumn();` (a Blueprint macro registered by CoreServiceProvider; `module:make {M} migration create_x_table` generates it). When tenancy is active (`single`/`multi`) it adds the configured tenant column as a nullable, indexed foreign key with restricted deletion; in `none` mode it's a **no-op** — nothing to comment in/out per project.
- Switching an existing project from `none` to `single`/`multi`: run `tenant:migrations`, review and run `migrate`, assign legacy rows with `tenant:backfill`, then require `tenant:check` to pass. `tenant:migrations` discovers every non-abstract model using `HasTenantScope`, generates missing columns, and converts indexes declared by `tenantUniqueColumns()` from global to tenant-composite uniqueness. `--module=Name` (repeatable) narrows all three commands.
- The generated catch-up migration adds column + index only, **no foreign key** on purpose — adding an FK to an existing table isn't portable across drivers (SQLite in particular); add one in a separate migration if the project needs it.
- Verified lifecycle: create table in `none` mode (no column) → flip to `single`/`multi` → `tenant:migrations` + `migrate` + `tenant:backfill` + `tenant:check` → tables created afterwards get the indexed, restricted tenant foreign key at create time via the macro. Tenants are soft-deleted.

### Project config

All project-specific config lives in `config/project.php`, read via `project_config('key')` helper or `config('project.key')`. Features like registration, email verification, 2FA, personal access tokens are toggled via env vars prefixed `PROJECT_FEATURE_*`.

### Routing conventions

- Module API files use a resource-relative prefix (e.g. `users`); Core centrally applies `PROJECT_API_PREFIX` + `PROJECT_API_VERSION` (default final URL: `api/v1/users`). `PROJECT_API_ENABLED=false` removes business API routes while preserving health probes.
- **Auth by default**: `make:module` generates API routes behind `auth:sanctum` — open up individual routes deliberately, not the other way around. Shipped modules follow the same posture: `/api/v1/users` requires auth on every endpoint (PII); Country/City reads are public reference data, writes require auth.
- **Authorization, not just authentication**: `auth:sanctum` only proves *who*, not *what they may do* — shipped write/mutate routes additionally require a `permission:` check (`local/permission`, see below), e.g. `/api/v1/users` requires `users.view`/`users.create`/`users.update`/`users.delete` per action, Country/City writes require `countries.manage`/`cities.manage`. **Definitions are module-owned**: each module declares the permissions of the resource it ships in its own `Config/permissions.php` (returning `['permissions' => [], 'roles' => []]`), discovered via `config/permission.php` → `definition_paths` (glob, default `app/Modules/*/Config/permissions.php`); the central `definitions` array keeps roles and cross-cutting permissions. `permission:seed` merges every source (same-named roles union their lists; a role's `'*'` means "every permission from every source"). **Bootstrapping a fresh install**: `php artisan user:make-admin you@example.com` does the whole thing — seeds the definitions if the role isn't in the DB yet, creates the user if the email is unknown (interactive, or `--name=`/`--password=` for scripting), and grants `admin` (`--role=` for another role; roles must come from the definitions, it refuses to grant an undefined one). **Generated modules are authorized by default, not just authenticated**: `make:module` emits a populated `Config/permissions.php` (`{resource}.view/.create/.update/.delete`) *and* wires the matching `permission:` middleware onto every generated API and web route, so a new module can never silently ship CRUD that is open to every logged-in user. Until you run `permission:seed` and grant a role, those endpoints correctly 403 for everyone (fail closed). Coarsen the split (e.g. one `{resource}.manage` for writes) by editing both the config file and the route middleware together. `app/Modules/Core/Tests/Feature/MakeModuleCommandTest.php` pins this posture — it generates a module and asserts the routes are permission-gated, the permissions are declared, and the generated files are valid PHP.
- **Email verification posture**: verification emails (when `PROJECT_FEATURE_EMAIL_VERIFICATION` is on) are informational by default — no shipped route requires a verified email. Routes that should enforce it carry the `verified.feature` middleware (Auth module alias): a pass-through while the flag is off, 403 for unverified users while it's on — annotate once, flip enforcement globally with the flag.
- **Rate limiting**: every `api`-group route is throttled by `RateLimiter::for('api')` (registered in CoreServiceProvider), reading `project.api.rate_limit` (`PROJECT_API_RATE_LIMIT`, default 60 req/min, keyed per user id or IP). Routes with their own `throttle:` middleware (Auth's login/register) are limited by both.
- Module web routes use prefix `{resource}`; `make:module` generates them behind `auth` (same posture as API routes — open up deliberately). Dashboard routes use `{dashboard_prefix}/{resource}`, authenticated centrally — but authentication is not authorization: gate dashboard actions with `permission:` middleware too. The User module ships **no** web/dashboard UI (PII stays API-only until a project adds a protected backoffice — the route files show the pattern); guests on `auth` web routes are redirected to `/` (bootstrap `redirectGuestsTo` — no named `login` route exists by default).
- Global `routes/api.php` and `routes/web.php` stay empty — all module routes come from each module's own `Routes/*.php` files, loaded by `CoreServiceProvider`.

## Static analysis

- PHPStan + Larastan at **level 5**, config in `phpstan.neon`, run with `composer analyse` / `make analyse`; **runs in CI** alongside Pint and must stay at zero errors.
- Analyses `app`, `config`, `database`, `routes` (including module and package tests — they are contract surface too). Local package `config/` dirs are excluded: they are declarative arrays pulled in via `mergeConfigFrom()`, which Laravel skips when config is cached, so `env()` in them is as safe as in the root `config/`.
- `treatPhpDocTypesAsCertain: false` — several framework methods are annotated non-nullable but genuinely return null at runtime (Sanctum's `currentAccessToken()`, `Seeder::$command`); trusting those PHPDocs would push us to delete correct null handling.
- Conventions that keep it at zero: models carry `@property` docblocks; API resources carry `@mixin {Model}`; relation methods declare generics (`@return MorphToMany<Role, $this>`); traits meant for models declare `@phpstan-require-extends Model`.
- `BaseRepository` is deliberately **not** generic — Eloquent's `static`-returning builders don't resolve through a PHPStan template, so `@template TModel` would only produce unverifiable annotations. A repository needing a narrower type queries its own model class directly (see `UserRepository::findByEmail`).

## Testing

- PHPUnit 12, run with `php artisan test` (or `composer test` which clears config first).
- Tests use SQLite in-memory database (configured in `phpunit.xml`).
- Test suites (`phpunit.xml`): `Unit` (`tests/Unit`), `Feature` (`tests/Feature`), `Modules` (`app/Modules/*/Tests` — every module carries its own tests), `Packages` (`app/Vendor/*/tests`). All run together with `php artisan test`; run one with `--testsuite=Modules`.
- Module tests live in `app/Modules/{Module}/Tests/{Feature,Unit}` and extend `Tests\TestCase`. Feature stubs skip themselves when the module is disabled in the registry.
- Generate: `php artisan module:make {Module} test {Name}` (`--unit` for a unit test). When a matching API controller + model exist, the generator emits a full CRUD smoke test (index/store/show/update/destroy against the module's endpoints). `make:module`'s wizard defaults its "extras" multiselect to include `test`, generating `{Module}ApiTest` automatically.
- Reference example: `app/Modules/User/Tests/Feature/UserApiTest.php` covers CRUD, uuid exposure, and the fetch pipeline (word/pagination/per_page cap/sort whitelist).
- Isolated unit tests: services use constructor injection specifically so their repository can be swapped for a mock without booting a database — `app/Modules/User/Tests/Unit/UserServiceTest.php` is the reference example (`$this->mock(UserRepository::class, ...)`, resolve the service from the container, assert delegation). Reach for this shape — not a Feature test — when what's under test is the service's own logic rather than the request/response/DB round trip.

## Docker

The project includes a full Docker setup for containerized development and deployment.

### Quick start

```bash
cp .env.example .env          # configure DB/Redis credentials
make setup                     # build images, install deps, migrate
make dev                       # start all services + Vite HMR
```

### Services

| Service | Container | Description | Port |
|---------|-----------|-------------|------|
| `app` | `proj-base-app` | PHP 8.3-FPM (Laravel) | 9000 (internal) |
| `nginx` | `proj-base-nginx` | Web server, proxies to app | `APP_PORT` (default 80) |
| `mysql` | `proj-base-mysql` | MySQL 8.4 database | `FORWARD_DB_PORT` (default 3306) |
| `redis` | `proj-base-redis` | Redis 7 (cache/session/queue) | `REDIS_PORT` (default 6379) |
| `queue` | `proj-base-queue` | Queue worker (`queue:work`) | — |
| `scheduler` | `proj-base-scheduler` | Cron (`schedule:run` every minute) | — |
| `vite` | `proj-base-vite` | Vite dev server with HMR (dev profile) | `VITE_PORT` (default 5173) |
| `mailpit` | `proj-base-mailpit` | Email testing UI + SMTP (dev profile) | `MAILPIT_PORT` (default 8025) |

### Database driver mapping

`DB_CONNECTION` in `.env` controls which database driver and service to use:

| `DB_CONNECTION` | Compose service | Notes |
|---------------------|-----------------|-------|
| `mysql` (default) | `mysql` | MySQL 8.4, active by default |
| `pgsql` | `pgsql` | Uncomment in `docker-compose.yml`, update `DB_HOST=pgsql` |
| `sqlite` | — | No DB container needed, uses file-based SQLite |

### Volume strategy

- **Bind mount** (`.:/var/www/html`) — live code sync for development.
- **Named volumes** (`vendor`, `node_modules`) — avoid host ↔ container conflicts for dependencies.
- **Persistent data** (`mysql_data`, `redis_data`) — survives `docker compose down`; destroyed with `make fresh` (`-v` flag).

### Environment

- All `PROJECT_*` env vars are forwarded from `.env` to the containers.
- Docker defaults to `DB_HOST=mysql` and `REDIS_HOST=redis`; both can be overridden from `.env`.
- `.env.example` includes commented Docker overrides — uncomment when switching to Docker.
- Use `docker-compose.override.yml` (gitignored) for local-only tweaks.

### Xdebug

Disabled by default. Enable at build time:
```bash
INSTALL_XDEBUG=true make rebuild
```

## Health Check

- `GET /api/health` — returns structured JSON with DB, Cache, Redis, and Queue status.
- Returns `200` when all checks pass (`healthy`), `503` when any check fails (`degraded`).
- Response includes version from `config('project.version')`, per-check latency, and driver info.
- Implemented as `App\Modules\Core\Controllers\Api\HealthController` (route: `app/Modules/Core/Routes/api.php`).
- Deliberately not the success/message/data envelope — built via `DataResponse::raw()` instead of `successResponse()` so tooling that expects a flat shape (uptime monitors, k8s probes) keeps working.

## CI/CD

GitHub Actions (`.github/workflows/ci.yml`) and a GitLab mirror (`.gitlab-ci.yml`) with the same stages. Runs on push/PR to `main` and `develop` (plus manual `workflow_dispatch`).

- **Syntax job** (fast fail): `php -l` over app/config/database/routes/tests + `composer validate` — catches parse errors before installing anything.
- **PHP job**: Pint lint, boot smoke check (`route:list` + `about` — catches urgent runtime errors), a **MySQL migration guard** (`migrate:fresh` against the job's MySQL service in `none` and `multi` tenancy modes — catches schema portability bugs SQLite tolerates, e.g. FK ordering), then PHPUnit tests on PHP 8.3 & 8.4 (SQLite in-memory) with a **coverage floor** (`--coverage --min=75`, line coverage; measured baseline at introduction was 79.7% — ratchet the number up as real coverage improves, don't lower it to make a low-coverage PR pass). The HTML/Clover report is uploaded as a build artifact (`coverage-report`, PHP 8.3 only) so a failing PR can see exactly which classes are dragging the number down. The GitLab mirror runs the same guard as a separate `mysql-migrations` job.
- **Frontend job**: `npm ci --ignore-scripts` + `npm run build` + `npm audit` (Node 22).
- **Security job**: `composer audit` for known vulnerability scanning.
- **Container job**: builds and smoke-tests the production Docker image. Version tags publish immutable images to GHCR/GitLab Container Registry.
- **Deploy job** (`deploy-dev`): after all checks pass on a `develop` push (or manual dispatch), SSHes into the dev server and runs `deploy/deploy-dev.sh` there.

### Dev server deployment

- Remote steps live in `deploy/deploy-dev.sh` — a CONFIGURATION block at the top (path, branch, php/composer binaries, toggles for migrations/assets/queue restart/maintenance mode) is meant to be customized per server. It can also be run manually: `ssh user@server 'bash -s' < deploy/deploy-dev.sh`.
- Connection settings come from repo secrets (GitHub: Settings → Secrets → Actions; GitLab: Settings → CI/CD → Variables): `DEV_SSH_PRIVATE_KEY` (required), `DEV_SSH_HOST` (required), `DEV_SSH_USER` (required), `DEV_SSH_PORT` (default 22), `DEV_DEPLOY_PATH`, `DEV_SSH_KNOWN_HOSTS` (optional host-key pinning).
- The public half of `DEV_SSH_PRIVATE_KEY` must be in the dev server's `~/.ssh/authorized_keys`. Until the secrets are configured, the deploy job exits with a notice and CI stays green.
- The deploy script runs `config:cache`, `route:cache`, `view:cache`, and `event:cache`. Routes must stay cacheable (no closure routes — use `Route::view` or controllers); CI's boot smoke check enforces this.
- `deploy-dev.sh` is single-host and mutable (`git reset --hard` on the server) — the right shape for a dev environment, the wrong shape for anything that needs more than one replica. For a real production rollout, see `deploy/k8s/` — reference Kubernetes manifests (Deployment + nginx sidecar, HPA, a separate queue worker Deployment, a scheduler CronJob, a migration Job) built on the same image this CI already produces. Same framing as this file: a pattern to adapt, not a turnkey deployment — see `deploy/k8s/README.md`.

## Important gotchas

- `.npmrc` sets `ignore-scripts=true` — npm won't run post-install lifecycle scripts. When adding npm packages that need post-install scripts, this may cause issues.
- Tests need the `pdo_sqlite` PHP extension (in-memory SQLite). The official Docker PHP images ship it; if the host PHP lacks it, run tests via Docker (`make test`) or install `php8.3-sqlite3`.
- `config:clear` is run before tests; never rely on cached config in test environments.
- The `Model` base class has `$guarded = ['id', 'uuid']` — all other attributes are mass-assignable, but `id` and `uuid` are guarded.
- Primary keys are auto-increment integers (internal only). `HasUuid` populates a separate `uuid` column on create — that uuid is the **public** identifier: API resources expose it as `id`, `getRouteKeyName()` binds routes to it, and `BaseRepository::find()/findOrFail()` transparently look up by uuid when given a uuid string.
- **Never re-declare `use HasFactory` in a module model.** `App\Modules\Core\Models\Model` already uses it and overrides `newFactory()` to resolve `App\Modules\{X}\Database\Factories\{Model}Factory`. A trait used directly in a subclass takes precedence over the same trait's method inherited (already-overridden) from the parent — so redeclaring `HasFactory` in, say, `User` silently un-overrides factory resolution and `User::factory()` falls back to Laravel's default (wrong) namespace guess. `App\Modules\User\Models\User` documents this inline; it's what `App\Modules\User\Database\Factories\UserFactory` needed a fix for.
