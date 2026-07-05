<?php

namespace Local\Permission\Tests;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Local\Permission\Models\Permission;
use Tests\TestCase;

class PermissionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_or_create_is_idempotent(): void
    {
        $first = Permission::findOrCreate('users.create');
        $second = Permission::findOrCreate('users.create');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Permission::query()->count());
    }

    public function test_find_by_name_throws_when_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Permission::findByName('does.not.exist');
    }

    public function test_name_is_unique_per_guard(): void
    {
        Permission::query()->create(['name' => 'edit', 'guard_name' => 'web']);
        Permission::query()->create(['name' => 'edit', 'guard_name' => 'api']);

        $this->assertSame(2, Permission::query()->where('name', 'edit')->count());
    }

    public function test_duplicate_name_and_guard_is_rejected_by_the_database(): void
    {
        Permission::query()->create(['name' => 'edit', 'guard_name' => null]);

        $this->expectException(QueryException::class);

        Permission::query()->create(['name' => 'edit', 'guard_name' => null]);
    }
}
