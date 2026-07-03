<?php

namespace App\Modules\Core\Commands;

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

                preg_match_all('/App\\\\Modules\\\\([A-Za-z0-9]+)\\\\/', $file->getContents(), $matches);

                foreach (array_unique($matches[1]) as $target) {
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

        if ($violations === []) {
            $this->info('Module boundaries OK — no undeclared cross-module dependencies.');

            return self::SUCCESS;
        }

        $this->error('Undeclared cross-module dependencies found:');
        $this->table(['Module', 'Depends on', 'File'], $violations);
        $this->line('Either remove the dependency or declare it in config/project.php under boundaries.allow.');

        return self::FAILURE;
    }
}
