<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Traits\HasTenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Catch-up path for projects that started single-tenant and switched to
 * multi: finds every tenant-scoped model (HasTenantScope) whose table is
 * missing the tenant column and generates the add-column migration into
 * the owning module. New tables don't need this — their create migrations
 * use $table->tenantColumn(), which adds the column automatically in
 * multi-tenant mode.
 */
class TenantMigrationsCommand extends Command
{
    protected $signature = 'tenant:migrations';

    protected $description = 'Generate add-tenant-column migrations for tenant-scoped tables that are missing it';

    public function handle(): int
    {
        if (! is_multi_tenant()) {
            $this->info('Tenancy mode is [single] — tables don\'t need a tenant column.');
            $this->line('Set PROJECT_TENANCY_MODE=multi and re-run to generate catch-up migrations.');

            return self::SUCCESS;
        }

        $column = config('project.tenancy.tenant_column', 'tenant_id');
        $models = $this->tenantScopedModels();

        if ($models === []) {
            $this->warn('No models using HasTenantScope found in active modules.');

            return self::SUCCESS;
        }

        $created = 0;
        $rows = [];

        foreach ($models as $class) {
            [$status, $generated] = $this->process($class, $column);
            $created += $generated ? 1 : 0;

            $rows[] = [Str::afterLast($class, '\\'), (new $class)->getTable(), $status];
        }

        $this->info("Tenant column [{$column}] status per tenant-scoped model:");
        $this->table(['Model', 'Table', 'Status'], $rows);

        $this->newLine();
        $created > 0
            ? $this->info("{$created} migration(s) generated — review them, then run: php artisan migrate")
            : $this->info('Nothing to generate — all tenant-scoped tables are covered.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: bool} [status label, whether a file was generated]
     */
    protected function process(string $class, string $column): array
    {
        $table = (new $class)->getTable();
        $module = Str::before(Str::after($class, 'App\\Modules\\'), '\\');
        $migrationsDir = module_path($module, 'Database/Migrations');

        if (! Schema::hasTable($table)) {
            return ['<fg=yellow>table missing — run migrate first</>', false];
        }

        if (Schema::hasColumn($table, $column)) {
            return ['<fg=green>ok</>', false];
        }

        if (glob("{$migrationsDir}/*_add_{$column}_to_{$table}_table.php") !== []) {
            return ['<fg=yellow>migration already generated — run migrate</>', false];
        }

        $this->writeMigration($migrationsDir, $table, $column);

        return ['<fg=green>migration created</>', true];
    }

    protected function writeMigration(string $dir, string $table, string $column): void
    {
        $timestamp = now()->format('Y_m_d_His');
        $path = "{$dir}/{$timestamp}_add_{$column}_to_{$table}_table.php";

        // Column + index only — no foreign key on purpose: adding an FK to an
        // existing table isn't portable across drivers (SQLite in particular).
        // Add one in a separate migration if the project needs it.
        $content = <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::table('{$table}', function (Blueprint \$table) {
                    \$table->foreignId('{$column}')->nullable()->index();
                });
            }

            public function down(): void
            {
                Schema::table('{$table}', function (Blueprint \$table) {
                    \$table->dropColumn('{$column}');
                });
            }
        };

        PHP;

        File::ensureDirectoryExists($dir);
        File::put($path, $content);
    }

    /**
     * Non-abstract models using HasTenantScope across active modules.
     *
     * @return array<int, class-string>
     */
    protected function tenantScopedModels(): array
    {
        $models = [];

        foreach (config('project.modules', []) as $module) {
            $dir = module_path($module, 'Models');

            if (! is_dir($dir)) {
                continue;
            }

            foreach (File::allFiles($dir) as $file) {
                $class = "App\\Modules\\{$module}\\Models\\".Str::before($file->getRelativePathname(), '.php');
                $class = str_replace('/', '\\', $class);

                if (! class_exists($class) || (new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                if (in_array(HasTenantScope::class, class_uses_recursive($class), true)) {
                    $models[] = $class;
                }
            }
        }

        return $models;
    }
}
