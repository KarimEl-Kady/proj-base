<?php

namespace App\Modules\User\Listeners;

use App\Modules\Core\Listeners\QueuedListener;
use App\Modules\User\Events\UserCreated;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;

/**
 * Audit-trail primitive: records every user creation with the event's
 * identity metadata. Runs on the queue (QueuedListener), off the request
 * path — the living example of an async, auto-discovered module listener.
 */
class RecordUserCreation extends QueuedListener
{
    public function handle(UserCreated $event): void
    {
        Log::info('user.created', [
            'event_id' => $event->eventId,
            'occurred_at' => $event->occurredAt->toIso8601String(),
            'user_uuid' => $event->user->uuid,
            'tenant_id' => Context::get('tenant_id'),
            'request_id' => Context::get('request_id'),
        ]);
    }
}
