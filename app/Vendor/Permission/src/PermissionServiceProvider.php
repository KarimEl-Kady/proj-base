<?php

namespace Local\Permission;

use Illuminate\Support\Facades\Gate;
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

        $this->registerGateHook();

        if ($this->app->runningInConsole()) {
            $this->commands([
                PermissionSeedCommand::class,
                PermissionListCommand::class,
            ]);
        }
    }

    /**
     * Feeds every permission-bearing model into the whole Laravel
     * authorization surface (Gate::allows/denies, @can, $this->authorize(),
     * Policy fallback) — not just the `permission:` route middleware.
     *
     * Returning `false` from a Gate::before callback denies immediately
     * without ever reaching a Policy, so this only *allows*: it returns
     * `true` when the ability name is itself a granted permission, and
     * `null` (defer — try Gate::define()/Policies next) otherwise. A route
     * gated by `permission:users.update` and a controller calling
     * `$this->authorize('users.update')` are therefore equivalent; abilities
     * that need record-level logic (e.g. "update your own profile, or have
     * users.update") stay out of this hook and belong in a Policy method,
     * which this hook simply steps aside for.
     */
    protected function registerGateHook(): void
    {
        Gate::before(function ($user, string $ability) {
            if (! method_exists($user, 'hasPermissionTo')) {
                return null;
            }

            return $user->hasPermissionTo($ability) ?: null;
        });
    }
}
