<?php

namespace App\Modules\User\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UserRules
{
    public static function uniqueEmail(?string $ignoreUuid = null): Unique
    {
        $rule = Rule::unique('users', 'email');

        if (has_tenancy() && tenant_id() !== null) {
            $rule->where(
                fn ($query) => $query->where(
                    config('project.tenancy.tenant_column', 'tenant_id'),
                    tenant_id(),
                )
            );
        }

        if ($ignoreUuid !== null) {
            $rule->ignore($ignoreUuid, 'uuid');
        }

        return $rule;
    }
}
