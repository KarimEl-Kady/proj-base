<?php

namespace Local\Permission\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Local\Permission\Models\Role;
use Local\Permission\Support\DefinitionLoader;
use Tests\TestCase;

class DefinitionLoaderTest extends TestCase
{
    use RefreshDatabase;

    protected string $definitionDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->definitionDir = storage_path('framework/testing/permission-definitions');
        File::ensureDirectoryExists($this->definitionDir);

        config([
            'permission.definitions' => [
                'permissions' => ['reports.view'],
                'roles' => ['admin' => ['*']],
            ],
            'permission.definition_paths' => [
                'storage/framework/testing/permission-definitions/*.php',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->definitionDir);

        parent::tearDown();
    }

    protected function writeDefinitionFile(string $name, array $definition): void
    {
        File::put(
            "{$this->definitionDir}/{$name}.php",
            '<?php return '.var_export($definition, true).';'
        );
    }

    protected function loader(): DefinitionLoader
    {
        return app(DefinitionLoader::class);
    }

    public function test_permissions_merge_central_config_with_definition_files(): void
    {
        $this->writeDefinitionFile('blog', ['permissions' => ['posts.view', 'posts.manage']]);

        $this->assertSame(
            ['reports.view', 'posts.view', 'posts.manage'],
            $this->loader()->permissions()
        );
    }

    public function test_duplicate_permissions_across_sources_are_deduplicated(): void
    {
        $this->writeDefinitionFile('blog', ['permissions' => ['reports.view', 'posts.view']]);

        $this->assertSame(
            ['reports.view', 'posts.view'],
            $this->loader()->permissions()
        );
    }

    public function test_same_named_roles_union_their_permission_lists(): void
    {
        config(['permission.definitions.roles' => ['manager' => ['reports.view']]]);
        $this->writeDefinitionFile('blog', ['roles' => ['manager' => ['posts.view']]]);

        $this->assertSame(
            ['manager' => ['reports.view', 'posts.view']],
            $this->loader()->roles()
        );
    }

    public function test_a_wildcard_from_any_source_survives_the_merge(): void
    {
        $this->writeDefinitionFile('blog', ['roles' => ['admin' => ['posts.manage']]]);

        $this->assertContains('*', $this->loader()->roles()['admin']);
    }

    public function test_files_may_declare_new_roles(): void
    {
        $this->writeDefinitionFile('blog', ['roles' => ['editor' => ['posts.manage']]]);

        $this->assertSame(['posts.manage'], $this->loader()->roles()['editor']);
    }

    public function test_no_matching_files_falls_back_to_central_config_only(): void
    {
        $this->assertSame(['reports.view'], $this->loader()->permissions());
        $this->assertSame(['admin' => ['*']], $this->loader()->roles());
    }

    public function test_seed_command_creates_permissions_declared_in_definition_files(): void
    {
        $this->writeDefinitionFile('blog', ['permissions' => ['posts.manage']]);

        $this->artisan('permission:seed')->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['name' => 'posts.manage']);

        // '*' expands across sources: admin gets file-declared permissions too.
        $admin = Role::findByName('admin');
        $this->assertTrue($admin->hasPermissionTo('posts.manage'));
    }
}
