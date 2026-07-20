<?php

namespace App\Modules\Core\Support;

use Illuminate\Support\Facades\Context;
use Local\Media\Contracts\TenantResolver;

class MediaTenantResolver implements TenantResolver
{
    public function enabled(): bool
    {
        return has_tenancy() && Context::get('tenancy_bypass') !== true;
    }

    public function id(): int|string|null
    {
        return tenant_id();
    }
}
