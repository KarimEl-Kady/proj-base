<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Core\Support\ModuleRuntimeCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;

class ModuleEnableCommand extends Command
{
    protected $signature = 'module:enable {name? : Module name in StudlyCase (omit to choose from a list)}';

    protected $description = 'Enable a module in the registry (config/project_modules.php)';

    public function handle(): int
    {
        $module = $this->argument('name')
            ? Str::studly($this->argument('name'))
            : $this->askForModule();

        if ($module === null) {
            $this->info('Nothing to enable — all modules are already enabled.');

            return self::SUCCESS;
        }

        if (! is_dir(module_path($module))) {
            $this->error("Module [{$module}] does not exist. Run: php artisan make:module {$module}");

            return self::FAILURE;
        }

        if (ModuleRegistry::isEnabled($module)) {
            $this->info("Module [{$module}] is already enabled.");

            return self::SUCCESS;
        }

        ModuleRegistry::set($module, true);
        ModuleRuntimeCache::clear();

        $this->info("Module [{$module}] enabled. Config, route, and event caches cleared.");

        return self::SUCCESS;
    }

    protected function askForModule(): ?string
    {
        $candidates = collect(File::directories(module_path()))
            ->map(fn (string $dir) => basename($dir))
            ->reject(fn (string $module) => $module === 'Core' || ModuleRegistry::isEnabled($module))
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        return select('Which module should be enabled?', $candidates->all());
    }
}
