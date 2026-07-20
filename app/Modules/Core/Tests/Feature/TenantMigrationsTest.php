<?php

namespace App\Modules\Core\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        Schema::dropIfExists('organizations');
        File::deleteDirectory($this->probePath);

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

    public function test_macro_references_the_configured_tenant_models_table(): void
    {
        config([
            'project.tenancy.mode' => 'multi',
            'project.tenancy.tenant_model' => TenantModelProbe::class,
        ]);

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('macro_probes', function (Blueprint $table) {
            $table->id();
            $table->tenantColumn();
        });

        $foreignKeys = DB::select("PRAGMA foreign_key_list('macro_probes')");

        $this->assertSame('organizations', $foreignKeys[0]->table);
    }

    /**
     * The macro no longer forks on tenancy mode (see its docblock in
     * CoreServiceProvider): every table created with $table->tenantColumn()
     * gets the column regardless of mode, so switching modes later is a
     * config + backfill change, never a schema migration.
     */
    public function test_macro_adds_the_tenant_column_in_none_mode_too(): void
    {
        config(['project.tenancy.mode' => 'none']);

        Schema::create('macro_probes', function (Blueprint $table) {
            $table->id();
            $table->tenantColumn();
        });

        $this->assertTrue(Schema::hasColumn('macro_probes', 'tenant_id'));
    }

    // ── tenant:migrations command ────────────────────────────────────

    /**
     * The command no longer special-cases "none" mode: real tables already
     * carry the column unconditionally (via tenantColumn()), so this is
     * purely a retrofit tool for hand-written migrations that skipped the
     * macro — equally useful in any mode.
     */
    public function test_command_reports_ok_for_the_real_users_table_in_every_mode(): void
    {
        foreach (['none', 'single', 'multi'] as $mode) {
            config(['project.tenancy.mode' => $mode]);

            // --module=User: this test only cares about the "ok" status, so
            // it never touches any other module's real filesystem path.
            $this->artisan('tenant:migrations', ['--module' => ['User']])->assertSuccessful();

            $this->assertSame(
                [],
                glob(module_path('User', 'Database/Migrations').'/*_add_tenant_id_to_users_table.php'),
                "No catch-up migration should be generated in [{$mode}] mode for a table that already has the column."
            );
        }
    }

    public function test_command_generates_a_catch_up_migration_in_none_mode_for_a_hand_written_table(): void
    {
        config(['project.tenancy.mode' => 'none']);
        $this->makeProbeModule();
        config(['project.modules' => [...config('project.modules'), 'TenantProbe']]);

        // A hand-written migration that skipped $table->tenantColumn().
        Schema::create('tenant_probes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
        });

        $this->artisan('tenant:migrations', ['--module' => ['TenantProbe']])
            ->expectsOutputToContain('migration created')
            ->assertSuccessful();

        $this->assertCount(
            1,
            glob("{$this->probePath}/Database/Migrations/*_add_tenant_id_to_tenant_probes_table.php")
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
            $table->string('code')->unique();
        });

        // --module=TenantProbe: without this, the command would also process
        // every other active module's tenant-scoped models (e.g. User) and
        // attempt to write a catch-up migration into their real, live
        // Database/Migrations directory as a side effect of this test.
        $this->artisan('tenant:migrations', ['--module' => ['TenantProbe']])
            ->expectsOutputToContain('migration created')
            ->assertSuccessful();

        $generated = glob("{$this->probePath}/Database/Migrations/*_add_tenant_id_to_tenant_probes_table.php");
        $this->assertCount(1, $generated);

        $content = file_get_contents($generated[0]);
        $this->assertStringContainsString("Schema::table('tenant_probes'", $content);
        $this->assertStringContainsString("foreignId('tenant_id')->nullable()->index()", $content);
        $this->assertStringContainsString("dropUnique(['code'])", $content);
        $this->assertStringContainsString("unique(['tenant_id', 'code'])", $content);
        $this->assertStringContainsString("dropColumn('tenant_id')", $content);
        $this->assertInstanceOf(Migration::class, require $generated[0]);

        // Second run must not create a second file.
        $this->artisan('tenant:migrations', ['--module' => ['TenantProbe']])
            ->expectsOutputToContain('migration already generated')
            ->assertSuccessful();

        $this->assertCount(
            1,
            glob("{$this->probePath}/Database/Migrations/*_add_tenant_id_to_tenant_probes_table.php")
        );
    }

    public function test_module_option_narrows_which_modules_are_scanned(): void
    {
        config(['project.tenancy.mode' => 'multi']);

        $this->artisan('tenant:migrations', ['--module' => ['TenantProbe']])
            ->expectsOutputToContain('No models using HasTenantScope found in active modules.')
            ->assertSuccessful();
    }

    public function test_backfill_assigns_legacy_rows_and_check_detects_readiness(): void
    {
        config(['project.tenancy.mode' => 'multi']);
        $this->makeProbeModule();
        config(['project.modules' => [...config('project.modules'), 'TenantProbe']]);
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);

        Schema::create('tenant_probes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->string('code');
        });
        DB::table('tenant_probes')->insert(['code' => 'legacy']);

        $this->artisan('tenant:check', ['--module' => ['TenantProbe']])->assertFailed();
        $this->artisan('tenant:backfill', [
            '--module' => ['TenantProbe'],
            '--tenant' => 'acme',
            '--force' => true,
        ])->assertSuccessful();
        $this->artisan('tenant:check', ['--module' => ['TenantProbe']])->assertSuccessful();

        $this->assertDatabaseHas('tenant_probes', [
            'code' => 'legacy',
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_backfill_fails_when_the_tenant_schema_is_incomplete(): void
    {
        config(['project.tenancy.mode' => 'multi']);
        $this->makeProbeModule();
        config(['project.modules' => [...config('project.modules'), 'TenantProbe']]);
        Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);

        Schema::create('tenant_probes', function (Blueprint $table) {
            $table->id();
            $table->string('code');
        });

        $this->artisan('tenant:backfill', [
            '--module' => ['TenantProbe'],
            '--tenant' => 'acme',
            '--force' => true,
        ])->expectsOutputToContain('Tenant schema is incomplete')
            ->assertFailed();
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

            public function tenantUniqueColumns(): array
            {
                return [['code']];
            }
        }
        PHP);
    }
}

class TenantModelProbe extends EloquentModel
{
    protected $table = 'organizations';
}
