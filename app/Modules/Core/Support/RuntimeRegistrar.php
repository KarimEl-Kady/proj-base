<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Middleware\RequestContextMiddleware;
use App\Modules\Core\Middleware\SecurityHeadersMiddleware;
use App\Modules\Core\Middleware\TenantMiddleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\Looping;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

class RuntimeRegistrar
{
    public function __construct(protected Router $router) {}

    public function register(): void
    {
        $this->router->pushMiddlewareToGroup('api', TenantMiddleware::class);
        $this->router->pushMiddlewareToGroup('web', TenantMiddleware::class);
        $this->router->pushMiddlewareToGroup('api', RequestContextMiddleware::class);
        $this->router->pushMiddlewareToGroup('web', RequestContextMiddleware::class);
        $this->router->pushMiddlewareToGroup('web', SecurityHeadersMiddleware::class);

        RateLimiter::for('api', function (Request $request): Limit {
            $limit = max(1, min((int) config('project.api.rate_limit', 60), 10000));

            return Limit::perMinute($limit)
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });
        $this->router->prependMiddlewareToGroup('api', 'throttle:api');

        Queue::looping(function (Looping $event): void {
            $ttl = max(30, (int) config('project.health.queue_heartbeat_ttl', 120));

            foreach (explode(',', $event->queue) as $queue) {
                Cache::put('project.queue_worker_heartbeat.'.trim($queue), now()->timestamp, $ttl);
            }
        });
    }
}
