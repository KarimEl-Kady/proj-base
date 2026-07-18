<?php

namespace App\Modules\User\Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('User', config('project.modules'))) {
            $this->markTestSkipped('Module [User] is disabled.');
        }
    }

    protected function makeUser(string $name = 'Alice', string $email = 'alice@example.com'): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'secret123',
        ]);
    }

    /**
     * All /api/v1/users routes require auth:sanctum plus a users.* permission
     * per action. Pass a user to act as one of the fixtures (keeps user
     * counts in listing assertions exact), and/or a narrower permission set
     * to test the authorization boundary itself.
     *
     * @param  string|array<int, string>  $permissions
     */
    protected function authenticate(
        ?User $user = null,
        string|array $permissions = ['users.view', 'users.create', 'users.update', 'users.delete'],
    ): User {
        $user ??= $this->makeUser('Admin', 'admin@example.com');

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        Sanctum::actingAs($user);

        return $user;
    }

    // ── Authentication & authorization gates ──────────────────────────

    public function test_guests_are_rejected_on_every_endpoint(): void
    {
        $uuid = (string) Str::uuid();

        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->postJson('/api/v1/users', [])->assertUnauthorized();
        $this->getJson("/api/v1/users/{$uuid}")->assertUnauthorized();
        $this->putJson("/api/v1/users/{$uuid}", [])->assertUnauthorized();
        $this->deleteJson("/api/v1/users/{$uuid}")->assertUnauthorized();
    }

    public function test_authenticated_users_without_the_matching_permission_are_forbidden(): void
    {
        $uuid = (string) Str::uuid();
        $this->authenticate(permissions: []);

        $this->getJson('/api/v1/users')->assertForbidden();
        $this->postJson('/api/v1/users', [])->assertForbidden();
        $this->getJson("/api/v1/users/{$uuid}")->assertForbidden();
        $this->putJson("/api/v1/users/{$uuid}", [])->assertForbidden();
        $this->deleteJson("/api/v1/users/{$uuid}")->assertForbidden();
    }

    public function test_permissions_are_checked_per_action_not_as_a_bundle(): void
    {
        $user = $this->makeUser();
        $this->authenticate($user, permissions: 'users.view');

        // Granted: users.view
        $this->getJson("/api/v1/users/{$user->uuid}")->assertOk();

        // Not granted: users.delete
        $this->deleteJson("/api/v1/users/{$user->uuid}")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    // ── CRUD ─────────────────────────────────────────────────────────

    public function test_index_returns_paginated_users(): void
    {
        $this->authenticate();

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data', 'links', 'meta']]);
    }

    public function test_store_creates_a_user_with_uuid(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'secret123',
        ]);

        $response->assertCreated();
        $this->assertTrue(Str::isUuid($response->json('data.id')));
        $this->assertDatabaseHas('users', ['email' => 'bob@example.com']);
    }

    public function test_store_validates_input(): void
    {
        $this->authenticate();

        $this->postJson('/api/v1/users', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_show_returns_enveloped_404_for_unknown_uuid(): void
    {
        $this->authenticate();

        $this->getJson('/api/v1/users/'.Str::uuid())
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_show_finds_a_user_by_uuid(): void
    {
        $user = $this->makeUser();
        $this->authenticate($user);

        $this->getJson("/api/v1/users/{$user->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->uuid);
    }

    public function test_update_modifies_a_user(): void
    {
        $user = $this->makeUser();
        $this->authenticate($user);

        $this->putJson("/api/v1/users/{$user->uuid}", ['name' => 'Updated'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated']);
    }

    public function test_update_accepts_the_current_email_with_uuid_routes(): void
    {
        $user = $this->makeUser();
        $this->authenticate($user);

        $this->putJson("/api/v1/users/{$user->uuid}", [
            'name' => 'Updated',
            'email' => $user->email,
        ])->assertOk();
    }

    public function test_destroy_deletes_a_user(): void
    {
        $user = $this->makeUser();
        $this->authenticate();

        $this->deleteJson("/api/v1/users/{$user->uuid}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // ── Fetch pipeline ───────────────────────────────────────────────

    public function test_word_filter_searches_searchable_columns(): void
    {
        $alice = $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');
        $this->authenticate($alice);

        $response = $this->getJson('/api/v1/users?word=alice');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame('Alice', $response->json('data.data.0.name'));
    }

    public function test_word_filter_treats_like_wildcards_as_literals(): void
    {
        $literal = $this->makeUser('100% Cotton', 'cotton@example.com');
        $this->makeUser('100x Cotton', 'cottonx@example.com');
        $this->authenticate($literal);

        // Unescaped, "100%" would LIKE-match both rows; escaped it must
        // match only the literal percent sign.
        $response = $this->getJson('/api/v1/users?word='.urlencode('100%'));

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame('100% Cotton', $response->json('data.data.0.name'));
    }

    public function test_pagination_false_returns_full_set(): void
    {
        $alice = $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');
        $this->authenticate($alice);

        $response = $this->getJson('/api/v1/users?pagination=false');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
        $this->assertNull($response->json('data.meta'));
    }

    public function test_pagination_false_is_bounded_by_the_unpaginated_cap(): void
    {
        config(['project.pagination.unpaginated_cap' => 2]);

        $alice = $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');
        $this->makeUser('Carol', 'carol@example.com');
        $this->authenticate($alice);

        $response = $this->getJson('/api/v1/users?pagination=false');

        // The toggle can never pull an unbounded table into memory.
        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_per_page_is_respected_and_capped(): void
    {
        $alice = $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');
        $this->authenticate($alice);

        $response = $this->getJson('/api/v1/users?per_page=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame(1, $response->json('data.meta.per_page'));

        $this->getJson('/api/v1/users?per_page=99999')->assertStatus(422);
    }

    public function test_sorting_is_whitelisted(): void
    {
        $alice = $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');
        $this->authenticate($alice);

        $response = $this->getJson('/api/v1/users?sort_by=id&sort_dir=asc');
        $response->assertOk();
        $this->assertSame('Alice', $response->json('data.data.0.name'));

        // non-whitelisted column is ignored, invalid direction is rejected
        $this->getJson('/api/v1/users?sort_by=password')->assertOk();
        $this->getJson('/api/v1/users?sort_dir=sideways')->assertStatus(422);
    }
}
