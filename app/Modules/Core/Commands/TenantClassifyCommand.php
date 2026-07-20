<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\TenantModelClassifier;
use Illuminate\Console\Command;

/**
 * Fails the build if any model is neither tenant-scoped nor explicitly
 * declared global. Unlike tenant:migrations/tenant:check (which discover
 * their targets by the HasTenantScope trait, and so can never see a model
 * that simply forgot it), this check is independent of the trait: it forces
 * every model to be classified one way or the other, so a missing decision
 * fails the build instead of shipping silently unscoped.
 */
class TenantClassifyCommand extends Command
{
    protected $signature = 'tenant:classify
                            {--module=* : Limit to specific module(s) (default: every active, non-Core module)}';

    protected $description = 'Check that every model is either tenant-scoped or declared global (project.tenancy.global_models)';

    public function handle(): int
    {
        $unclassified = TenantModelClassifier::unclassified($this->option('module'));

        if ($unclassified === []) {
            $this->info('Tenant classification OK — every model is tenant-scoped or declared global.');

            return self::SUCCESS;
        }

        $this->error('The following models are neither tenant-scoped nor declared global:');
        $this->newLine();

        foreach ($unclassified as $class) {
            $this->line("  - {$class}");
        }

        $this->newLine();
        $this->line('Fix one of:');
        $this->line('  - add `use HasTenantScope;` if this model holds tenant-owned data');
        $this->line("  - add the class to config('project.tenancy.global_models') if it's intentionally");
        $this->line('    shared reference data across every tenant (see Geo\'s Country/City for the pattern)');

        return self::FAILURE;
    }
}
