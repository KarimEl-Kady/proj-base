<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Traits\HasTenantScope;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class TenantModelDiscovery
{
    /**
     * @param  array<int, string>  $requestedModules
     * @return array<int, class-string>
     */
    public static function discover(array $requestedModules = []): array
    {
        $active = config('project.modules', []);
        $modules = $requestedModules === []
            ? $active
            : array_values(array_intersect($active, $requestedModules));
        $models = [];

        foreach ($modules as $module) {
            $dir = module_path($module, 'Models');

            if (! is_dir($dir)) {
                continue;
            }

            foreach (File::allFiles($dir) as $file) {
                $class = "App\\Modules\\{$module}\\Models\\".Str::before($file->getRelativePathname(), '.php');
                $class = str_replace('/', '\\', $class);

                if (! class_exists($class) || (new ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                if (in_array(HasTenantScope::class, class_uses_recursive($class), true)) {
                    $models[] = $class;
                }
            }
        }

        return $models;
    }
}
