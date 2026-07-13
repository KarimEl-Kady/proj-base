<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Fixtures\QueueProbeJob;
use Tests\TestCase;

class RedisQueueIntegrationTest extends TestCase
{
    public function test_job_round_trips_through_real_redis_queue_and_cache(): void
    {
        if (! filter_var(env('RUN_REDIS_INTEGRATION', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_REDIS_INTEGRATION=true to exercise Redis transport.');
        }

        $queue = 'integration-'.Str::lower(Str::random(12));
        $cacheKey = 'queue-probe:'.Str::uuid();
        $connection = Queue::connection('redis');

        Cache::forget($cacheKey);
        $connection->pushOn($queue, new QueueProbeJob($cacheKey));

        $this->assertSame(1, $connection->size($queue));

        $job = $connection->pop($queue);
        $this->assertNotNull($job);
        $job->fire();
        $job->delete();

        $this->assertSame('processed', Cache::get($cacheKey));
        $this->assertSame(0, $connection->size($queue));

        Cache::forget($cacheKey);
    }
}
