<?php

namespace App\Modules\Auth\Tests\Feature;

use App\Modules\Auth\Jobs\SendSecurityAlert;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthApiTest extends TestCase
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

    protected function registerPayload(): array
    {
        return [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret-password',
        ];
    }

    public function test_register_creates_user_and_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user' => ['id'], 'token', 'token_type', 'expires_in']]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_register_is_blocked_when_feature_disabled(): void
    {
        config(['project.features.registration' => false]);

        $this->postJson('/api/v1/auth/register', $this->registerPayload())
            ->assertForbidden();
    }

    public function test_register_validates_input(): void
    {
        $this->postJson('/api/v1/auth/register', ['email' => 'nope'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_login_returns_bearer_token(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_email_identity_is_case_insensitive(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice',
            'email' => ' Alice@Example.COM ',
            'password' => 'secret-password',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate',
            'email' => 'alice@example.com',
            'password' => 'secret-password',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'ALICE@EXAMPLE.COM',
            'password' => 'secret-password',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registerPayload());

        $this->postJson('/api/v1/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_me_returns_authenticated_user_and_requires_auth(): void
    {
        $token = $this->postJson('/api/v1/auth/register', $this->registerPayload())->json('data.token');

        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.name', 'Alice');
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = $this->postJson('/api/v1/auth/register', $this->registerPayload())->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_named_personal_access_tokens_are_flag_gated(): void
    {
        config(['project.features.personal_access_tokens' => false]);
        $token = $this->postJson('/api/v1/auth/register', $this->registerPayload())->json('data.token');

        $tokenPayload = ['name' => 'ci', 'current_password' => 'secret-password'];

        $this->withToken($token)->postJson('/api/v1/auth/tokens', $tokenPayload)->assertForbidden();

        config(['project.features.personal_access_tokens' => true]);

        $created = $this->withToken($token)->postJson('/api/v1/auth/tokens', $tokenPayload);
        $created->assertCreated();
        $namedToken = $created->json('data.token');
        $this->assertNotEmpty($namedToken);

        // The server-side default is API-only; a named integration token
        // cannot manage credentials/tokens unless account:manage was
        // explicitly requested during its password-confirmed creation.
        Auth::forgetGuards();
        $this->withToken($namedToken)->getJson('/api/v1/auth/tokens')->assertForbidden();

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/tokens')->assertOk();

        $user = User::query()->where('email', 'alice@example.com')->first();
        $namedTokenId = $user->tokens()->where('name', 'ci')->value('id');

        Auth::forgetGuards();
        $this->withToken($token)->deleteJson("/api/v1/auth/tokens/{$namedTokenId}")->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $namedTokenId]);
    }

    public function test_named_token_abilities_are_allowlisted(): void
    {
        config(['project.features.personal_access_tokens' => true]);
        $token = $this->postJson('/api/v1/auth/register', $this->registerPayload())->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/tokens', [
            'name' => 'unsafe',
            'current_password' => 'secret-password',
            'abilities' => ['root'],
        ])->assertUnprocessable();
    }

    public function test_sensitive_account_changes_require_password_and_revoke_tokens(): void
    {
        Queue::fake();
        $token = $this->postJson('/api/v1/auth/register', $this->registerPayload())->json('data.token');

        $this->withToken($token)->putJson('/api/v1/auth/account/email', [
            'email' => 'new@example.com',
            'current_password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->withToken($token)->putJson('/api/v1/auth/account/email', [
            'email' => 'new@example.com',
            'current_password' => 'secret-password',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Queue::assertPushed(SendSecurityAlert::class, fn (SendSecurityAlert $job) => $job->email === 'alice@example.com');
    }

    public function test_change_password_requires_current_password_and_revokes_tokens(): void
    {
        Queue::fake();
        $token = $this->postJson('/api/v1/auth/register', $this->registerPayload())->json('data.token');

        $this->withToken($token)->putJson('/api/v1/auth/account/password', [
            'current_password' => 'wrong-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertUnprocessable();

        $this->withToken($token)->putJson('/api/v1/auth/account/password', [
            'current_password' => 'secret-password',
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertTrue(password_verify('a-brand-new-password', User::query()->firstOrFail()->password));
        Queue::assertPushed(SendSecurityAlert::class, fn (SendSecurityAlert $job) => $job->email === 'alice@example.com');
    }

    public function test_change_password_rejects_reusing_the_current_password(): void
    {
        $token = $this->postJson('/api/v1/auth/register', $this->registerPayload())->json('data.token');

        $this->withToken($token)->putJson('/api/v1/auth/account/password', [
            'current_password' => 'secret-password',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_profile_update_cannot_change_identity_credentials(): void
    {
        $token = $this->postJson('/api/v1/auth/register', $this->registerPayload())->json('data.token');
        $user = User::query()->firstOrFail();

        $this->withToken($token)->putJson("/api/v1/users/{$user->uuid}", [
            'name' => 'Updated',
            'email' => 'attacker@example.com',
            'password' => 'attacker-password',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated',
            'email' => 'alice@example.com',
        ]);
        $this->assertTrue(password_verify('secret-password', $user->fresh()->password));
    }
}
