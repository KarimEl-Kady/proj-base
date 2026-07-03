<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;

class ProjectInfoCommand extends Command
{
    protected $signature = 'project:info';

    protected $description = 'Display the project configuration overview';

    public function handle(): int
    {
        $this->components->info(config('project.name').' v'.config('project.version'));

        $this->twoColumnDetail('<fg=cyan>Platform</>', '');
        $this->twoColumnDetail('Mode', config('project.platform'));
        $this->twoColumnDetail('DB driver', config('project.db_driver'));
        $this->twoColumnDetail('Auth driver', config('project.auth.driver'));
        $this->twoColumnDetail('Route attributes', config('project.route_attributes.enabled') ? 'enabled' : 'disabled');

        $this->twoColumnDetail('<fg=cyan>Tenancy</>', '');
        $this->twoColumnDetail('Mode', config('project.tenancy.mode'));
        if (is_multi_tenant()) {
            $this->twoColumnDetail('Tenant column', config('project.tenancy.tenant_column'));
            $this->twoColumnDetail('Identification', config('project.tenancy.tenant_identification'));
        }

        $this->twoColumnDetail('<fg=cyan>API</>', '');
        $this->twoColumnDetail('Enabled', config('project.api.enabled') ? 'yes' : 'no');
        $this->twoColumnDetail('Base path', '/'.config('project.api.prefix').'/'.config('project.api.version'));
        $this->twoColumnDetail('Rate limit', config('project.api.rate_limit').' req/min');

        $this->twoColumnDetail('<fg=cyan>Modules</>', implode(', ', config('project.modules', [])) ?: 'none');

        $features = collect(config('project.features', []))
            ->filter()
            ->keys()
            ->implode(', ');
        $this->twoColumnDetail('<fg=cyan>Features</>', $features ?: 'none');

        $this->newLine();
        $this->line('  Run <fg=green>module:list</> and <fg=green>package:list</> for details.');

        return self::SUCCESS;
    }

    protected function twoColumnDetail(string $left, string $right): void
    {
        $this->components->twoColumnDetail($left, $right);
    }
}
