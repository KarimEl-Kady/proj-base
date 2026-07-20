<?php

namespace App\Modules\Core\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Base class for listeners that should run on the queue instead of inside
 * the HTTP request. Retry behavior comes from config('project.events') so
 * every async listener in the project fails and retries the same way.
 *
 * Queue name comes from $lane — see config('project.events.lanes'). Every
 * lane is null (the connection's default queue) out of the box, and the
 * shipped workers (docker-compose `queue` service, `composer dev`) already
 * listen on all three lane names, so overriding $lane in a subclass is
 * enough by itself once a lane's env var is set to a real, distinct queue
 * name — no worker redeploy needed.
 */
abstract class QueuedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Which named lane this listener runs on. Override in a subclass doing
     * bulk/low-priority work so it can't starve latency-sensitive listeners
     * sharing the "default" lane (e.g. a bulk import vs. a password-reset
     * email notification).
     */
    protected string $lane = 'default';

    public function viaQueue(): ?string
    {
        return config("project.events.lanes.{$this->lane}")
            ?? config('project.events.lanes.default');
    }

    public function tries(): int
    {
        return (int) config('project.events.tries', 3);
    }

    /**
     * Seconds to wait between retries.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return config('project.events.backoff', [10, 60, 300]);
    }
}
