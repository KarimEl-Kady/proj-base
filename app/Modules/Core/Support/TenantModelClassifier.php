<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Traits\HasTenantScope;

/**
 * Every model discovered by TenantModelDiscovery::discoverAll() must be
 * either tenant-scoped (HasTenantScope) or explicitly declared global
 * (project.tenancy.global_models) — see the config comment for why this
 * exists as a check independent of the trait itself.
 */
class TenantModelClassifier
{
    /**
     * @param  array<int, string>  $requestedModules
     * @return array<int, class-string> models that are neither tenant-scoped nor declared global
     */
    public static function unclassified(array $requestedModules = []): array
    {
        $globalModels = config('project.tenancy.global_models', []);

        return array_values(array_filter(
            TenantModelDiscovery::discoverAll($requestedModules),
            fn (string $class) => ! in_array(HasTenantScope::class, class_uses_recursive($class), true)
                && ! in_array($class, $globalModels, true),
        ));
    }
}
