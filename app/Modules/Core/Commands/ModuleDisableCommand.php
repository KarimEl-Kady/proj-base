<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Core\Support\ModuleRuntimeCache;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;

class ModuleDisableCommand extends Command
{
    protected $signature = 'module:disable {name? : Module name in StudlyCase (omit to choose from a list)}';

    protected $description = 'Disable a module in the registry (config/project_modules.php)';

    public function handle(): int
    {
        $module = $this->argument('name')
            ? Str::studly($this->argument('name'))
            : $this->askForModule();

        if ($module === null) {
            $this->info('Nothing to disable — no modules are enabled.');

            return self::SUCCESS;
        }

        if ($module === 'Core') {
            $this->error('The Core module cannot be disabled.');

            return self::FAILURE;
        }

        if (! ModuleRegistry::isEnabled($module)) {
            $this->info("Module [{$module}] is not enabled.");

            return self::SUCCESS;
        }

        ModuleRegistry::set($module, false);
        ModuleRuntimeCache::clear();

        $this->info("Module [{$module}] disabled. Config, route, and event caches cleared.");

        return self::SUCCESS;
    }

    protected function askForModule(): ?string
    {
        $enabled = ModuleRegistry::enabled();

        if ($enabled === []) {
            return null;
        }

        return select('Which module should be disabled?', $enabled);
    }
}
