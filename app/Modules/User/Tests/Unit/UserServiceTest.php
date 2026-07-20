<?php

namespace App\Modules\User\Tests\Unit;

use App\Modules\User\Models\User;
use App\Modules\User\Repositories\UserRepository;
use App\Modules\User\Services\UserService;
use Tests\TestCase;

/**
 * Reference example for isolated service tests (see AGENTS.md's Testing
 * section): mock the repository, resolve the service from the container,
 * assert delegation — no database, no HTTP, no RefreshDatabase. Services in
 * this codebase use constructor injection specifically so this is possible;
 * reach for this shape instead of a full Feature test whenever what's under
 * test is the service's own logic, not the request/response/DB round trip.
 */
class UserServiceTest extends TestCase
{
    public function test_create_delegates_to_the_repository_and_returns_its_result(): void
    {
        $data = ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'password' => 'irrelevant-here'];
        $created = new User($data);

        $this->mock(UserRepository::class, function ($mock) use ($data, $created) {
            $mock->shouldReceive('create')->once()->with($data)->andReturn($created);
        });

        $result = $this->app->make(UserService::class)->create($data);

        $this->assertSame($created, $result);
    }

    public function test_find_by_email_delegates_to_the_repository(): void
    {
        $user = new User(['email' => 'ada@example.com']);

        $this->mock(UserRepository::class, function ($mock) use ($user) {
            $mock->shouldReceive('findByEmail')->once()->with('ada@example.com')->andReturn($user);
        });

        $result = $this->app->make(UserService::class)->findByEmail('ada@example.com');

        $this->assertSame($user, $result);
    }

    public function test_find_by_email_returns_null_when_the_repository_finds_nothing(): void
    {
        $this->mock(UserRepository::class, function ($mock) {
            $mock->shouldReceive('findByEmail')->once()->with('missing@example.com')->andReturnNull();
        });

        $result = $this->app->make(UserService::class)->findByEmail('missing@example.com');

        $this->assertNull($result);
    }
}
