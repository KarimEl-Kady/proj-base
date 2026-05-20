<?php

namespace App\Modules\Core\Providers;

use App\Modules\Core\Commands\MakeControllerCommand;
use App\Modules\Core\Commands\MakeEventCommand;
use App\Modules\Core\Commands\MakeModelCommand;
use App\Modules\Core\Commands\MakeModuleCommand;
use App\Modules\Core\Commands\MakeRepositoryCommand;
use App\Modules\Core\Commands\MakeRequestCommand;
use App\Modules\Core\Commands\MakeResourceCommand;
use App\Modules\Core\Commands\MakeServiceCommand;
use App\Modules\Core\Middleware\TenantMiddleware;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../../../config/project.php', 'project'
        );

        $this->loadHelpers();
        $this->registerModuleProviders();
        $this->configureRouteAttributes();
        $this->configureApiResources();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeModuleCommand::class,
                MakeModelCommand::class,
                MakeRepositoryCommand::class,
                MakeServiceCommand::class,
                MakeControllerCommand::class,
                MakeRequestCommand::class,
                MakeResourceCommand::class,
                MakeEventCommand::class,
            ]);
        }

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

    protected function registerApiMiddleware(): void
    {
        if (is_multi_tenant()) {
            $this->app['router']->pushMiddlewareToGroup('api', TenantMiddleware::class);
        }
    }

    protected function configureRouteAttributes(): void
    {
        if (! config('project.route_attributes.enabled', true)) {
            return;
        }

        $directories = [];

        foreach (config('project.modules', []) as $module) {
            $namespace = "App\\Modules\\{$module}\\Controllers";

            $apiDir = module_path($module, 'Controllers/Api');
            if (is_dir($apiDir)) {
                $directories[$namespace.'\\Api\\'] = $apiDir;
            }

            $webDir = module_path($module, 'Controllers/Web');
            if (is_dir($webDir)) {
                $directories[$namespace.'\\Web\\'] = $webDir;
            }
        }

        config()->set('route-attributes.directories', $directories);
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
