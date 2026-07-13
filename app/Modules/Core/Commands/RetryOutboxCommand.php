<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Models\OutboxMessage;
use Illuminate\Console\Command;

class RetryOutboxCommand extends Command
{
    protected $signature = 'outbox:retry
        {event_id? : Event UUID to retry}
        {--all : Retry every dead-lettered message}';

    protected $description = 'Return dead-lettered outbox messages to the publish queue';

    public function handle(): int
    {
        $eventId = $this->argument('event_id');

        if (! is_string($eventId) && ! $this->option('all')) {
            $this->error('Provide an event_id or pass --all.');

            return self::INVALID;
        }

        $query = OutboxMessage::query()->whereNotNull('failed_at');

        if (is_string($eventId)) {
            $query->where('event_id', $eventId);
        }

        $updated = $query->update([
            'attempts' => 0,
            'available_at' => now(),
            'claimed_at' => null,
            'claim_token' => null,
            'failed_at' => null,
            'last_error' => null,
            'updated_at' => now(),
        ]);

        $this->info("Released {$updated} outbox message(s) for retry.");

        return self::SUCCESS;
    }
}
