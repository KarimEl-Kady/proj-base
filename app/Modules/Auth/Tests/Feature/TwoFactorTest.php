<?php

namespace App\Modules\Auth\Tests\Feature;

use App\Modules\Auth\Jobs\SendSecurityAlert;
use App\Modules\Auth\Support\Totp;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('Auth', config('project.modules'))) {
            $this->markTestSkipped('Module [Auth] is disabled.');
        }

        config([
            'project.auth.driver' => 'sanctum',
            'project.features.two_factor_auth' => true,
        ]);
    }

    protected function registerAndGetToken(): string
    {
        return $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret-password',
        ])->json('data.token');
    }

    protected function enableTwoFactor(string $token): string
    {
        $secret = $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'secret-password'])
            ->json('data.secret');

        $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/confirm', ['code' => Totp::code($secret)])
            ->assertOk();

        return $secret;
    }

    public function test_enable_and_confirm_two_factor(): void
    {
        $token = $this->registerAndGetToken();

        $response = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable', [
            'current_password' => 'secret-password',
        ]);
        $response->assertOk()->assertJsonStructure(['data' => ['secret', 'uri']]);

        $secret = $response->json('data.secret');

        $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/confirm', ['code' => Totp::code($secret)])
            ->assertOk();

        $this->assertTrue(User::query()->first()->hasTwoFactorEnabled());
    }

    public function test_confirm_rejects_invalid_code(): void
    {
        $token = $this->registerAndGetToken();
        $this->withToken($token)->postJson('/api/v1/auth/2fa/enable', [
            'current_password' => 'secret-password',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000'])
            ->assertStatus(422);
    }

    public function test_login_requires_code_when_two_factor_enabled(): void
    {
        $token = $this->registerAndGetToken();
        $secret = $this->enableTwoFactor($token);

        $credentials = ['email' => 'alice@example.com', 'password' => 'secret-password'];

        $this->postJson('/api/v1/auth/login', $credentials)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->postJson('/api/v1/auth/login', $credentials + ['code' => Totp::code($secret)])
            ->assertOk();
    }

    public function test_disable_two_factor(): void
    {
        $token = $this->registerAndGetToken();
        $secret = $this->enableTwoFactor($token);

        $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/disable', ['code' => Totp::code($secret)])
            ->assertOk();

        $this->assertFalse(User::query()->first()->hasTwoFactorEnabled());
    }

    public function test_endpoints_are_flag_gated(): void
    {
        $token = $this->registerAndGetToken();
        config(['project.features.two_factor_auth' => false]);

        $this->withToken($token)->postJson('/api/v1/auth/2fa/enable', [
            'current_password' => 'secret-password',
        ])->assertForbidden();
    }

    public function test_confirming_two_factor_sends_a_security_alert(): void
    {
        Queue::fake();
        $token = $this->registerAndGetToken();

        $secret = $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/enable', ['current_password' => 'secret-password'])
            ->json('data.secret');

        $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/confirm', ['code' => Totp::code($secret)])
            ->assertOk();

        Queue::assertPushed(SendSecurityAlert::class, fn (SendSecurityAlert $job) => $job->email === 'alice@example.com');
    }

    public function test_disabling_two_factor_sends_a_security_alert(): void
    {
        $token = $this->registerAndGetToken();
        $secret = $this->enableTwoFactor($token);

        Queue::fake();

        $this->withToken($token)
            ->postJson('/api/v1/auth/2fa/disable', ['code' => Totp::code($secret)])
            ->assertOk();

        Queue::assertPushed(SendSecurityAlert::class, fn (SendSecurityAlert $job) => $job->email === 'alice@example.com');
    }

    public function test_account_email_and_password_changes_require_a_code_once_two_factor_is_enabled(): void
    {
        $token = $this->registerAndGetToken();
        $this->enableTwoFactor($token);

        $this->withToken($token)->putJson('/api/v1/auth/account/email', [
            'email' => 'new@example.com',
            'current_password' => 'secret-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->withToken($token)->putJson('/api/v1/auth/account/password', [
            'current_password' => 'secret-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
    }
}
