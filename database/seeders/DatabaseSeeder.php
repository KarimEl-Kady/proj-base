<?php

namespace Database\Seeders;

use App\Modules\Core\Support\Tenancy;
use App\Modules\User\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * The dev-convenience user is environment-guarded and idempotent — this
     * seeder can never plant test credentials in a real deployment. For a
     * production bootstrap, use: php artisan user:make-admin
     */
    public function run(): void
    {
        if (! app()->environment('local', 'development', 'testing')) {
            return;
        }

        // User is tenant-scoped; seed under the default tenant when tenancy
        // is active. The tenant column is set explicitly (not left to the
        // creating() hook) because WithoutModelEvents mutes model events.
        $seed = fn () => User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                ...(has_tenancy()
                    ? [config('project.tenancy.tenant_column', 'tenant_id') => tenant_id()]
                    : []),
            ],
        );

        has_tenancy() ? with_tenant(Tenancy::defaultTenantId(), $seed) : $seed();
    }
}
