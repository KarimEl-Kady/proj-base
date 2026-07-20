<?php

namespace App\Modules\Core\Providers;

use App\Models\Tenant;
use App\Modules\Core\Commands\BackfillTenantDataCommand;
use App\Modules\Core\Commands\CheckTenantDataCommand;
use App\Modules\Core\Commands\CreateTenantCommand;
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
use App\Modules\Core\Commands\TenantClassifyCommand;
use App\Modules\Core\Commands\TenantMigrationsCommand;
use App\Modules\Core\Commands\ValidateProjectCommand;
use App\Modules\Core\Support\RuntimeRegistrar;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Resources\Json\JsonResource;
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
                CreateTenantCommand::class,
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
                TenantClassifyCommand::class,
                TenantMigrationsCommand::class,
                ValidateProjectCommand::class,
            ]);

            $this->registerModuleCommands();
        }

        $this->registerTenancyMacros();
        $this->loadModuleResources();
        $this->loadModuleRouteFiles();
        $this->app->make(RuntimeRegistrar::class)->register();
    }

    protected function loadHelpers(): void
    {
        require_once __DIR__.'/../Helpers/helpers.php';
    }

    /**
     * Schema macro used as a bare statement in create-table migrations:
     *
     *     $table->tenantColumn();
     *
     * Unconditionally adds the configured tenant column (nullable, indexed,
     * FK to the configured tenant model's table) regardless of the current
     * tenancy mode — mirroring the `tenants` table itself, which is also
     * created in every mode (see its migration's docblock). A schema that
     * doesn't fork on `project.tenancy.mode` means switching modes is a
     * config + backfill change, never a "did every table get the column"
     * archaeology exercise, and every environment ends up with the same
     * schema no matter which mode it was first migrated under.
     *
     * In "none" mode the column simply stays null on every row — no code
     * path stamps or scopes on it, since HasTenantScope and the creating()
     * hook both no-op when `has_tenancy()` is false. `php artisan
     * tenant:migrations` remains available to retrofit hand-written
     * migrations (ones that didn't use this macro) that are missing the
     * column.
     */
    protected function registerTenancyMacros(): void
    {
        Blueprint::macro('tenantColumn', function (): Blueprint {
            /** @var Blueprint $this */
            $column = config('project.tenancy.tenant_column', 'tenant_id');
            /** @var class-string<Model> $tenantModel */
            $tenantModel = config('project.tenancy.tenant_model', Tenant::class);
            $tenantTable = (new $tenantModel)->getTable();
            $this->foreignId($column)
                ->nullable()
                ->index()
                ->constrained($tenantTable)
                ->restrictOnDelete();

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
        // route:cache already compiled and stored the route table; re-running
        // this filesystem scan + Route::group() registration on every boot
        // would just repeat work the cache exists to skip. Laravel replaces
        // the router's route collection with the cached one after boot()
        // regardless (see bootstrap/app.php withRouting()), so this guard
        // only removes redundant work — it doesn't change what routes end
        // up registered.
        if ($this->app->routesAreCached()) {
            return;
        }

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
