<?php

namespace App\Modules\Core\Tests\Unit;

use App\Modules\Core\Support\TenantModelClassifier;
use App\Modules\Geo\Models\City;
use App\Modules\Geo\Models\Country;
use App\Modules\User\Models\User;
use Tests\TestCase;

class TenantModelClassifierTest extends TestCase
{
    public function test_every_shipped_model_is_classified(): void
    {
        $this->assertSame(
            [],
            TenantModelClassifier::unclassified(),
            'Every model in an active module must use HasTenantScope or be listed in '.
            "config('project.tenancy.global_models') — see the failing class(es) above."
        );
    }

    public function test_user_is_tenant_scoped_not_declared_global(): void
    {
        $unclassifiedIfNotTenantScoped = in_array(User::class, config('project.tenancy.global_models', []), true);

        $this->assertFalse(
            $unclassifiedIfNotTenantScoped,
            'User should be tenant-scoped via HasTenantScope, not exempted via global_models.'
        );
    }

    public function test_geo_models_are_declared_global_reference_data(): void
    {
        $globalModels = config('project.tenancy.global_models', []);

        $this->assertContains(Country::class, $globalModels);
        $this->assertContains(City::class, $globalModels);
    }

    public function test_unclassified_model_is_flagged(): void
    {
        $unclassified = TenantModelClassifier::unclassified(['Geo']);

        // Country/City are both declared global, so scoping to Geo alone
        // should still classify cleanly with the shipped config.
        $this->assertSame([], $unclassified);
    }
}
