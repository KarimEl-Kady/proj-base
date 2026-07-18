<?php

namespace App\Modules\Auth\Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('Auth', config('project.modules'))) {
            $this->markTestSkipped('Module [Auth] is disabled.');
        }

        config(['project.auth.driver' => 'sanctum']);
    }

    protected function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'old-password',
        ]);
    }

    public function test_forgot_sends_reset_notification(): void
    {
        Notification::fake();
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])
            ->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_does_not_leak_unknown_emails(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ghost@example.com'])
            ->assertOk();

        Notification::assertNothingSent();
    }

    public function test_reset_changes_the_password(): void
    {
        $user = $this->makeUser();
        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'new-password-123',
        ])->assertOk();
    }

    public function test_reset_rejects_invalid_token(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(422);
    }

    public function test_reset_tokens_are_stored_by_user_uuid(): void
    {
        $user = $this->makeUser();

        Password::createToken($user);

        $this->assertDatabaseHas('password_reset_tokens', [
            'user_uuid' => $user->uuid,
            'email' => $user->email,
        ]);
        $this->assertTrue(Schema::hasColumn('password_reset_tokens', 'user_uuid'));
    }

    public function test_security_upgrade_rebuilds_the_legacy_email_token_table(): void
    {
        Schema::drop('password_reset_tokens');
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
        DB::table('password_reset_tokens')->insert([
            'email' => 'legacy@example.com',
            'token' => 'legacy-token',
        ]);

        $migration = require database_path('migrations/2026_07_18_000001_bind_password_reset_tokens_to_user_uuid.php');
        $migration->up();

        $this->assertTrue(Schema::hasColumn('password_reset_tokens', 'user_uuid'));
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }
}
