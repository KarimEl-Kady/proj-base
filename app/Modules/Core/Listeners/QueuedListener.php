<?php

namespace App\Modules\Core\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Base class for listeners that should run on the queue instead of inside
 * the HTTP request. Retry behavior comes from config('project.events') so
 * every async listener in the project fails and retries the same way.
 *
 * Queue name comes from $lane — see config('project.events.lanes'). The
 * shipped runtime starts an independent worker for each lane, so overriding
 * $lane in a subclass changes both routing and reserved worker capacity.
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
