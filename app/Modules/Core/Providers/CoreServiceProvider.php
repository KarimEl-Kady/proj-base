<?php

namespace App\Modules\Core\Providers;

use App\Models\Tenant;
use App\Modules\Core\Commands\BackfillTenantDataCommand;
use App\Modules\Core\Commands\CheckTenantDataCommand;
use App\Modules\Core\Commands\MakeModuleCommand;
use App\Modules\Core\Commands\MakePackageCommand;
use App\Modules\Core\Commands\ModuleBoundariesCommand;
use App\Modules\Core\Commands\ModuleDeleteCommand;
use App\Modules\Core\Commands\ModuleDisableCommand;
use App\Modules\Core\Commands\ModuleEnableCommand;
use App\Modules\Core\Commands\ModuleListCommand;
use App\Modules\Core\Commands\ModuleMakeCommand;
use App\Modules\Core\Commands\PackageListCommand;
use App\Modules\Core\Commands\ProjectInfoCommand;
use App\Modules\Core\Commands\PruneMessagesCommand;
use App\Modules\Core\Commands\PublishOutboxCommand;
use App\Modules\Core\Commands\RetryOutboxCommand;
use App\Modules\Core\Commands\TenantMigrationsCommand;
use App\Modules\Core\Commands\ValidateProjectCommand;
use App\Modules\Core\Middleware\RequestContextMiddleware;
use App\Modules\Core\Middleware\TenantMiddleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../../config/project.php', 'project'
        );

        $this->loadHelpers();
        $this->registerModuleProviders();
        $this->configureApiResources();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModuleCommand::class,
                MakePackageCommand::class,
                BackfillTenantDataCommand::class,
                CheckTenantDataCommand::class,
                ModuleBoundariesCommand::class,
                ModuleDeleteCommand::class,
                ModuleDisableCommand::class,
                ModuleEnableCommand::class,
                ModuleListCommand::class,
                ModuleMakeCommand::class,
                PackageListCommand::class,
                PruneMessagesCommand::class,
                PublishOutboxCommand::class,
                ProjectInfoCommand::class,
                RetryOutboxCommand::class,
                TenantMigrationsCommand::class,
                ValidateProjectCommand::class,
            ]);

            $this->registerModuleCommands();
        }

        $this->registerTenancyMacros();
        $this->loadModuleResources();
        $this->loadModuleRouteFiles();
        $this->registerTenantMiddleware();
        $this->registerRequestContextMiddleware();
        $this->registerRateLimiting();
        $this->registerQueueHeartbeat();
    }

    protected function registerQueueHeartbeat(): void
    {
        Queue::looping(function (): void {
            $ttl = max(30, (int) config('project.health.queue_heartbeat_ttl', 120));
            Cache::put('project.queue_worker_heartbeat', now()->timestamp, $ttl);
        });
    }

    /**
     * Project-wide API rate limit (project.api.rate_limit requests/min,
     * keyed per user or IP). Routes with their own throttle middleware
     * (e.g. the Auth module's login/register) are limited by both.
     */
    protected function registerRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $limit = max(1, min((int) config('project.api.rate_limit', 60), 10000));

            return Limit::perMinute($limit)
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        $this->app['router']->prependMiddlewareToGroup('api', 'throttle:api');
    }

    protected function loadHelpers(): void
    {
        require_once __DIR__.'/../Helpers/helpers.php';
    }

    /**
     * Schema macros that make migrations tenancy-mode aware. Use as a bare
     * statement in create-table migrations:
     *
     *     $table->tenantColumn();
     *
     * Whenever tenancy is active (single or multi mode) it adds the
     * configured tenant column (nullable, indexed) referencing the configured
     * tenant model's table; in "none" mode it's a no-op. For tables created
     * while the project was still in "none" mode, `php artisan
     * tenant:migrations` generates the catch-up add-column migrations.
     */
    protected function registerTenancyMacros(): void
    {
        Blueprint::macro('tenantColumn', function (): Blueprint {
            /** @var Blueprint $this */
            if (has_tenancy()) {
                $column = config('project.tenancy.tenant_column', 'tenant_id');
                /** @var class-string<Model> $tenantModel */
                $tenantModel = config('project.tenancy.tenant_model', Tenant::class);
                $tenantTable = (new $tenantModel)->getTable();
                $this->foreignId($column)
                    ->nullable()
                    ->index()
                    ->constrained($tenantTable)
                    ->restrictOnDelete();
            }

            return $this;
        });
    }

    protected function registerModuleProviders(): void
    {
        $modules = config('project.modules', []);

        foreach ($modules as $module) {
            $provider = "App\\Modules\\{$module}\\Providers\\{$module}ServiceProvider";

            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    /**
     * Register migrations, views, and translations for every active module.
     *
     * Views and translations are namespaced by the module's kebab-case name,
     * e.g. view('user::index'), __('blog::messages.created').
     */
    protected function loadModuleResources(): void
    {
        foreach (array_unique(array_merge(['Core'], config('project.modules', []))) as $module) {
            $migrations = module_path($module, 'Database/Migrations');
            if (is_dir($migrations)) {
                $this->loadMigrationsFrom($migrations);
            }

            $namespace = Str::kebab($module);

            $views = module_path($module, 'Views');
            if (is_dir($views)) {
                $this->loadViewsFrom($views, $namespace);
            }

            $lang = module_path($module, 'Lang');
            if (is_dir($lang)) {
                $this->loadTranslationsFrom($lang, $namespace);
            }
        }
    }

    /**
     * Auto-register artisan commands living in each active module's
     * Commands directory (e.g. app/Modules/Blog/Commands/*.php).
     */
    protected function registerModuleCommands(): void
    {
        foreach (config('project.modules', []) as $module) {
            $dir = module_path($module, 'Commands');

            if (! is_dir($dir)) {
                continue;
            }

            foreach (glob($dir.'/*.php') ?: [] as $file) {
                $class = "App\\Modules\\{$module}\\Commands\\".basename($file, '.php');

                if (class_exists($class) && is_subclass_of($class, Command::class)) {
                    $this->commands([$class]);
                }
            }
        }
    }

    /**
     * Always registered on both route groups; the middleware itself is a
     * pass-through when tenancy.mode is "none", so the mode is honored at
     * request time (not frozen at boot) and flipping it never requires
     * re-wiring.
     */
    protected function registerTenantMiddleware(): void
    {
        $this->app['router']->pushMiddlewareToGroup('api', TenantMiddleware::class);
        $this->app['router']->pushMiddlewareToGroup('web', TenantMiddleware::class);
    }

    protected function registerRequestContextMiddleware(): void
    {
        $this->app['router']->pushMiddlewareToGroup('api', RequestContextMiddleware::class);
        $this->app['router']->pushMiddlewareToGroup('web', RequestContextMiddleware::class);
    }

    /**
     * Module route files. Loaded for every active module (plus Core); each
     * file is optional, only existing ones are registered.
     *
     * - Routes/api.php        under the "api" middleware group
     * - Routes/web.php        under the "web" middleware group
     * - Routes/dashboard.php  under "web" + project.routes.dashboard config
     *
     * API files declare only their resource-relative prefix. The project API
     * prefix/version and optional path-tenant segment are applied centrally.
     * Web route prefixes stay in their files; dashboard.php gets its
     * prefix/name/middleware centrally from project.routes.dashboard.
     */
    protected function loadModuleRouteFiles(): void
    {
        $modules = array_unique(array_merge(['Core'], config('project.modules', [])));
        $pathTenancy = is_multi_tenant()
            && config('project.tenancy.tenant_identification') === 'path';
        $apiEnabled = (bool) config('project.api.enabled', true);
        $apiPrefix = trim((string) config('project.api.prefix', 'api'), '/');
        $apiVersion = trim((string) config('project.api.version', 'v1'), '/');
        $apiMiddleware = config('project.api.middleware', ['api']);

        foreach ($modules as $module) {
            $apiRoutes = module_path($module, 'Routes/api.php');
            if (is_file($apiRoutes) && ($apiEnabled || $module === 'Core')) {
                $prefix = $module === 'Core'
                    ? $apiPrefix
                    : "{$apiPrefix}/{$apiVersion}";

                $router = Route::middleware($apiMiddleware);
                if ($pathTenancy && $module !== 'Core') {
                    $prefix = '{tenant}/'.$prefix;
                }

                $router->prefix($prefix)
                    ->where(['tenant' => '[A-Za-z0-9-]+'])
                    ->group($apiRoutes);
            }

            $webRoutes = module_path($module, 'Routes/web.php');
            if (is_file($webRoutes)) {
                $router = Route::middleware('web');

                if ($pathTenancy && $module !== 'Core') {
                    $router->prefix('{tenant}')->where(['tenant' => '[A-Za-z0-9-]+']);
                }

                $router->group($webRoutes);
            }

            $dashboardRoutes = module_path($module, 'Routes/dashboard.php');
            if (is_file($dashboardRoutes)) {
                $prefix = config('project.routes.dashboard.prefix', 'dashboard');
                if ($pathTenancy && $module !== 'Core') {
                    $prefix = '{tenant}/'.$prefix;
                }

                Route::middleware(config('project.routes.dashboard.middleware', ['web']))
                    ->prefix($prefix)
                    ->where(['tenant' => '[A-Za-z0-9-]+'])
                    ->name(config('project.routes.dashboard.name_prefix', 'dashboard.'))
                    ->group($dashboardRoutes);
            }
        }
    }

    protected function configureApiResources(): void
    {
        if (config('project.api_resources.without_wrapping', false)) {
            JsonResource::withoutWrapping();
        } else {
            JsonResource::wrap(
                config('project.api_resources.wrap', 'data')
            );
        }
    }
}
