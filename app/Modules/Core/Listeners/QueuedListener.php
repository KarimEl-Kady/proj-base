<?php

namespace App\Modules\Core\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Base class for listeners that should run on the queue instead of inside
 * the HTTP request. Retry behavior and queue name come from
 * config('project.events') so every async listener in the project fails
 * and retries the same way.
 *
 * Note: project.events.queue defaults to null (the default queue) on
 * purpose — the shipped queue workers (docker-compose `queue` service,
 * `composer dev`) only consume the default queue. If you point this at a
 * named queue, add it to the workers' --queue= list.
 */
abstract class QueuedListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function viaQueue(): ?string
    {
        return config('project.events.queue');
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
