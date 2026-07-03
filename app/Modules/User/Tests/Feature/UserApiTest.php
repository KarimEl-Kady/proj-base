<?php

namespace App\Modules\User\Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    // ── CRUD ─────────────────────────────────────────────────────────

    public function test_index_returns_paginated_users(): void
    {
        $this->makeUser();

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['data', 'links', 'meta']]);
    }

    public function test_store_creates_a_user_with_uuid(): void
    {
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
        $this->postJson('/api/v1/users', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_show_returns_enveloped_404_for_unknown_uuid(): void
    {
        $this->getJson('/api/v1/users/'.Str::uuid())
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_show_finds_a_user_by_uuid(): void
    {
        $user = $this->makeUser();

        $this->getJson("/api/v1/users/{$user->uuid}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->uuid);
    }

    public function test_update_modifies_a_user(): void
    {
        $user = $this->makeUser();

        $this->putJson("/api/v1/users/{$user->uuid}", ['name' => 'Updated'])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated']);
    }

    public function test_destroy_deletes_a_user(): void
    {
        $user = $this->makeUser();

        $this->deleteJson("/api/v1/users/{$user->uuid}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    // ── Fetch pipeline ───────────────────────────────────────────────

    public function test_word_filter_searches_searchable_columns(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        $response = $this->getJson('/api/v1/users?word=alice');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame('Alice', $response->json('data.data.0.name'));
    }

    public function test_pagination_false_returns_full_set(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        $response = $this->getJson('/api/v1/users?pagination=false');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
        $this->assertNull($response->json('data.meta'));
    }

    public function test_per_page_is_respected_and_capped(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        $response = $this->getJson('/api/v1/users?per_page=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame(1, $response->json('data.meta.per_page'));

        $this->getJson('/api/v1/users?per_page=99999')->assertStatus(422);
    }

    public function test_sorting_is_whitelisted(): void
    {
        $this->makeUser('Alice', 'alice@example.com');
        $this->makeUser('Bob', 'bob@example.com');

        $response = $this->getJson('/api/v1/users?sort_by=id&sort_dir=asc');
        $response->assertOk();
        $this->assertSame('Alice', $response->json('data.data.0.name'));

        // non-whitelisted column is ignored, invalid direction is rejected
        $this->getJson('/api/v1/users?sort_by=password')->assertOk();
        $this->getJson('/api/v1/users?sort_dir=sideways')->assertStatus(422);
    }
}
