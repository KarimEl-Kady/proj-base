<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\TenantModelDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Retrofit path for tenant-scoped tables (HasTenantScope) whose migration
 * predates this project's tenancy setup, or was hand-written without
 * `$table->tenantColumn()`: finds every such model whose table is missing
 * the tenant column and generates the add-column migration into the owning
 * module. Tables created with $table->tenantColumn() already get the
 * column unconditionally, in every tenancy mode, and never need this.
 */
class TenantMigrationsCommand extends Command
{
    protected $signature = 'tenant:migrations
                            {--module=* : Limit to specific module(s) (default: every active module)}';

    protected $description = 'Generate add-tenant-column migrations for tenant-scoped tables that are missing it';

    public function handle(): int
    {
        $column = config('project.tenancy.tenant_column', 'tenant_id');
        $models = TenantModelDiscovery::discover($this->option('module'));

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
            ? $this->info("{$created} migration(s) generated — review them, run migrate, then tenant:backfill")
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

        $model = new $class;
        $tenantUniqueColumns = method_exists($model, 'tenantUniqueColumns')
            ? $model->tenantUniqueColumns()
            : [];
        $this->writeMigration($migrationsDir, $table, $column, $tenantUniqueColumns);

        return ['<fg=green>migration created</>', true];
    }

    /**
     * @param  array<int, array<int, string>>  $tenantUniqueColumns
     */
    protected function writeMigration(string $dir, string $table, string $column, array $tenantUniqueColumns): void
    {
        $timestamp = now()->format('Y_m_d_His');
        $path = "{$dir}/{$timestamp}_add_{$column}_to_{$table}_table.php";

        // Column + index only — no foreign key on purpose: adding an FK to an
        // existing table isn't portable across drivers (SQLite in particular).
        // Add one in a separate migration if the project needs it.
        $upIndexes = collect($tenantUniqueColumns)
            ->map(function (array $columns) use ($column): string {
                $global = implode("', '", $columns);
                $scoped = implode("', '", [$column, ...$columns]);

                return "            \$table->dropUnique(['{$global}']);\n".
                    "            \$table->unique(['{$scoped}']);";
            })
            ->implode("\n");
        $downIndexes = collect($tenantUniqueColumns)
            ->map(function (array $columns) use ($column): string {
                $global = implode("', '", $columns);
                $scoped = implode("', '", [$column, ...$columns]);

                return "            \$table->dropUnique(['{$scoped}']);\n".
                    "            \$table->unique(['{$global}']);";
            })
            ->implode("\n");

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
        {$upIndexes}
                });
            }

            public function down(): void
            {
                Schema::table('{$table}', function (Blueprint \$table) {
        {$downIndexes}
                    \$table->dropColumn('{$column}');
                });
            }
        };

        PHP;

        File::ensureDirectoryExists($dir);
        File::put($path, $content);
    }
}
