<?php

namespace Local\Permission;

use Illuminate\Support\ServiceProvider;
use Local\Permission\Commands\PermissionListCommand;
use Local\Permission\Commands\PermissionSeedCommand;
use Local\Permission\Middleware\PermissionMiddleware;
use Local\Permission\Middleware\RoleMiddleware;
use Local\Permission\Middleware\RoleOrPermissionMiddleware;
use Local\Permission\Support\DefinitionLoader;
use Local\Permission\Support\PermissionRegistry;

class PermissionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/permission.php', 'permission');

        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(DefinitionLoader::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/permission.php' => config_path('permission.php'),
        ], 'permission-config');

        $router = $this->app['router'];
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PermissionSeedCommand::class,
                PermissionListCommand::class,
            ]);
        }
    }
}
