<?php

namespace Database\Seeders;

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

        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password'],
        );
    }
}
