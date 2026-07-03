<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List all HMVC modules with their status and components';

    public function handle(): int
    {
        $registry = ModuleRegistry::all();

        $rows = collect(File::directories(module_path()))
            ->map(fn (string $dir) => basename($dir))
            ->sort()
            ->map(function (string $module) use ($registry) {
                $status = match (true) {
                    $module === 'Core' => '<fg=cyan>core</>',
                    ! array_key_exists($module, $registry) => '<fg=red>not registered</>',
                    $registry[$module] => '<fg=green>enabled</>',
                    default => '<fg=yellow>disabled</>',
                };

                return [
                    $module,
                    $status,
                    $this->countFiles($module, 'Controllers'),
                    $this->countFiles($module, 'Models'),
                    $this->countFiles($module, 'Services'),
                    $this->countFiles($module, 'Database/Migrations'),
                ];
            })
            ->values();

        if ($rows->isEmpty()) {
            $this->warn('No modules found in '.module_path());

            return self::SUCCESS;
        }

        $this->table(
            ['Module', 'Status', 'Controllers', 'Models', 'Services', 'Migrations'],
            $rows->all()
        );

        $orphans = array_diff(array_keys($registry), collect(File::directories(module_path()))->map(fn ($d) => basename($d))->all());
        foreach ($orphans as $orphan) {
            $this->warn("Registry entry [{$orphan}] has no matching module directory.");
        }

        return self::SUCCESS;
    }

    protected function countFiles(string $module, string $dir): int
    {
        $path = module_path($module, $dir);

        return is_dir($path) ? count(File::allFiles($path)) : 0;
    }
}
