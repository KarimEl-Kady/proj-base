<?php

namespace App\Modules\Core\Providers;

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
use App\Modules\Core\Middleware\TenantMiddleware;
use Illuminate\Console\Command;
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
                ModuleBoundariesCommand::class,
                ModuleDeleteCommand::class,
                ModuleDisableCommand::class,
                ModuleEnableCommand::class,
                ModuleListCommand::class,
                ModuleMakeCommand::class,
                PackageListCommand::class,
                ProjectInfoCommand::class,
            ]);

            $this->registerModuleCommands();
        }

        $this->loadModuleResources();
        $this->loadModuleRouteFiles();

        if (config('project.platform') === 'api' || config('project.platform') === 'hybrid') {
            $this->registerApiMiddleware();
        }
    }

    protected function loadHelpers(): void
    {
        require_once __DIR__.'/../Helpers/helpers.php';
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
        foreach (config('project.modules', []) as $module) {
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

    protected function registerApiMiddleware(): void
    {
        if (is_multi_tenant()) {
            $this->app['router']->pushMiddlewareToGroup('api', TenantMiddleware::class);
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
     * Prefixes/names for api.php/web.php are declared inside each file
     * itself. dashboard.php gets its prefix/name/middleware centrally from
     * project.routes.dashboard so every module's backoffice section is
     * consistent.
     */
    protected function loadModuleRouteFiles(): void
    {
        $modules = array_unique(array_merge(['Core'], config('project.modules', [])));

        foreach ($modules as $module) {
            $apiRoutes = module_path($module, 'Routes/api.php');
            if (is_file($apiRoutes)) {
                Route::middleware('api')->group($apiRoutes);
            }

            $webRoutes = module_path($module, 'Routes/web.php');
            if (is_file($webRoutes)) {
                Route::middleware('web')->group($webRoutes);
            }

            $dashboardRoutes = module_path($module, 'Routes/dashboard.php');
            if (is_file($dashboardRoutes)) {
                Route::middleware(config('project.routes.dashboard.middleware', ['web']))
                    ->prefix(config('project.routes.dashboard.prefix', 'dashboard'))
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
