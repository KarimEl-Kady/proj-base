<?php

namespace App\Modules\Core\Tests\Unit;

use App\Modules\Core\Listeners\QueuedListener;
use Tests\TestCase;

class QueuedListenerLaneTest extends TestCase
{
    public function test_default_lane_resolves_to_the_default_lane_config(): void
    {
        config(['project.events.lanes.default' => 'default-queue-name']);

        $listener = new class extends QueuedListener
        {
            public function handle(): void {}
        };

        $this->assertSame('default-queue-name', $listener->viaQueue());
    }

    public function test_overriding_the_lane_resolves_to_its_own_config(): void
    {
        config([
            'project.events.lanes.default' => 'default-queue-name',
            'project.events.lanes.bulk' => 'bulk-queue-name',
        ]);

        $listener = new class extends QueuedListener
        {
            protected string $lane = 'bulk';

            public function handle(): void {}
        };

        $this->assertSame('bulk-queue-name', $listener->viaQueue());
    }

    public function test_an_unset_lane_falls_back_to_the_default_lane(): void
    {
        config([
            'project.events.lanes.default' => null,
            'project.events.lanes.notifications' => null,
        ]);

        $listener = new class extends QueuedListener
        {
            protected string $lane = 'notifications';

            public function handle(): void {}
        };

        $this->assertNull($listener->viaQueue());
    }
}
