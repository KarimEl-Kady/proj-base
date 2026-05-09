# AGENTS.md

## Dev commands

| Task | Command |
|------|---------|
| Setup | `composer setup` |
| Run dev | `composer dev` (server + queue + pail + vite concurrently) |
| Run tests | `composer test` |
| Single test | `php artisan test --filter=ClassName` |
| Lint (PHP) | `./vendor/bin/pint` |
| Frontend dev | `npm run dev` |
| Frontend build | `npm run build` |
| Make module | `php artisan make:module Blog` (or `composer module -- Blog`) |

## Architecture

Laravel 13.7, PHP 8.3+, Vite 8 + Tailwind CSS 4. Modular app structure under `app/Modules/`.

### Module system

- Active modules controlled by `PROJECT_MODULES` env var (comma-separated, e.g. `User,Profile`).
- `CoreServiceProvider` (`app/Modules/Core/Providers/CoreServiceProvider.php:32`) auto-registers each active module's `{Module}ServiceProvider` at `app/Modules/{Module}/Providers/{Module}ServiceProvider.php`.
- Use `module_path('Module')` helper to resolve module directories. Do NOT hardcode paths.
- Module directory structure is defined in `config/project.php` under `module_structure` key.
- Generate a new module: `php artisan make:module Blog` (alias: `composer module -- Blog`). Use `--api-only` or `--web-only` to skip unused controllers. Then add the module name to `PROJECT_MODULES` in `.env`.
- The command creates: ServiceProvider, Model, Repository, Service, ApiController, WebController, Create/Update Requests, and Resource — all wired with route attributes.

### Route attributes (spatie/laravel-route-attributes)

- Routes are declared via PHP 8 attributes on controller methods — there are **no module-level route files** (`Routes/api.php`, `Routes/web.php` are removed).
- `CoreServiceProvider::configureRouteAttributes()` dynamically registers each active module's `Controllers/Api` and `Controllers/Web` directories with the spatie route registrar.
- Toggle with `PROJECT_ROUTE_ATTRIBUTES` env (default `true`).
- Use `#[Prefix]` + `#[Middleware]` at the class level, method-level HTTP attributes (`#[Get]`, `#[Post]`, `#[Put]`, `#[Delete]`, `#[Patch]`) on methods.
- API controllers prefix: `api/v1/{resource}`. Web controllers prefix: `{resource}`.
- Example:
  ```php
  #[Prefix('api/v1/users')]
  #[Middleware('api')]
  class UserController extends Controller
  {
      #[Get('/', name: 'api.users.index')]
      public function index(): JsonResponse { ... }

      #[Get('/{user}', name: 'api.users.show')]
      public function show(string $id): JsonResponse { ... }
  }
  ```

### Base class hierarchy

- Models: `App\Modules\Core\Models\Model` extends Eloquent, includes `HasUuid` trait, has `scopeForTenant()`.
- Repositories: `App\Modules\Core\Repositories\BaseRepository` provides standard CRUD.
- Services: `App\Modules\Core\Services\BaseService` wraps a repository.
- Controllers: `App\Modules\Core\Controllers\Controller` adds `jsonResponse()` and `jsonError()` helpers. App-level `App\Http\Controllers\Controller` extends it.
- Resources: `App\Modules\Core\Resources\BaseResource` extends `JsonResource` for API response transformation.
- All modules follow: Controller → Service → Repository → Model, with Resources for API output.

### Multi-tenancy

- Controlled by `PROJECT_TENANCY_MODE` (default `single`). Set to `multi` to enable.
- `TenantMiddleware` resolves tenant from subdomain, `X-Tenant-ID` header, or URL path segment based on `PROJECT_TENANT_IDENTIFICATION`.
- Use `tenant_id()` helper to get current tenant, `is_multi_tenant()` to check mode.
- Models should use `HasTenantScope` trait and call `scopeForTenant()` on queries.

### Project config

All project-specific config lives in `config/project.php`, read via `project_config('key')` helper or `config('project.key')`. Features like registration, email verification, 2FA, API tokens are toggled via env vars prefixed `PROJECT_FEATURE_*`.

### Routing conventions

- Module API routes use prefix `api/v1/{resource}` (e.g. `api/v1/users`).
- Module web routes use prefix `{resource}` (e.g. `users`).
- Global `routes/api.php` and `routes/web.php` exist but are mostly empty — module routes are loaded by spatie/laravel-route-attributes scanning each module's Controllers directory.

## Testing

- PHPUnit 12, run with `php artisan test` (or `composer test` which clears config first).
- Tests use SQLite in-memory database (configured in `phpunit.xml`).
- `tests/Unit/` and `tests/Feature/` standard layout.

## Important gotchas

- `.npmrc` sets `ignore-scripts=true` — npm won't run post-install lifecycle scripts. When adding npm packages that need post-install scripts, this may cause issues.
- `config:clear` is run before tests; never rely on cached config in test environments.
- The `Model` base class has `$guarded = ['id', 'uuid']` — all other attributes are mass-assignable, but `id` and `uuid` are guarded.
- `HasUuid` trait sets `$incrementing = false` and `$keyType = 'string'` — primary keys are ordered UUIDs, not auto-increment integers.
