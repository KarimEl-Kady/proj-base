<?php

namespace App\Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_can_be_explicitly_provisioned(): void
    {
        $this->artisan('tenant:create acme --name="Acme Corp" --subdomain=acme')
            ->expectsOutputToContain('Tenant [acme] provisioned')
            ->assertSuccessful();

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'subdomain' => 'acme',
            'is_active' => true,
        ]);
    }

    public function test_invalid_or_duplicate_tenants_fail_closed(): void
    {
        $this->artisan('tenant:create Not/Valid')->assertFailed();
        $this->artisan('tenant:create acme')->assertSuccessful();
        $this->artisan('tenant:create acme')->assertFailed();
    }
}
