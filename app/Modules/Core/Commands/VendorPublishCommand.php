<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\VendorGit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class VendorPublishCommand extends Command
{
    protected $signature = 'vendor:publish
                            {name : Package directory name under app/Vendor (StudlyCase)}
                            {repo : Git URL/path of the (empty or compatible) destination repository}
                            {--ref=main : Branch to push in the destination repository}
                            {--keep-branch : Keep the local vendor-split/* branch used to build the push}';

    protected $description = 'Extract an app/Vendor package to a new git repository via git subtree split, so other platforms can vendor:install it';

    public function handle(): int
    {
        $studly = Str::studly($this->argument('name'));
        $kebab = Str::kebab($studly);
        $repo = $this->argument('repo');
        $ref = $this->option('ref');
        $branch = "vendor-split/{$kebab}";

        $vendorRelative = config('project.paths.vendor', 'app/Vendor')."/{$studly}";
        $targetPath = VendorGit::repoRoot().'/'.$vendorRelative;
        $packageComposerPath = "{$targetPath}/composer.json";

        if (! File::isDirectory($targetPath)) {
            $this->error("No package at {$vendorRelative}");

            return self::FAILURE;
        }

        try {
            VendorGit::ensureCleanWorkingTree();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Splitting {$vendorRelative} out to branch {$branch} ...");

        try {
            VendorGit::subtreeSplit($vendorRelative, $branch);
            $this->info("Pushing {$branch} to {$repo}:{$ref} ...");
            VendorGit::push($repo, $branch, $ref);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if (! $this->option('keep-branch')) {
                try {
                    VendorGit::deleteBranch($branch);
                } catch (RuntimeException) {
                    // Best-effort cleanup; leaving the branch behind isn't fatal.
                }
            }
        }

        if (File::exists($packageComposerPath) && VendorGit::writeVendorSource($packageComposerPath, $repo, $ref)) {
            $packageName = VendorGit::readJson($packageComposerPath)['name'] ?? $studly;
            VendorGit::commitAll("vendor: link {$packageName} to published source");
        }

        $this->info("Published [{$studly}] to {$repo}@{$ref}.");
        $this->newLine();
        $this->line('Other platforms can now pull it with:');
        $this->line("  php artisan vendor:install {$repo} --as={$studly} --ref={$ref}");
        $this->line('Consider registering it in config/vendor_sources.php for a shorthand name.');

        return self::SUCCESS;
    }
}
