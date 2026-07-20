<?php

namespace Local\Media\Support;

use Local\Media\Contracts\TenantResolver;

class NullTenantResolver implements TenantResolver
{
    public function enabled(): bool
    {
        return false;
    }

    public function id(): int|string|null
    {
        return null;
    }
}
