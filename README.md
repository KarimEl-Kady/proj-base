# Proj-Base

A modular HMVC application foundation built on Laravel, designed to serve as a starting point for any platform — web, API, or hybrid.

## Stack

- **PHP** 8.3+ · **Laravel** 13.7
- **Vite** 8 · **Tailwind CSS** 4
- **SQLite** (dev) / MySQL / PostgreSQL (production)

## Quick Start

```bash
# Clone and set up
git clone git@github.com:KarimEl-Kady/proj-base.git
cd proj-base
composer setup

# Start development server + queue + logs + vite
composer dev
```

The app runs at `http://localhost:8000`.

## Architecture

The project follows an **HMVC** (Hierarchical Model-View-Controller) pattern with modules under `app/Modules/`. Each module is self-contained:

```
app/Modules/{Module}/
├── Controllers/
│   ├── Api/          # API endpoints (JSON + route attributes)
│   └── Web/          # Web views (Blade + route attributes)
├── Models/           # Eloquent models
├── Providers/        # Module service provider
├── Repositories/     # Data access layer
├── Requests/         # Form request validation
├── Resources/        # API resource transformers
└── Services/         # Business logic layer
```

**Request flow:** Controller → Service → Repository → Model, with Resources for API serialization.

### Key features

| Feature | Details |
|---------|---------|
| **Modules** | Controlled via `PROJECT_MODULES` env (comma-separated) — auto-registered by `CoreServiceProvider` |
| **Route attributes** | Routes declared via PHP 8 attributes (`#[Get]`, `#[Post]`, `#[Prefix]`, `#[Middleware]`) using `spatie/laravel-route-attributes` |
| **Multi-tenancy** | Toggle `PROJECT_TENANCY_MODE` — supports subdomain, header, and path-based tenant resolution |
| **UUID primary keys** | All models use ordered UUIDs via `HasUuid` trait |
| **Feature flags** | Registration, email verification, 2FA, API tokens — all togglable via env |

### Generate a new module

```bash
php artisan make:module Blog                # Full (API + Web)
php artisan make:module Blog --api-only     # API only
php artisan make:module Blog --web-only     # Web only

# Or via Composer
composer module -- Blog
```

Then add `Blog` to `PROJECT_MODULES` in your `.env`.

## Configuration

All project-specific settings live in `config/project.php`, controlled via `PROJECT_*` env variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `PROJECT_MODULES` | `User` | Active modules (comma-separated) |
| `PROJECT_TENANCY_MODE` | `single` | `single` or `multi` |
| `PROJECT_PLATFORM` | `web` | `web`, `api`, or `hybrid` |
| `PROJECT_ROUTE_ATTRIBUTES` | `true` | Use PHP 8 route attributes |
| `PROJECT_DB_DRIVER` | `mysql` | Database driver |
| `PROJECT_FEATURE_REGISTRATION` | `true` | Enable user registration |
| `PROJECT_FEATURE_API_TOKENS` | `false` | Enable Sanctum API tokens |

See `.env.example` for all available options.

## Development

| Command | Purpose |
|---------|---------|
| `composer dev` | Start server + queue + logs + vite |
| `composer test` | Run tests (clears config, uses SQLite :memory:) |
| `php artisan test --filter=ClassName` | Run a single test |
| `./vendor/bin/pint` | Lint PHP |
| `npm run dev` | Vite dev server |
| `npm run build` | Production build |

## License

MIT
