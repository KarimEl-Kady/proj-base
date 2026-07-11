<?php

namespace Local\Permission\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Local\Permission\Models\Permission;
use Local\Permission\Models\Role;
use Local\Permission\Support\PermissionRegistry;
use Tests\TestCase;

class PermissionRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_permission_names_for_a_role(): void
    {
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo(Permission::findOrCreate('posts.create'), Permission::findOrCreate('posts.update'));

        $names = app(PermissionRegistry::class)->permissionNamesForRole($role->id);

        $this->assertTrue($names->contains('posts.create'));
        $this->assertTrue($names->contains('posts.update'));
    }

    public function test_unknown_role_id_returns_an_empty_collection(): void
    {
        $names = app(PermissionRegistry::class)->permissionNamesForRole(999999);

        $this->assertTrue($names->isEmpty());
    }

    public function test_role_changes_are_reflected_without_manual_cache_clearing(): void
    {
        $role = Role::findOrCreate('editor');
        $registry = app(PermissionRegistry::class);

        $this->assertTrue($registry->permissionNamesForRole($role->id)->isEmpty());

        $role->givePermissionTo(Permission::findOrCreate('posts.create'));

        // Same registry instance, no manual forgetCache() call — Role's
        // model events already flushed it.
        $this->assertTrue($registry->permissionNamesForRole($role->id)->contains('posts.create'));
    }

    public function test_query_count_is_flat_regardless_of_role_count(): void
    {
        foreach (range(1, 5) as $i) {
            $role = Role::findOrCreate("role-{$i}");
            $role->givePermissionTo(Permission::findOrCreate("permission-{$i}"));
        }

        app(PermissionRegistry::class)->forgetCache();
        $roleIds = Role::query()->pluck('id')->all();

        DB::enableQueryLog();
        $registry = app(PermissionRegistry::class);
        foreach ($roleIds as $roleId) {
            $registry->permissionNamesForRole($roleId);
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // One query loads the whole map; subsequent lookups are in-memory.
        $this->assertCount(1, $queries);
    }

    public function test_disabling_cache_still_returns_correct_data(): void
    {
        config(['permission.cache.enabled' => false]);

        $role = Role::findOrCreate('editor');
        $role->givePermissionTo(Permission::findOrCreate('posts.create'));

        $names = app(PermissionRegistry::class)->permissionNamesForRole($role->id);

        $this->assertTrue($names->contains('posts.create'));
    }

    /**
     * Persistent cache stores refuse to unserialize objects by default
     * (config/cache.php → serializable_classes = false, Laravel's
     * gadget-chain hardening), so the cached payload must be plain
     * arrays/scalars end to end. The in-memory array store used in tests
     * never serializes, which hides violations — assert the strict
     * round-trip directly. Regression test for a real crash: with
     * CACHE_STORE=database, a cached Collection came back to the next PHP
     * process as __PHP_Incomplete_Class and fataled every permission check.
     */
    public function test_cached_payload_survives_strict_object_free_unserialization(): void
    {
        $role = Role::findOrCreate('editor');
        $role->givePermissionTo(Permission::findOrCreate('posts.create'));

        // Warm the cache.
        app(PermissionRegistry::class)->permissionNamesForRole($role->id);

        $cached = cache()->get(config('permission.cache.key'));

        $this->assertIsArray($cached);

        $roundTripped = unserialize(serialize($cached), ['allowed_classes' => false]);
        $this->assertSame($cached, $roundTripped);
        $this->assertSame(['posts.create'], $roundTripped[$role->id]);
    }
}
