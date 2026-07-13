<?php

namespace App\Modules\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class DurableMessage
{
    use Dispatchable;
    use SerializesModels;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $eventId,
        public string $type,
        public array $payload,
        public int|string|null $tenantId,
        public Carbon $occurredAt,
    ) {}
}
