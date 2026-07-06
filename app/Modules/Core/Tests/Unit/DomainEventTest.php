<?php

namespace App\Modules\Core\Tests\Unit;

use App\Modules\Core\Events\DomainEvent;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Str;
use Tests\TestCase;

class DomainEventTest extends TestCase
{
    protected function makeEvent(): DomainEvent
    {
        return new class extends DomainEvent {};
    }

    public function test_it_defers_dispatch_until_the_transaction_commits(): void
    {
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, $this->makeEvent());
    }

    public function test_it_carries_a_uuid_identity(): void
    {
        $event = $this->makeEvent();

        $this->assertTrue(Str::isUuid($event->eventId));
        $this->assertNotSame($event->eventId, $this->makeEvent()->eventId);
    }

    public function test_it_records_when_it_occurred(): void
    {
        $event = $this->makeEvent();

        $this->assertTrue($event->occurredAt->isToday());
    }
}
