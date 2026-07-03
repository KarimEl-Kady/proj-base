<?php

namespace App\Modules\Auth\Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
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
}
