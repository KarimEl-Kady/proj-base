# Proj-Base

A modular HMVC application foundation built on Laravel, designed to serve as a starting point for any platform — web, API, or hybrid. Batteries included: module generators, a standard API fetch pipeline, local composer packages, per-module tests, Docker, and CI/CD with dev-server deployment.

## Stack

- **PHP** 8.3+ · **Laravel** 13.7 · **Sanctum** 4
- **Vite** 8 · **Tailwind CSS** 4
- **SQLite** (dev) / MySQL 8.4 / PostgreSQL (production)
- **Docker** (PHP-FPM, Nginx, MySQL, Redis, queue, scheduler, Mailpit, Vite HMR)

## Quick Start

```bash
git clone git@github.com:KarimEl-Kady/proj-base.git
cd proj-base
composer setup     # install, .env, key, migrate, npm build
composer dev       # server + queue + logs + vite
```

Or with Docker:

```bash
cp .env.example .env
make setup         # build images, install deps, migrate
make dev           # all services + Vite HMR
```

## Architecture

**HMVC** modules under `app/Modules/`, each self-contained:

```
app/Modules/{Module}/
├── Controllers/
│   ├── Api/            # JSON endpoints
│   └── Web/            # Blade views
├── Commands/           # Artisan commands (auto-registered)
├── Database/
│   ├── Factories/      # Auto-resolved per module
│   ├── Migrations/     # Auto-loaded
│   └── Seeders/
├── Events/             # Plain event classes
├── Lang/               # Auto-loaded, namespaced: __('blog::key')
├── Listeners/          # Auto-discovered (typed handle() = wired, no registration)
├── Models/
├── Providers/
├── Repositories/
├── Requests/           # Extend Core BaseRequest / FetchRequest
├── Resources/
├── Routes/
│   ├── api.php         # Loaded under "api" middleware
│   ├── web.php         # Loaded under "web" middleware
│   └── dashboard.php   # Loaded under project.routes.dashboard (prefix/middleware)
├── Services/
├── Tests/
│   ├── Feature/        # Runs with the project suite (Modules testsuite)
│   └── Unit/
└── Views/              # Auto-loaded, namespaced: view('blog::index')
```

**Request flow:** Controller → Service → Repository → Model, with Resources for API serialization.

### Key features

| Feature | Details |
|---------|---------|
| **Module registry** | `config/project_modules.php` is the single source of truth (`'Blog' => true`) — managed via artisan or edited by hand |
| **Interactive generators** | Every `make:*` / `module:*` command runs a prompt-driven wizard when called without arguments |
| **Routing** | Plain `Routes/{api,web,dashboard}.php` per module — no attributes, no scanning, just `Route::` calls you can grep and diff |
| **Fetch pipeline** | Standard listing keys on every index endpoint: `?word=`, `?pagination=false`, `?per_page=`, `?sort_by=`, `?sort_dir=` — validated by `FetchRequest`, executed by `BaseRepository::fetch()` |
| **Event-driven messaging** | `DomainEvent` base (after-commit dispatch, uuid + timestamp) fanned out to auto-discovered module listeners; `QueuedListener` base for async handlers over the queue — `event:cache`-compatible, disabled modules excluded |
| **Local packages** | `app/Vendor/{Name}` composer path packages (`local/media`, `local/data-response`, `local/geo-seeder`, `local/permission`) — scaffold with `make:package`, install with `composer require local/name:"*"` |
| **Roles & permissions** | `local/permission` package — `$user->assignRole('admin')`, `$user->hasPermissionTo('posts.create')`, `role:`/`permission:` route middleware, cached, config-driven via `php artisan permission:seed` |
| **Public UUIDs** | Auto-increment integer PKs internally; a `uuid` column is the public identifier (API `id`, route binding, repository lookup) |
| **Uniform API envelope** | `local/data-response` package builds every success/error response — `{success, message, data/errors}`, including validation, 404, 401, 403, 500, with renameable keys |
| **Full Auth module** | Register/login/logout/me (Sanctum bearer tokens or session), email verification, password reset, TOTP 2FA, named API tokens — every part gated by feature flags |
| **Module boundaries** | `php artisan module:boundaries` (runs in CI) fails on undeclared cross-module dependencies — declared in `config/project.php` |
| **Country/City reference data** | `Country` and `City` modules (full CRUD API) + `local/geo-seeder` package with data for Egypt, Kuwait, UAE, KSA — `php artisan geo:seed` |
| **Multi-tenancy** | Toggle `PROJECT_TENANCY_MODE` — subdomain, header, or path-based tenant resolution. Migrations adapt automatically: `$table->tenantColumn()` adds the tenant column only in multi mode, and `php artisan tenant:migrations` generates catch-up migrations when a single-tenant project goes multi |
| **Feature flags** | Registration, email verification, 2FA, API tokens — togglable via env and actually implemented by the Auth module |

