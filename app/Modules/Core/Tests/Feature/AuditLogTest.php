<?php

namespace App\Modules\Core\Tests\Feature;

use App\Modules\Core\Models\AuditLog;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Uses User (App\Modules\Core\Traits\Auditable) as the living example —
 * see AGENTS.md's "Events & listeners" for why this codebase prefers a real
 * shipped module over a synthetic fixture to demonstrate a pattern.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_user_writes_an_audit_log_without_the_password(): void
    {
        $user = User::factory()->create(['password' => 'correct horse battery staple']);

        $log = AuditLog::query()->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', $user->id)
            ->where('action', 'created')
            ->firstOrFail();

        $this->assertSame([], $log->changes['before']);
        $this->assertArrayHasKey('email', $log->changes['after']);
        $this->assertArrayNotHasKey('password', $log->changes['after']);
        $this->assertArrayNotHasKey('remember_token', $log->changes['after']);
        $this->assertArrayNotHasKey('two_factor_secret', $log->changes['after']);
        $this->assertNull($log->actor_id);
    }

    public function test_updating_a_user_records_only_the_changed_fields(): void
    {
        $user = User::factory()->create(['name' => 'Original Name']);
        AuditLog::query()->truncate();

        $user->update(['name' => 'Updated Name']);

        $log = AuditLog::query()->where('auditable_id', $user->id)->where('action', 'updated')->firstOrFail();

        $this->assertSame(['name' => 'Original Name'], $log->changes['before']);
        $this->assertSame(['name' => 'Updated Name'], $log->changes['after']);
    }

    public function test_a_no_op_save_writes_no_audit_log(): void
    {
        $user = User::factory()->create();
        AuditLog::query()->truncate();

        $user->save();

        $this->assertSame(0, AuditLog::query()->where('auditable_id', $user->id)->count());
    }

    public function test_deleting_a_user_records_the_final_state(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;
        AuditLog::query()->truncate();

        $user->delete();

        $log = AuditLog::query()->where('auditable_id', $userId)->where('action', 'deleted')->firstOrFail();

        $this->assertSame([], $log->changes['after']);
        $this->assertArrayHasKey('email', $log->changes['before']);
    }

    public function test_the_authenticated_actor_is_recorded(): void
    {
        $actor = User::factory()->create();
        Auth::login($actor);

        $subject = User::factory()->create();

        $log = AuditLog::query()->where('auditable_id', $subject->id)->where('action', 'created')->firstOrFail();

        $this->assertSame($actor->id, $log->actor_id);
        $this->assertSame($actor->getMorphClass(), $log->actor_type);
    }
}
