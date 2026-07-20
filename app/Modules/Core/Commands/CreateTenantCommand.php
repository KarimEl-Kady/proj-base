<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
        {slug : Lowercase tenant slug}
        {--name= : Display name; defaults to the slug}
        {--subdomain= : Optional subdomain identifier}
        {--inactive : Create the tenant behind the kill switch}';

    protected $description = 'Provision a tenant explicitly (multi-tenant self-registration is closed by default)';

    public function handle(): int
    {
        $slug = (string) $this->argument('slug');

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $this->error('Slug must contain lowercase letters/numbers separated by single dashes.');

            return self::FAILURE;
        }

        $tenantModel = config('project.tenancy.tenant_model');

        if (! is_string($tenantModel) || ! is_subclass_of($tenantModel, Model::class)) {
            $this->error('project.tenancy.tenant_model is not an Eloquent model.');

            return self::FAILURE;
        }

        if ($tenantModel::query()->where('slug', $slug)->exists()) {
            $this->error("Tenant [{$slug}] already exists.");

            return self::FAILURE;
        }

        $tenant = $tenantModel::query()->create([
            'name' => $this->option('name') ?: str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'subdomain' => $this->option('subdomain'),
            'is_active' => ! $this->option('inactive'),
        ]);

        $this->info("Tenant [{$slug}] provisioned with id [{$tenant->getKey()}].");

        return self::SUCCESS;
    }
}