## Generators

`make:module` is pure question-and-answer (no arguments/flags); the single-component generator below is interactive when called with no arguments, and scriptable with them:

```bash
# New module — wizard asks: name, API/Web/both, extras, enable now?
php artisan make:module

# Single component into an existing module
php artisan module:make                       # interactive
php artisan module:make Blog model Post --fillable=title,slug,body
php artisan module:make Blog request FetchPosts --fetch
php artisan module:make Blog test Post        # full API CRUD test
php artisan module:make Blog event PostPublished
php artisan module:make Blog listener NotifySubscribers --event=PostPublished
# types: model, migration, controller, request, resource, service,
#        repository, seeder, factory, command, job, event, listener,
#        middleware, policy, observer, test

# Module lifecycle
php artisan module:list
php artisan module:enable Blog                # or omit the name to pick from a list
php artisan module:disable Blog
php artisan module:delete Blog
php artisan module:boundaries                 # verify cross-module dependencies
php artisan tenant:migrations                 # single→multi: generate missing tenant-column migrations

# Local packages (app/Vendor)
php artisan make:package Payment
composer require local/payment:"*"
php artisan package:list

# Project overview
php artisan project:info
```

### Fetch pipeline example

```
GET /api/v1/users?word=alice&per_page=10&sort_by=id&sort_dir=asc
GET /api/v1/users?pagination=false
```

`word` searches the model's `$searchable` columns; `sort_by` is validated against the model's `$sortable` whitelist; `per_page` is capped by `project.pagination.max_per_page`. Extend `FetchRequest` per module to add custom filters.

### Routing

Every module registers routes through plain files, loaded automatically by `CoreServiceProvider` — no attributes, no directory scanning:

| File | Middleware | Prefix / name |
|------|-----------|----------------|
| `Routes/api.php` | `api` | whatever the file declares (e.g. `api/v1/users`) |
| `Routes/web.php` | `web` | whatever the file declares |
| `Routes/dashboard.php` | `web` + config | centrally applied from `project.routes.dashboard` (`prefix`, `middleware`, `name_prefix` — defaults to `dashboard`, `['web','auth']`, `dashboard.`) |

```php
// app/Modules/Blog/Routes/api.php
Route::prefix('api/v1/posts')->group(function () {
    Route::get('/', [PostController::class, 'index'])->name('api.posts.index');
    // ...
});
```

`make:module` generates all three files (matching the controllers it scaffolds) so a new module works immediately. `module:make controller` (single-component) prints the `Route::` line to add by hand, since it can't safely append to an existing file.

### Events & listeners (event-driven messaging)

The base's async architecture is **event-driven over the queue** — events are *facts* (`UserCreated`) fanned out to any number of listeners, jobs are *commands* (`GenerateInvoicePdf`) with one owner. Rule of thumb: if deleting the consumer should break the dispatcher, it's a job; if the dispatcher shouldn't even notice, it's an event.

Every **active** module's `Listeners/` directory is auto-discovered (wired in `bootstrap/app.php` from the module registry) — no `Event::listen`, no provider wiring. Discovery keys off the type-hint of `handle()`'s first parameter, which is why the listener generator asks for the event:

```bash
php artisan module:make Blog event PostPublished
php artisan module:make Blog listener NotifySubscribers --event=PostPublished --queued
php artisan module:make Blog listener AuditLogin --event='Illuminate\Auth\Events\Registered'
```

```php
// Generated event — extends Core's DomainEvent:
//   dispatched only after the surrounding DB transaction commits
//   (no queued listener ever sees uncommitted data), and carries
//   $eventId (uuid) + $occurredAt for audit trails/deduplication.
class PostPublished extends DomainEvent { ... }

// Generated with --queued — extends Core's QueuedListener:
//   ShouldQueue with shared tries/backoff from config('project.events').
class NotifySubscribers extends QueuedListener
{
    public function handle(PostPublished $event): void { ... }
}
```

Dispatch with `PostPublished::dispatch($post)`. `php artisan event:list` shows the discovered map (queued listeners labeled `ShouldQueue`), `event:cache` caches it (already in the deploy script), and disabling a module removes its listeners automatically.

