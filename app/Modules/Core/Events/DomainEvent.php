<?php

namespace App\Modules\Core\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Base class for module domain events — facts about something that already
 * happened ("UserCreated", "OrderShipped"), consumed by any number of
 * listeners the publisher knows nothing about.
 *
 * What extending this buys you over a bare event class:
 *
 * - ShouldDispatchAfterCommit: when dispatched inside a DB transaction, the
 *   event is held until the transaction commits (and dropped on rollback).
 *   Without this, a queued listener can run against data that isn't
 *   committed yet — the classic async-events production bug.
 * - Identity + time: every event carries a uuid ($eventId) and an
 *   $occurredAt timestamp, so logs/audit trails/external systems can
 *   deduplicate and order events.
 * - SerializesModels: Eloquent models in event properties are stored as
 *   ids and re-fetched fresh when a queued listener runs.
 */
abstract class DomainEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public string $eventId;

    public Carbon $occurredAt;

    public function __construct()
    {
        $this->eventId = (string) Str::uuid();
        $this->occurredAt = Carbon::now();
    }
}
