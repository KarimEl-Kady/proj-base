<?php

namespace App\Modules\User\Tests\Feature;

use App\Modules\User\Events\UserCreated;
use App\Modules\User\Listeners\RecordUserCreation;
use App\Modules\User\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserCreatedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_user_via_the_api_dispatches_the_domain_event(): void
    {
        $this->actingAsUser('users.create');
        Event::fake([UserCreated::class]);

        $this->postJson('/api/v1/users', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret123',
        ])->assertCreated();

        Event::assertDispatched(
            UserCreated::class,
            fn (UserCreated $event) => $event->user->email === 'alice@example.com'
        );
    }

    public function test_registration_dispatches_the_same_event(): void
    {
        Event::fake([UserCreated::class]);
        config(['project.auth.driver' => 'sanctum']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'secret-password',
        ])->assertCreated();

        Event::assertDispatched(UserCreated::class);
    }

    public function test_the_audit_listener_runs_on_the_queue(): void
    {
        Queue::fake();

        $this->withTestTenant(null, fn () => User::factory()->create());

        Queue::assertPushed(
            CallQueuedListener::class,
            fn (CallQueuedListener $job) => $job->class === RecordUserCreation::class
        );
    }

    public function test_the_audit_listener_writes_the_audit_log_line(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'user.created'
                    && isset($context['event_id'], $context['occurred_at'], $context['user_uuid'])
                    && $context['email'] === 'carol@example.com';
            });

        // Sync queue in tests — the queued listener runs inline.
        $this->withTestTenant(null, fn () => User::factory()->create(['email' => 'carol@example.com']));
    }
}
