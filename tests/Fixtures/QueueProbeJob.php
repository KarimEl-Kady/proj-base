<?php

namespace Tests\Fixtures;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class QueueProbeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $cacheKey) {}

    public function handle(): void
    {
        Cache::put($this->cacheKey, 'processed', 60);
    }
}