Living examples: `Auth\Listeners\AssignDefaultRole` (sync — assigns `PROJECT_AUTH_DEFAULT_ROLE` to new registrations, atomic on purpose) and `User\Events\UserCreated` + `User\Listeners\RecordUserCreation` (async — fired from the model's `created` hook for every entry point, queued audit-log listener).

### Authentication (Auth module)

Token-based (Sanctum) when `PROJECT_AUTH_DRIVER=sanctum|token`, session when `session`. All endpoints under `/api/v1/auth`:

| Endpoint | Description |
|----------|-------------|
| `POST /register`, `POST /login`, `POST /logout`, `GET /me` | Core flow — login returns a Bearer token with `PROJECT_AUTH_TOKEN_EXPIRATION` lifetime |
| `POST /password/forgot`, `POST /password/reset` | Password reset via email (non-enumerating) |
| `POST /email/resend`, `GET /email/verify/{id}/{hash}` | Email verification (signed URLs) — `PROJECT_FEATURE_EMAIL_VERIFICATION` |
| `POST /2fa/enable`, `/2fa/confirm`, `/2fa/disable` | TOTP 2FA (Google Authenticator-compatible, dependency-free) — `PROJECT_FEATURE_2FA`; login then requires `code` |
| `GET/POST/DELETE /tokens` | Named personal access tokens for integrations — `PROJECT_FEATURE_API_TOKENS` |

### Local packages

Non-Packagist packages live in `app/Vendor/{Name}` with their own `composer.json`, installed through a composer `path` repository (symlinked) and auto-discovered by Laravel. Four first-party examples ship with the base:

**`local/data-response`** — every JSON response in the app is built here. `App\Modules\Core\Controllers\Controller` already includes its trait, so `successResponse()`/`failedResponse()` work everywhere for free; the exception handler uses it too, so validation/404/401/403/500 all share the same shape:

```php
use Local\DataResponse\DataResponse;

DataResponse::success($user, 'User created.', 201);
DataResponse::error('Not found.', 404);
DataResponse::raw(['status' => 'healthy', 'checks' => [...]]); // escape hatch for non-envelope shapes
```

Rename the envelope keys or default messages project-wide via `config/data_response.php` (publish with `--tag=data-response-config`) — no controller changes needed.

**`local/media`** — polymorphic attachments:

```php
use Local\Media\Traits\HasMedia;

class Post extends Model { use HasMedia; }

$post->addMedia($request->file('cover'), 'covers');
$post->getFirstMediaUrl('covers');
$post->clearMedia('covers');
```

**`local/geo-seeder`** — country/city reference data (name, ISO2/ISO3, phone code, currency, flag, timezone; cities with coordinates) for **Egypt, Kuwait, UAE, and KSA**. Pure data + a read-only repository — no models, no migrations of its own; the `Country`/`City` modules own the tables and seed *from* this package:

```php
use Local\GeoSeeder\GeoDataRepository;

app(GeoDataRepository::class)->supported();   // ['AE', 'EG', 'KW', 'SA']
app(GeoDataRepository::class)->country('EG'); // ['name' => 'Egypt', 'iso2' => 'EG', ...]
```

Which countries get seeded is one setting — `GEO_SEED_COUNTRIES=EG,KW,AE,SA` — read by both seeders and by a dedicated command that reports its plan before touching the database:

```bash
php artisan geo:seed                    # seeds config('geo_seeder.countries')
php artisan geo:seed --countries=EG,KW  # override for this run
php artisan geo:seed --fresh            # wipe + reseed just these countries
```

```
Planning to seed:
+------+---------+----------------------------+--------+
| Code | Country | Data                       | Cities |
+------+---------+----------------------------+--------+
| EG   | Egypt   | ok                         | 12     |
| FR   | —       | no data — will be skipped | —      |
+------+---------+----------------------------+--------+
```

Add a country by dropping `app/Vendor/GeoSeeder/src/Data/{ISO2}.php` (same shape as the existing files) and adding its code to `GEO_SEED_COUNTRIES` — no other changes.

**`local/permission`** — roles and permissions, database-backed and cached. Unlike the other three packages it does own its own tables (`Role`, `Permission`, and three pivot tables) — roles/permissions are inherently data, not just reference config. Already wired into `App\Modules\User\Models\User`:

```php
use Local\Permission\Traits\HasRolesAndPermissions;

class User extends Model { use HasRolesAndPermissions; }

$user->assignRole('admin');                          // creates the role if missing
$user->hasPermissionTo('posts.create');               // direct grant OR via any role
$user->hasAnyRole('admin', 'manager');
```

Route middleware is auto-registered — `role:admin`, `permission:posts.create` (pipe-separate for "any of"), and `role_or_permission:admin|posts.create`. A failed check throws `UnauthorizedException` (extends Laravel's `AuthorizationException`), so it renders as a normal 403 through the existing exception handler — no extra wiring.

Which roles/permissions should exist is declarative, and **module-owned**: each module declares the permissions of the resource it ships in its own `Config/permissions.php`, and the central `config/permission.php` keeps the roles plus anything cross-cutting. `permission:seed` merges every source:

```php
// app/Modules/Blog/Config/permissions.php — owned by the module
return [
    'permissions' => ['posts.view', 'posts.manage'],
];

// config/permission.php — roles + cross-cutting permissions
'definitions' => [
    'permissions' => [],               // module-owned ones live in the modules
    'roles' => [
        'admin' => ['*'],              // every permission from every source
        'manager' => ['users.view'],
    ],
],
'definition_paths' => [
    'app/Modules/*/Config/permissions.php',   // how module files are discovered
],
```

```bash
php artisan permission:seed              # reports sources + the plan, then creates/syncs
php artisan permission:seed --fresh      # also wipes existing roles/permissions (confirms first)
php artisan permission:list              # what's actually in the database
```

The role → permission map is cached as a single unit (it's small, read on every check) and auto-flushed whenever a role's permissions change — no manual cache-busting anywhere.

**Bootstrapping the first admin** is one command — it seeds the definitions if needed, creates the user if the email is unknown (interactive, or `--name=`/`--password=` for scripting), and grants the role:

```bash
php artisan user:make-admin you@example.com
```

## Configuration

Project settings live in `config/project.php` (`PROJECT_*` env vars); active modules in `config/project_modules.php`:

| Setting | Default | Description |
|---------|---------|-------------|
| `config/project_modules.php` | `['User' => true]` | Module registry (file, not env) |
| `PROJECT_TENANCY_MODE` | `single` | `single` or `multi` |
| `PROJECT_PLATFORM` | `web` | `web`, `api`, or `hybrid` |
| `PROJECT_DASHBOARD_PREFIX` | `dashboard` | URL prefix for every module's `Routes/dashboard.php` |
| `PROJECT_AUTH_DEFAULT_ROLE` | *(unset)* | Role auto-assigned to new registrations by Auth's `AssignDefaultRole` listener |
| `PROJECT_EVENTS_QUEUE` / `PROJECT_EVENTS_TRIES` | *(default queue)* / `3` | Queue + retries for listeners extending `QueuedListener` |
| `PROJECT_DB_DRIVER` | `mysql` | Database driver |
| `PROJECT_PAGINATION_PER_PAGE` | `15` | Default page size |
| `PROJECT_PAGINATION_MAX_PER_PAGE` | `100` | Hard cap for `?per_page=` |
| `MEDIA_DISK` / `MEDIA_MAX_FILE_SIZE` | `public` / `10240` KB | Media package |
| `GEO_SEED_COUNTRIES` | `EG,KW,AE,SA` | ISO2 codes the `geo:seed` command / seeders act on |
| `PERMISSION_CACHE_ENABLED` / `PERMISSION_CACHE_TTL` | `true` / `3600` | Permission package's role→permission map cache |

See `.env.example` for all options.

## Testing

```bash
composer test                          # config:clear + full suite
php artisan test --testsuite=Modules   # only module tests
php artisan test --filter=UserApiTest  # single class
```

Four suites run together: `Unit`, `Feature`, `Modules` (`app/Modules/*/Tests`), and `Packages` (`app/Vendor/*/tests`). Every module carries its own tests; generated feature tests skip themselves when their module is disabled. `make:module` generates an API CRUD smoke test by default.

> Tests use in-memory SQLite — the host PHP needs `pdo_sqlite` (`php8.3-sqlite3`), or run them via Docker (`make test`).

## CI/CD

GitHub Actions (`.github/workflows/ci.yml`) and a GitLab mirror (`.gitlab-ci.yml`):

1. **Syntax** — `php -l` over the whole codebase + `composer validate` (fast fail)
2. **Lint + Test** — Pint, boot smoke check (`route:list`, `route:cache` guard, `module:boundaries`), PHPUnit on PHP 8.3/8.4
3. **Frontend** — `npm ci` + Vite build · **Security** — `composer audit`
4. **Deploy → dev server** — on `develop` pushes (after all checks pass), SSHes in and runs `deploy/deploy-dev.sh`

Deployment is configured with repo secrets — `DEV_SSH_PRIVATE_KEY`, `DEV_SSH_HOST`, `DEV_SSH_USER` (+ optional `DEV_SSH_PORT`, `DEV_DEPLOY_PATH`, `DEV_SSH_KNOWN_HOSTS`). Until they're set, the deploy job skips with a notice. Server-side behaviour (path, branch, migrations, asset build, queue restart, maintenance mode) is customized in the CONFIGURATION block of `deploy/deploy-dev.sh`, which also works standalone:

```bash
ssh deploy@dev-server 'bash -s' < deploy/deploy-dev.sh
```

## Development

| Command | Local | Docker |
|---------|-------|--------|
| Dev servers | `composer dev` | `make dev` |
| Tests | `composer test` | `make test` |
| Lint | `./vendor/bin/pint` | `make lint` |
| New module | `php artisan make:module` | `make module` |
| Shell / Tinker | `php artisan tinker` | `make shell` / `make tinker` |
| Health check | `GET /api/health` | — |

## License

MIT
