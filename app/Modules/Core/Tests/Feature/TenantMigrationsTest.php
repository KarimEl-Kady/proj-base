<?php

namespace App\Modules\Core\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantMigrationsTest extends TestCase
{
    use RefreshDatabase;

    protected string $probePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->probePath = module_path('TenantProbe');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('tenant_probes');
        Schema::dropIfExists('macro_probes');
        File::deleteDirectory($this->probePath);
        File::delete(glob(module_path('User', 'Database/Migrations').'/*_add_tenant_id_to_users_table.php'));

        parent::tearDown();
    }

    // ── tenantColumn() macro ─────────────────────────────────────────

    public function test_macro_adds_the_tenant_column_in_multi_mode(): void
    {
        config(['project.tenancy.mode' => 'multi']);

        Schema::create('macro_probes', function (Blueprint $table) {
            $table->id();
            $table->tenantColumn();
        });

        $this->assertTrue(Schema::hasColumn('macro_probes', 'tenant_id'));
    }

    public function test_macro_respects_a_custom_tenant_column_name(): void
    {
        config(['project.tenancy.mode' => 'multi', 'project.tenancy.tenant_column' => 'org_id']);

        Schema::create('macro_probes', function (Blueprint $table) {
            $table->id();
            $table->tenantColumn();
        });

        $this->assertTrue(Schema::hasColumn('macro_probes', 'org_id'));
        $this->assertFalse(Schema::hasColumn('macro_probes', 'tenant_id'));
    }

    public function test_macro_adds_the_tenant_column_in_single_mode(): void
    {
        config(['project.tenancy.mode' => 'single']);

        Schema::create('macro_probes', function (Blueprint $table) {
            $table->id();
            $table->tenantColumn();
        });

        $this->assertTrue(Schema::hasColumn('macro_probes', 'tenant_id'));
    }

    public function test_macro_is_a_no_op_in_none_mode(): void
    {
        config(['project.tenancy.mode' => 'none']);

        Schema::create('macro_probes', function (Blueprint $table) {
            $table->id();
            $table->tenantColumn();
        });

        $this->assertFalse(Schema::hasColumn('macro_probes', 'tenant_id'));
    }

    // ── tenant:migrations command ────────────────────────────────────

    public function test_command_is_a_no_op_in_none_mode(): void
    {
        config(['project.tenancy.mode' => 'none']);

        $this->artisan('tenant:migrations')
            ->expectsOutputToContain('Tenancy mode is [none]')
            ->assertSuccessful();
    }

    public function test_command_reports_ok_for_tables_that_already_have_the_column(): void
    {
        config(['project.tenancy.mode' => 'multi']);

        // Simulate a users table created while already in multi mode:
        // the base migration's tenantColumn() macro is a no-op in the
        // single-mode test environment, so add the column by hand.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->index();
        });

        $this->artisan('tenant:migrations')->assertSuccessful();

        $this->assertSame(
            [],
            glob(module_path('User', 'Database/Migrations').'/*_add_tenant_id_to_users_table.php'),
            'No catch-up migration should be generated for a table that already has the column.'
        );
    }

    public function test_command_generates_catch_up_migration_and_does_not_duplicate_it(): void
    {
        config(['project.tenancy.mode' => 'multi']);

        $this->makeProbeModule();
        config(['project.modules' => [...config('project.modules'), 'TenantProbe']]);

        // Table created single-tenant style: no tenant column.
        Schema::create('tenant_probes', function (Blueprint $table) {
            $table->id();
        });

        $this->artisan('tenant:migrations')
            ->expectsOutputToContain('migration created')
            ->assertSuccessful();

        $generated = glob("{$this->probePath}/Database/Migrations/*_add_tenant_id_to_tenant_probes_table.php");
        $this->assertCount(1, $generated);

        $content = file_get_contents($generated[0]);
        $this->assertStringContainsString("Schema::table('tenant_probes'", $content);
        $this->assertStringContainsString("foreignId('tenant_id')->nullable()->index()", $content);
        $this->assertStringContainsString("dropColumn('tenant_id')", $content);

        // Second run must not create a second file.
        $this->artisan('tenant:migrations')
            ->expectsOutputToContain('migration already generated')
            ->assertSuccessful();

        $this->assertCount(
            1,
            glob("{$this->probePath}/Database/Migrations/*_add_tenant_id_to_tenant_probes_table.php")
        );
    }

    protected function makeProbeModule(): void
    {
        File::ensureDirectoryExists("{$this->probePath}/Models");
        File::ensureDirectoryExists("{$this->probePath}/Database/Migrations");

        File::put("{$this->probePath}/Models/TenantProbe.php", <<<'PHP'
        <?php

        namespace App\Modules\TenantProbe\Models;

        use App\Modules\Core\Models\Model;
        use App\Modules\Core\Traits\HasTenantScope;

        class TenantProbe extends Model
        {
            use HasTenantScope;

            protected $table = 'tenant_probes';
        }
        PHP);
    }
}
