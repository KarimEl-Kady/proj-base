<?php

namespace App\Modules\Core\Exceptions;

use RuntimeException;

class TenantContextMismatchException extends RuntimeException
{
    public static function for(string $model, int|string $expected, int|string $actual): self
    {
        return new self(
            "Tenant mismatch while creating [{$model}]: active tenant [{$expected}] does not match supplied tenant [{$actual}]. "
            .'Use with_tenant() for tenant-specific work or without_tenant_scope() for deliberate cross-tenant maintenance.'
        );
    }
}
