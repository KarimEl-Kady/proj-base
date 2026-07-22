<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\VendorGit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;

class VendorRemoveCommand extends Command
{
    protected $signature = 'vendor:remove
                            {name : Package directory name under app/Vendor (StudlyCase)}
                            {--keep-files : Drop the composer require but leave the directory in place}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Remove an app/Vendor package: drop the composer require and (optionally) its files';

    public function handle(): int
    {
        $studly = Str::studly($this->argument('name'));
        $vendorRelative = config('project.paths.vendor', 'app/Vendor')."/{$studly}";
        $targetPath = VendorGit::repoRoot().'/'.$vendorRelative;
        $packageComposerPath = "{$targetPath}/composer.json";

        if (! File::isDirectory($targetPath)) {
            $this->error("No package at {$vendorRelative}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm("Remove package [{$studly}] at {$vendorRelative}?", default: false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $packageName = File::exists($packageComposerPath)
            ? (VendorGit::readJson($packageComposerPath)['name'] ?? null)
            : null;

        if ($packageName) {
            VendorGit::removeRequire(VendorGit::repoRoot().'/composer.json', $packageName);
            VendorGit::commitAll("vendor: remove {$packageName}");
        }

        if (! $this->option('keep-files')) {
            VendorGit::removeTracked($vendorRelative, "vendor: delete {$studly}");
        }

        $this->info("Removed [{$studly}].");
        $this->line('Run: composer update --no-interaction');

        return self::SUCCESS;
    }
}
