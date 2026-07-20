<?php

namespace App\Modules\Auth\Support;

class TenantRegistrationPolicy
{
    public function ensureAllowed(): void
    {
        if (! is_multi_tenant()) {
            return;
        }

        abort_unless(
            config('project.tenancy.registration') === 'open',
            403,
            'Self-service registration is closed for this tenant. Use an administrator-approved provisioning flow.'
        );
    }
}
