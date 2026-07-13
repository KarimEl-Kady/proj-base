<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Events\DurableMessage;
use Closure;
use Illuminate\Support\Facades\DB;

class Inbox
{
    public function consume(DurableMessage $message, string $consumer, Closure $callback): bool
    {
        return $this->once(
            $message->eventId,
            $consumer,
            fn () => with_tenant(
                $message->tenantId,
                fn () => $callback($message),
            ),
        );
    }

    public function once(string $eventId, string $consumer, Closure $callback): bool
    {
        return DB::transaction(function () use ($eventId, $consumer, $callback): bool {
            $claimed = DB::table('processed_messages')->insertOrIgnore([
                'event_id' => $eventId,
                'consumer' => $consumer,
                'processed_at' => now(),
            ]);

            if ($claimed === 0) {
                return false;
            }

            $callback();

            return true;
        }, attempts: 3);
    }
}
