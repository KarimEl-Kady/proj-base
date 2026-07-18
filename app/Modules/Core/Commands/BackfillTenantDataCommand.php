<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\Tenancy;
use App\Modules\Core\Support\TenantModelDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackfillTenantDataCommand extends Command
{
    protected $signature = 'tenant:backfill
        {--tenant= : Target tenant slug/subdomain (required in multi mode)}
        {--module=* : Limit to specific active modules}
        {--dry-run : Report null tenant rows without updating them}
        {--force : Run without confirmation}';

    protected $description = 'Assign legacy null-tenant rows after enabling tenancy';

    public function handle(): int
    {
        if (! has_tenancy()) {
            $this->error('Enable single or multi tenancy before backfilling tenant data.');

            return self::FAILURE;
        }

        $tenantId = $this->targetTenantId();
        if ($tenantId === null) {
            return self::FAILURE;
        }

        $column = (string) config('project.tenancy.tenant_column', 'tenant_id');
        $models = TenantModelDiscovery::discover($this->option('module'));
        $rows = [];
        $total = 0;
        $schemaMissing = false;

        foreach ($models as $class) {
            $model = new $class;
            $table = $model->getTable();

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                $rows[] = [Str::afterLast($class, '\\'), $table, 'missing column', 0];
                $schemaMissing = true;

                continue;
            }

            $count = DB::table($table)->whereNull($column)->count();
            $total += $count;
            $rows[] = [Str::afterLast($class, '\\'), $table, 'ready', $count];
        }

        $this->table(['Model', 'Table', 'Status', 'Null rows'], $rows);

        if ($schemaMissing) {
            $this->error('Tenant schema is incomplete. Run tenant:migrations and migrate before backfilling.');

            return self::FAILURE;
        }

        if ($this->option('dry-run') || $total === 0) {
            $this->info($total === 0 ? 'No rows require backfilling.' : "{$total} row(s) would be assigned.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Assign {$total} legacy row(s) to tenant ID [{$tenantId}]?"
        )) {
            $this->warn('Backfill cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($models, $column, $tenantId): void {
            foreach ($models as $class) {
                $table = (new $class)->getTable();

                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    DB::table($table)->whereNull($column)->update([$column => $tenantId]);
                }
            }
        });

        $this->info("Assigned {$total} legacy row(s) to tenant ID [{$tenantId}].");

        return self::SUCCESS;
    }

    protected function targetTenantId(): ?int
    {
        if (is_single_tenant()) {
            $tenantId = Tenancy::defaultTenantId();

            if ($tenantId === null) {
                $this->error('The default tenant is unavailable or inactive.');
            }

            return $tenantId;
        }

        $identifier = (string) $this->option('tenant');
        if ($identifier === '') {
            $this->error('Multi-tenant mode requires --tenant={slug or subdomain}.');

            return null;
        }

        $tenantId = Tenancy::lookupTenantId($identifier);
        if ($tenantId === null) {
            $this->error("No active tenant matches [{$identifier}].");
        }

        return $tenantId;
    }
}
