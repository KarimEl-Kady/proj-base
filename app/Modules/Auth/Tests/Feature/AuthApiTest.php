<?php

namespace App\Modules\Auth\Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $token = $this->postJson('/api/v1/auth/register', $this->registerPayload())->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/tokens', ['name' => 'ci'])->assertForbidden();

        config(['project.features.personal_access_tokens' => true]);

        $created = $this->withToken($token)->postJson('/api/v1/auth/tokens', ['name' => 'ci']);
        $created->assertCreated();
        $this->assertNotEmpty($created->json('data.token'));

        $this->withToken($token)->getJson('/api/v1/auth/tokens')->assertOk();

        $user = User::query()->where('email', 'alice@example.com')->first();
        $namedTokenId = $user->tokens()->where('name', 'ci')->value('id');

        $this->withToken($token)->deleteJson("/api/v1/auth/tokens/{$namedTokenId}")->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $namedTokenId]);
    }
}
