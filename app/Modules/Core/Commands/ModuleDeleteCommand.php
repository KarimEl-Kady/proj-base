<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Core\Support\ModuleRuntimeCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class ModuleDeleteCommand extends Command
{
    protected $signature = 'module:delete
                            {name? : Module name in StudlyCase (omit to choose from a list)}
                            {--force : Delete without confirmation}';

    protected $description = 'Delete a module directory and remove it from the registry';

    public function handle(): int
    {
        $module = $this->argument('name')
            ? Str::studly($this->argument('name'))
            : $this->askForModule();

        if ($module === null) {
            $this->info('No modules to delete.');

            return self::SUCCESS;
        }

        if ($module === 'Core') {
            $this->error('The Core module cannot be deleted.');

            return self::FAILURE;
        }

        $path = module_path($module);

        if (! File::isDirectory($path)) {
            $this->error("Module [{$module}] does not exist at {$path}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm("Delete module [{$module}] and all its files at {$path}?", default: false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        File::deleteDirectory($path);
        ModuleRegistry::remove($module);
        ModuleRuntimeCache::clear();

        $this->info("Module [{$module}] deleted, removed from the registry, and runtime caches cleared.");
        $this->warn('Remember to drop any tables/migrations that belonged to this module.');

        return self::SUCCESS;
    }

    protected function askForModule(): ?string
    {
        $candidates = collect(File::directories(module_path()))
            ->map(fn (string $dir) => basename($dir))
            ->reject(fn (string $module) => $module === 'Core')
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        return select('Which module should be deleted?', $candidates->all());
    }
}
