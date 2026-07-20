<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\ModuleReferenceScanner;
use App\Modules\Core\Support\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ModuleBoundariesCommand extends Command
{
    protected $signature = 'module:boundaries';

    protected $description = 'Check that modules only depend on Core and their declared dependencies (config project.boundaries)';

    public function handle(): int
    {
        $allowed = config('project.boundaries.allow', []);
        $violations = [];
        $registry = ModuleRegistry::all();

        foreach (array_keys(array_filter($registry)) as $module) {
            $provider = module_path($module, "Providers/{$module}ServiceProvider.php");

            if (! File::exists($provider)) {
                $violations[] = [$module, 'missing-provider', str_replace(base_path().'/', '', $provider)];
            }

            foreach ($allowed[$module] ?? [] as $dependency) {
                if (($registry[$dependency] ?? false) !== true) {
                    $violations[] = [$module, $dependency, 'dependency is missing or disabled in config/project_modules.php'];
                }
            }
        }

        $allowedCycles = collect(config('project.boundaries.allow_cycles', []))
            ->map(function (array $cycle): string {
                sort($cycle);

                return implode('|', $cycle);
            })
            ->all();

        foreach ($this->cycles($allowed) as $cycle) {
            if (! in_array(implode('|', $cycle), $allowedCycles, true)) {
                $violations[] = [implode(' -> ', $cycle), 'dependency-cycle', 'Declare it in boundaries.allow_cycles only when intentional.'];
            }
        }

        foreach (File::directories(module_path()) as $dir) {
            $module = basename($dir);

            if ($module === 'Core') {
                continue;
            }

            $moduleAllowed = array_merge(['Core', $module], $allowed[$module] ?? []);

            foreach (File::allFiles($dir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                foreach (ModuleReferenceScanner::referencedModules($file->getContents()) as $target) {
                    if (! in_array($target, $moduleAllowed)) {
                        $violations[] = [
                            $module,
                            $target,
                            'app/Modules/'.$module.'/'.$file->getRelativePathname(),
                        ];
                    }
                }
            }
        }

        $vendorPath = base_path(config('project.paths.vendor', 'app/Vendor'));
        if (is_dir($vendorPath)) {
            foreach (File::directories($vendorPath) as $packageDir) {
                foreach (File::allFiles($packageDir) as $file) {
                    if ($file->getExtension() !== 'php'
                        || str_starts_with($file->getRelativePathname(), 'tests'.DIRECTORY_SEPARATOR)) {
                        continue;
                    }

                    if (ModuleReferenceScanner::referencesAppNamespace($file->getContents())) {
                        $violations[] = [
                            'package:'.basename($packageDir),
                            'application-coupling',
                            str_replace(base_path().'/', '', $file->getPathname()),
                        ];
                    }
                }
            }
        }

        if ($violations === []) {
            $this->info('Module boundaries OK — no undeclared cross-module dependencies.');

            return self::SUCCESS;
        }

        $this->error('Undeclared cross-module dependencies found:');
        $this->table(['Module', 'Dependency / issue', 'File / detail'], $violations);
        $this->line('Either remove the dependency or declare it in config/project.php under boundaries.allow.');

        return self::FAILURE;
    }

    /**
     * @param  array<string, array<int, string>>  $graph
     * @return array<int, array<int, string>>
     */
    protected function cycles(array $graph): array
    {
        $cycles = [];

        $walk = function (string $origin, string $node, array $path) use (&$walk, &$cycles, $graph): void {
            foreach ($graph[$node] ?? [] as $next) {
                if ($next === $origin) {
                    $cycle = array_values(array_unique([...$path, $node]));
                    sort($cycle);
                    $cycles[implode('|', $cycle)] = $cycle;

                    continue;
                }

                if (! in_array($next, $path, true)) {
                    $walk($origin, $next, [...$path, $node]);
                }
            }
        };

        foreach (array_keys($graph) as $module) {
            $walk($module, $module, []);
        }

        return array_values($cycles);
    }
}
