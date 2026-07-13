<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\OutboxMessage;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class Outbox
{
    /**
     * Record this inside the same transaction as the domain write.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(string $type, array $payload, ?string $eventId = null): OutboxMessage
    {
        return OutboxMessage::query()->create([
            'event_id' => $eventId ?? (string) Str::uuid(),
            'type' => $type,
            'payload' => $payload,
            'tenant_id' => tenant_id(),
            'request_id' => Context::get('request_id'),
            'occurred_at' => now(),
            'available_at' => now(),
        ]);
    }
}
