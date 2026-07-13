<?php

namespace App\Modules\Core\Tests\Feature;

use App\Modules\Core\Events\DurableMessage;
use App\Modules\Core\Support\Inbox;
use App\Modules\Core\Support\Outbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_record_rolls_back_with_the_domain_transaction(): void
    {
        try {
            DB::transaction(function (): void {
                app(Outbox::class)->record('orders.placed', ['order_id' => 10]);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertDatabaseCount('outbox_messages', 0);
    }

    public function test_publisher_dispatches_and_marks_pending_messages(): void
    {
        Event::fake([DurableMessage::class]);
        $message = app(Outbox::class)->record('orders.placed', ['order_id' => 10]);

        $this->artisan('outbox:publish')->assertSuccessful();

        Event::assertDispatched(DurableMessage::class, fn (DurableMessage $event) => $event->eventId === $message->event_id
            && $event->payload['order_id'] === 10
        );
        $this->assertNotNull($message->fresh()->published_at);
    }

    public function test_inbox_runs_each_consumer_once_and_releases_failed_claims(): void
    {
        $inbox = app(Inbox::class);
        $runs = 0;

        $this->assertTrue($inbox->once('0f51f115-83bc-4f65-a9c6-a4ad64dd6772', 'billing', function () use (&$runs): void {
            $runs++;
        }));
        $this->assertFalse($inbox->once('0f51f115-83bc-4f65-a9c6-a4ad64dd6772', 'billing', function () use (&$runs): void {
            $runs++;
        }));
        $this->assertSame(1, $runs);

        try {
            $inbox->once('e7f93cea-ca6c-47fa-b511-82255d7fc3db', 'billing', fn () => throw new RuntimeException('retry'));
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertTrue($inbox->once('e7f93cea-ca6c-47fa-b511-82255d7fc3db', 'billing', fn () => null));
    }

    public function test_inbox_claim_and_consumer_database_work_are_atomic(): void
    {
        $inbox = app(Inbox::class);
        $eventId = 'bc289779-98fc-4a61-803c-349f4456ef3c';

        try {
            $inbox->once($eventId, 'billing', function (): void {
                $this->assertGreaterThan(0, DB::transactionLevel());
                DB::table('cache')->insert([
                    'key' => 'consumer-side-effect',
                    'value' => 'done',
                    'expiration' => now()->addHour()->timestamp,
                ]);

                throw new RuntimeException('crash before commit');
            });
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertDatabaseMissing('processed_messages', ['event_id' => $eventId]);
        $this->assertDatabaseMissing('cache', ['key' => 'consumer-side-effect']);
    }

    public function test_publisher_claims_prevent_concurrent_delivery_and_expire_after_a_crash(): void
    {
        Event::fake([DurableMessage::class]);
        $message = app(Outbox::class)->record('orders.placed', ['order_id' => 20]);
        $message->forceFill([
            'claim_token' => (string) Str::uuid(),
            'claimed_at' => now(),
        ])->save();

        $this->artisan('outbox:publish')->assertSuccessful();
        Event::assertNotDispatched(DurableMessage::class);

        $message->forceFill(['claimed_at' => now()->subMinutes(10)])->save();

        $this->artisan('outbox:publish')->assertSuccessful();
        Event::assertDispatchedTimes(DurableMessage::class, 1);
        $this->assertNotNull($message->fresh()->published_at);
    }

    public function test_failed_publish_backs_off_then_dead_letters_and_can_be_retried(): void
    {
        config(['project.outbox.max_attempts' => 1]);
        Event::listen(DurableMessage::class, fn () => throw new RuntimeException('broker unavailable'));
        $message = app(Outbox::class)->record('orders.placed', ['order_id' => 30]);

        $this->artisan('outbox:publish')->assertFailed();

        $failed = $message->fresh();
        $this->assertSame(1, $failed->attempts);
        $this->assertNotNull($failed->failed_at);
        $this->assertNull($failed->claim_token);
        $this->assertSame('broker unavailable', $failed->last_error);

        $this->artisan('outbox:retry', ['event_id' => $message->event_id])
            ->expectsOutputToContain('Released 1')
            ->assertSuccessful();

        $retried = $message->fresh();
        $this->assertSame(0, $retried->attempts);
        $this->assertNull($retried->failed_at);
        $this->assertNull($retried->last_error);
    }

    public function test_message_pruning_honors_retention_windows(): void
    {
        config([
            'project.outbox.retention.published_hours' => 24,
            'project.outbox.retention.failed_hours' => 48,
            'project.outbox.retention.processed_hours' => 72,
        ]);

        $oldPublished = app(Outbox::class)->record('old.published', []);
        $oldPublished->forceFill(['published_at' => now()->subHours(25)])->save();
        $recentPublished = app(Outbox::class)->record('recent.published', []);
        $recentPublished->forceFill(['published_at' => now()->subHours(2)])->save();
        $oldFailed = app(Outbox::class)->record('old.failed', []);
        $oldFailed->forceFill(['failed_at' => now()->subHours(49)])->save();

        DB::table('processed_messages')->insert([
            'event_id' => (string) Str::uuid(),
            'consumer' => 'old-consumer',
            'processed_at' => now()->subHours(73),
        ]);

        $this->artisan('messages:prune')->assertSuccessful();

        $this->assertDatabaseMissing('outbox_messages', ['event_id' => $oldPublished->event_id]);
        $this->assertDatabaseMissing('outbox_messages', ['event_id' => $oldFailed->event_id]);
        $this->assertDatabaseHas('outbox_messages', ['event_id' => $recentPublished->event_id]);
        $this->assertDatabaseMissing('processed_messages', ['consumer' => 'old-consumer']);
    }
}
