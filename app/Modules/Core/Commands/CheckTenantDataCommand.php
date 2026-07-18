<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\TenantModelDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CheckTenantDataCommand extends Command
{
    protected $signature = 'tenant:check {--module=* : Limit to specific active modules}';

    protected $description = 'Fail when active tenancy has missing columns or unassigned rows';

    public function handle(): int
    {
        if (! has_tenancy()) {
            $this->info('Tenancy is disabled; no tenant data checks are required.');

            return self::SUCCESS;
        }

        $column = (string) config('project.tenancy.tenant_column', 'tenant_id');
        $invalid = false;
        $rows = [];

        foreach (TenantModelDiscovery::discover($this->option('module')) as $class) {
            $table = (new $class)->getTable();

            if (! Schema::hasTable($table)) {
                $rows[] = [Str::afterLast($class, '\\'), $table, 'table missing', '-'];
                $invalid = true;

                continue;
            }

            if (! Schema::hasColumn($table, $column)) {
                $rows[] = [Str::afterLast($class, '\\'), $table, 'column missing', '-'];
                $invalid = true;

                continue;
            }

            $nullRows = DB::table($table)->whereNull($column)->count();
            $rows[] = [Str::afterLast($class, '\\'), $table, $nullRows === 0 ? 'ok' : 'unassigned rows', $nullRows];
            $invalid = $invalid || $nullRows > 0;
        }

        $this->table(['Model', 'Table', 'Status', 'Null rows'], $rows);

        if ($invalid) {
            $this->error('Tenant data is not ready. Run tenant:migrations and tenant:backfill.');

            return self::FAILURE;
        }

        $this->info('Tenant data is ready.');

        return self::SUCCESS;
    }
}
