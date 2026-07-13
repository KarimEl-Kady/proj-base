<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Events\DurableMessage;
use App\Modules\Core\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class PublishOutboxCommand extends Command
{
    protected $signature = 'outbox:publish {--limit=100 : Maximum messages to publish}';

    protected $description = 'Publish pending transactional outbox messages';

    public function handle(): int
    {
        $limit = max(1, min((int) $this->option('limit'), 1000));
        $failed = 0;

        OutboxMessage::query()
            ->whereNull('published_at')
            ->whereNull('failed_at')
            ->where('available_at', '<=', now())
            ->where(function ($query): void {
                $expired = now()->subSeconds($this->claimTtl());
                $query->whereNull('claimed_at')->orWhere('claimed_at', '<=', $expired);
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $id) use (&$failed): void {
                $message = $this->claim($id);

                if ($message !== null && ! $this->publish($message)) {
                    $failed++;
                }
            });

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function claim(int $id): ?OutboxMessage
    {
        $token = (string) Str::uuid();
        $expired = now()->subSeconds($this->claimTtl());

        $claimed = OutboxMessage::query()
            ->whereKey($id)
            ->whereNull('published_at')
            ->whereNull('failed_at')
            ->where('available_at', '<=', now())
            ->where(function ($query) use ($expired): void {
                $query->whereNull('claimed_at')->orWhere('claimed_at', '<=', $expired);
            })
            ->update([
                'claim_token' => $token,
                'claimed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return null;
        }

        return OutboxMessage::query()->where('claim_token', $token)->first();
    }

    protected function publish(OutboxMessage $message): bool
    {
        try {
            DurableMessage::dispatch(
                $message->event_id,
                $message->type,
                $message->payload,
                $message->tenant_id,
                $message->occurred_at,
            );

            OutboxMessage::query()
                ->whereKey($message->getKey())
                ->where('claim_token', $message->claim_token)
                ->update([
                    'published_at' => now(),
                    'attempts' => $message->attempts + 1,
                    'last_error' => null,
                    'claim_token' => null,
                    'claimed_at' => null,
                    'updated_at' => now(),
                ]);

            return true;
        } catch (Throwable $e) {
            $attempts = $message->attempts + 1;
            $failedAt = $attempts >= $this->maxAttempts() ? now() : null;

            OutboxMessage::query()
                ->whereKey($message->getKey())
                ->where('claim_token', $message->claim_token)
                ->update([
                    'attempts' => $attempts,
                    'last_error' => mb_substr($e->getMessage(), 0, 65535),
                    'available_at' => $this->nextAttemptAt($attempts),
                    'failed_at' => $failedAt,
                    'claim_token' => null,
                    'claimed_at' => null,
                    'updated_at' => now(),
                ]);

            report($e);

            return false;
        }
    }

    protected function claimTtl(): int
    {
        return max(30, (int) config('project.outbox.claim_ttl_seconds', 300));
    }

    protected function maxAttempts(): int
    {
        return max(1, (int) config('project.outbox.max_attempts', 10));
    }

    protected function nextAttemptAt(int $attempts): Carbon
    {
        $backoff = array_values((array) config('project.outbox.backoff', [10, 60, 300, 900, 3600]));
        $seconds = (int) ($backoff[min($attempts - 1, count($backoff) - 1)] ?? 60);

        return now()->addSeconds(max(1, $seconds));
    }
}
