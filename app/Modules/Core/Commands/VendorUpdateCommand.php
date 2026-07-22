<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\VendorGit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class VendorUpdateCommand extends Command
{
    protected $signature = 'vendor:update
                            {name : Package directory name under app/Vendor (StudlyCase)}
                            {--ref= : Override the branch/tag/commit to pull (default: the ref it was installed from)}';

    protected $description = 'Pull upstream changes into an app/Vendor package via git subtree pull';

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

        $source = VendorGit::readVendorSource($packageComposerPath);

        if ($source === null) {
            $this->error("[{$studly}] has no recorded vendor source (extra.vendor-source). It wasn't installed via vendor:install, so there's nothing to update from.");

            return self::FAILURE;
        }

        $ref = $this->option('ref') ?? $source['ref'];

        try {
            VendorGit::ensureCleanWorkingTree();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pulling updates for [{$studly}] from {$source['repo']}@{$ref} ...");

        try {
            VendorGit::subtreePull($vendorRelative, $source['repo'], $ref, "vendor: update {$studly} from {$source['repo']}@{$ref}");
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            $this->warn('If this was a merge conflict, resolve the conflict markers under '.$vendorRelative.', then `git add` and `git commit` to finish.');

            return self::FAILURE;
        }

        if ($ref !== $source['ref'] && VendorGit::writeVendorSource($packageComposerPath, $source['repo'], $ref)) {
            VendorGit::commitAll("vendor: track {$studly} at ref {$ref}");
        }

        $this->info("Updated [{$studly}].");
        $this->line('If the package\'s own dependencies changed, run: composer update --no-interaction');

        return self::SUCCESS;
    }
}
