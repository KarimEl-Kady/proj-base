<?php

use App\Modules\Core\Exceptions\Handler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
 * Listener auto-discovery for active modules (+ Core). Config isn't loaded
 * yet at this point, so read the module registry file directly — the same
 * way config/project.php derives project.modules from it. Discovered
 * listeners need a type-hinted event on handle()/__invoke(); the result is
 * cached by `php artisan event:cache` like any other discovery path.
 */
$moduleRegistry = is_file(__DIR__.'/../config/project_modules.php')
    ? (array) require __DIR__.'/../config/project_modules.php'
    : [];

$listenerPaths = array_map(
    fn (string $module) => __DIR__."/../app/Modules/{$module}/Listeners",
    ['Core', ...array_keys(array_filter($moduleRegistry))]
);

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: $listenerPaths)
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Uniform {success, message, errors} envelope for API errors.
        $exceptions->render(fn (Throwable $e, Request $request) => Handler::render($e, $request));
    })->create();
