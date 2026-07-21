<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

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

        // The check above is a courtesy, not the guarantee — it can't close
        // a race between two concurrent runs (two deploy scripts, a retried
        // CI step). The unique constraint on `slug` is the real guarantee;
        // catch its violation here so that race surfaces the same clean
        // error instead of a raw QueryException.
        try {
            $tenant = $tenantModel::query()->create([
                'name' => $this->option('name') ?: str($slug)->replace('-', ' ')->title()->toString(),
                'slug' => $slug,
                'subdomain' => $this->option('subdomain'),
                'is_active' => ! $this->option('inactive'),
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->error("Tenant [{$slug}] already exists.");

            return self::FAILURE;
        }

        $this->info("Tenant [{$slug}] provisioned with id [{$tenant->getKey()}].");

        return self::SUCCESS;
    }
}
