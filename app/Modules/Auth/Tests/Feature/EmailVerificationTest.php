<?php

namespace App\Modules\Auth\Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
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
            'project.features.email_verification' => true,
        ]);
    }

    protected function registerUser(): array
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret-password',
        ]);

        return [
            User::query()->where('email', 'alice@example.com')->first(),
            $response->json('data.token'),
        ];
    }

    public function test_registration_sends_verification_email_when_enabled(): void
    {
        Notification::fake();

        [$user] = $this->registerUser();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_endpoint_sends_verification_email(): void
    {
        [$user, $token] = $this->registerUser();
        Notification::fake();

        $this->withToken($token)->postJson('/api/v1/auth/email/resend')->assertOk();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_link_verifies_the_email(): void
    {
        [$user] = $this->registerUser();
        $this->assertFalse($user->hasVerifiedEmail());

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->getJson($url)->assertOk();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_tampered_link_is_rejected(): void
    {
        [$user] = $this->registerUser();

        $this->getJson("/api/v1/auth/email/verify/{$user->getKey()}/bad-hash")
            ->assertForbidden();
    }

    public function test_endpoints_are_flag_gated(): void
    {
        [, $token] = $this->registerUser();
        config(['project.features.email_verification' => false]);

        $this->withToken($token)->postJson('/api/v1/auth/email/resend')->assertForbidden();
    }
}
