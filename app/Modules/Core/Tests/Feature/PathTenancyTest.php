<?php

namespace App\Modules\Core\Tests\Feature;

use App\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class PathTenancyTest extends TestCase
{
    use RefreshDatabase;

    public static function setUpBeforeClass(): void
    {
        putenv('PROJECT_TENANCY_MODE=multi');
        putenv('PROJECT_TENANT_IDENTIFICATION=path');
    }

    public static function tearDownAfterClass(): void
    {
        putenv('PROJECT_TENANCY_MODE');
        putenv('PROJECT_TENANT_IDENTIFICATION');
    }

    public function test_module_routes_are_prefixed_and_resolve_the_path_tenant(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);

        $this->getJson('/api/v1/countries')->assertNotFound();
        $this->getJson('/acme/api/v1/countries')->assertOk();
        $this->assertSame($tenant->id, tenant_id());
    }

    public function test_path_parameter_is_removed_before_controller_binding(): void
    {
        Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);

        $this->postJson('/acme/api/v1/auth/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret-password',
        ])->assertCreated();
    }

    public function test_verification_link_preserves_the_path_tenant(): void
    {
        config(['project.features.email_verification' => true]);
        Tenant::query()->create(['name' => 'Acme', 'slug' => 'acme']);
        Notification::fake();

        $this->postJson('/acme/api/v1/auth/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret-password',
        ])->assertCreated();

        $actionUrl = null;
        Notification::assertSentTo(
            User::query()->firstOrFail(),
            VerifyEmail::class,
            function (VerifyEmail $notification, array $channels, $user) use (&$actionUrl): bool {
                $actionUrl = $notification->toMail($user)->actionUrl;

                return true;
            },
        );

        $this->assertStringContainsString('/acme/api/v1/auth/email/verify/', (string) $actionUrl);
        $this->getJson((string) $actionUrl)->assertOk();
    }
}
