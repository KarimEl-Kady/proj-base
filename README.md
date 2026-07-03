# Proj-Base

A modular HMVC application foundation built on Laravel, designed to serve as a starting point for any platform — web, API, or hybrid. Batteries included: module generators, a standard API fetch pipeline, local composer packages, per-module tests, Docker, and CI/CD with dev-server deployment.

## Stack

- **PHP** 8.3+ · **Laravel** 13.7
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
│   ├── Api/            # JSON endpoints (route attributes)
│   └── Web/            # Blade views (route attributes)
├── Commands/           # Artisan commands (auto-registered)
├── Database/
│   ├── Factories/      # Auto-resolved per module
│   ├── Migrations/     # Auto-loaded
│   └── Seeders/
├── Lang/               # Auto-loaded, namespaced: __('blog::key')
├── Models/
├── Providers/
├── Repositories/
├── Requests/           # Extend Core BaseRequest / FetchRequest
├── Resources/
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
| **Route attributes** | Routes declared via PHP 8 attributes (`#[Get]`, `#[Post]`, `#[Prefix]`, `#[Middleware]`) using `spatie/laravel-route-attributes` |
| **Fetch pipeline** | Standard listing keys on every index endpoint: `?word=`, `?pagination=false`, `?per_page=`, `?sort_by=`, `?sort_dir=` — validated by `FetchRequest`, executed by `BaseRepository::fetch()` |
| **Local packages** | `app/Vendor/{Name}` composer path packages (e.g. `local/media`) — scaffold with `make:package`, install with `composer require local/name:"*"` |
| **Public UUIDs** | Auto-increment integer PKs internally; a `uuid` column is the public identifier (API `id`, route binding, repository lookup) |
| **Uniform API envelope** | Success and error responses share `{success, message, data/errors}` — including validation, 404, 401, 403, 500 |
| **Multi-tenancy** | Toggle `PROJECT_TENANCY_MODE` — subdomain, header, or path-based tenant resolution |
| **Feature flags** | Registration, email verification, 2FA, API tokens — togglable via env |

## Generators

All commands are interactive when called with no arguments, and scriptable with them:

```bash
# New module — wizard asks: name, API/Web/both, extras, enable now?
php artisan make:module
php artisan make:module Blog --api-only --with=migration --with=test

# Single component into an existing module
php artisan module:make                       # interactive
php artisan module:make Blog model Post
php artisan module:make Blog request FetchPosts --fetch
php artisan module:make Blog test Post        # full API CRUD test
# types: model, migration, controller, request, resource, service,
#        repository, seeder, factory, command, job, event, listener,
#        middleware, policy, observer, test

# Module lifecycle
php artisan module:list
php artisan module:enable Blog                # or omit the name to pick from a list
php artisan module:disable Blog
php artisan module:delete Blog

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

### Local packages

Non-Packagist packages live in `app/Vendor/{Name}` with their own `composer.json`, installed through a composer `path` repository (symlinked) and auto-discovered by Laravel. First-party example — **`local/media`** (polymorphic attachments):

```php
use Local\Media\Traits\HasMedia;

class Post extends Model { use HasMedia; }

$post->addMedia($request->file('cover'), 'covers');
$post->getFirstMediaUrl('covers');
$post->clearMedia('covers');
```

### Generate module components

Individual components can be scaffolded after a module exists:

```bash
# Model
php artisan make:module:model Blog Post --fillable=name,slug,content

# Repository
php artisan make:module:repository Blog PostRepository --model=Post

# Service
php artisan make:module:service Blog PostService --repository=PostRepository

# Controller (API or Web)
php artisan make:module:controller Blog Post --type=api --resource=PostResource
php artisan make:module:controller Blog Post --type=web --service=PostService

# Form Requests
php artisan make:module:request Blog Post --type=both --rules="title:required|string|max:255|body:required|string"

# API Resource
php artisan make:module:resource Blog Post --fields=id,title,slug,created_at

# Event + Listener
php artisan make:module:event Blog PostCreated --model=Post --channels=posts
```

| Command | Description |
|---------|-------------|
| `make:module:model` | Create model with optional fillable fields |
| `make:module:repository` | Create repository extending BaseRepository |
| `make:module:service` | Create service extending BaseService |
| `make:module:controller` | Create API or Web controller with route attributes |
| `make:module:request` | Create Create/Update form requests with validation rules |
| `make:module:resource` | Create API resource transformer |
| `make:module:event` | Create event + queued listener pair |

## Configuration

Project settings live in `config/project.php` (`PROJECT_*` env vars); active modules in `config/project_modules.php`:

| Setting | Default | Description |
|---------|---------|-------------|
| `config/project_modules.php` | `['User' => true]` | Module registry (file, not env) |
| `PROJECT_TENANCY_MODE` | `single` | `single` or `multi` |
| `PROJECT_PLATFORM` | `web` | `web`, `api`, or `hybrid` |
| `PROJECT_ROUTE_ATTRIBUTES` | `true` | Use PHP 8 route attributes |
| `PROJECT_DB_DRIVER` | `mysql` | Database driver |
| `PROJECT_PAGINATION_PER_PAGE` | `15` | Default page size |
| `PROJECT_PAGINATION_MAX_PER_PAGE` | `100` | Hard cap for `?per_page=` |
| `MEDIA_DISK` / `MEDIA_MAX_FILE_SIZE` | `public` / `10240` KB | Media package |

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
2. **Lint + Test** — Pint, boot smoke check, PHPUnit on PHP 8.3/8.4
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
