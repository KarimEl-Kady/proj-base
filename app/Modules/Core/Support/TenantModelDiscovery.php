<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Traits\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
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
        return array_values(array_filter(
            static::discoverAll($requestedModules),
            fn (string $class) => in_array(HasTenantScope::class, class_uses_recursive($class), true),
        ));
    }

    /**
     * Every concrete Eloquent model in the requested (default: active,
     * non-Core) modules, regardless of tenant scoping — the superset
     * discover() filters down and TenantModelClassifier classifies.
     *
     * @param  array<int, string>  $requestedModules
     * @return array<int, class-string>
     */
    public static function discoverAll(array $requestedModules = []): array
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

                if (! is_subclass_of($class, Model::class)) {
                    continue;
                }

                $models[] = $class;
            }
        }

        return $models;
    }
}
