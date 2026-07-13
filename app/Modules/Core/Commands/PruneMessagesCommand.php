<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneMessagesCommand extends Command
{
    protected $signature = 'messages:prune';

    protected $description = 'Prune old published/dead-lettered outbox and Inbox deduplication records';

    public function handle(): int
    {
        $published = OutboxMessage::query()
            ->whereNotNull('published_at')
            ->where('published_at', '<', now()->subHours($this->retention('published_hours', 168)))
            ->delete();

        $failed = OutboxMessage::query()
            ->whereNotNull('failed_at')
            ->where('failed_at', '<', now()->subHours($this->retention('failed_hours', 720)))
            ->delete();

        $processed = DB::table('processed_messages')
            ->where('processed_at', '<', now()->subHours($this->retention('processed_hours', 720)))
            ->delete();

        $this->info("Pruned {$published} published, {$failed} failed, and {$processed} processed message(s).");

        return self::SUCCESS;
    }

    protected function retention(string $key, int $default): int
    {
        return max(1, (int) config("project.outbox.retention.{$key}", $default));
    }
}
