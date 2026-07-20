<?php

namespace App\Modules\Core\Controllers\Api;

use App\Modules\Core\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Local\DataResponse\DataResponse;

class HealthController extends Controller
{
    /** Dependency readiness probe. Use /api/health/live for liveness. */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
        ];

        // Add Redis check only when Redis is configured
        if ($this->isRedisConfigured()) {
            $checks['redis'] = $this->checkRedis();
        }

        $healthy = collect($checks)->every(fn (array $check) => $check['status'] === 'ok');

        if (! config('project.health.expose_details', false)) {
            $checks = collect($checks)
                ->map(fn (array $check): array => ['status' => $check['status']])
                ->all();
        }

        // Deliberately not the success/message/data envelope — health
        // check tooling (uptime monitors, k8s probes) expects this flat
        // shape, so it goes through DataResponse::raw() instead.
        return DataResponse::raw([
            'status' => $healthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'version' => config('project.version', '1.0.0'),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'driver' => config('database.default'),
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $this->dependencyError($e),
            ];
        }
    }

    protected function checkCache(): array
    {
        try {
            $key = 'health_check_'.uniqid();
            Cache::put($key, true, 5);
            $hit = Cache::get($key);
            Cache::forget($key);

            return [
                'status' => $hit ? 'ok' : 'error',
                'driver' => config('cache.default'),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $this->dependencyError($e),
            ];
        }
    }

    protected function checkRedis(): array
    {
        try {
            $start = microtime(true);
            $pong = Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => $pong ? 'ok' : 'error',
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $this->dependencyError($e),
            ];
        }
    }

    protected function checkQueue(): array
    {
        try {
            $connection = config('queue.default');
            $driver = config("queue.connections.{$connection}.driver", $connection);
            $backlog = Queue::connection($connection)->size();
            $requiresWorker = (bool) config('project.health.require_queue_worker', false);
            $lanes = array_values(array_unique(array_filter(
                (array) config('project.events.lanes', ['default' => 'default']),
                'is_string',
            )));
            $lanes = $lanes === [] ? ['default'] : $lanes;
            $heartbeats = collect($lanes)->mapWithKeys(
                fn (string $lane): array => [$lane => Cache::get("project.queue_worker_heartbeat.{$lane}")]
            );
            $workersAlive = $driver === 'sync' || $heartbeats->every(fn (mixed $heartbeat): bool => $heartbeat !== null);
            $warningAt = max(1, (int) config('project.health.queue_backlog_warning', 1000));

            return [
                'status' => $requiresWorker && ! $workersAlive ? 'error' : 'ok',
                'connection' => $connection,
                'driver' => $driver,
                'backlog' => $backlog,
                'backlog_status' => $backlog >= $warningAt ? 'warning' : 'ok',
                'workers_alive' => $workersAlive,
                'heartbeats' => $heartbeats->map(
                    fn (mixed $heartbeat): ?string => $heartbeat === null
                        ? null
                        : now()->setTimestamp((int) $heartbeat)->toIso8601String()
                )->all(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $this->dependencyError($e),
            ];
        }
    }

    protected function isRedisConfigured(): bool
    {
        return in_array(config('cache.default'), ['redis']) ||
               in_array(config('session.driver'), ['redis']) ||
               in_array(config('queue.default'), ['redis']);
    }

    protected function dependencyError(\Throwable $error): string
    {
        return config('app.debug') ? $error->getMessage() : 'Dependency unavailable.';
    }
}
