<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\VendorGit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class VendorInstallCommand extends Command
{
    protected $signature = 'vendor:install
                            {source : A name registered in config(vendor_sources) or a literal git URL/path}
                            {--as= : Directory/StudlyCase name under app/Vendor (default: derived from the source)}
                            {--ref= : Branch/tag/commit to install (default: registry ref, or "main")}';

    protected $description = 'Pull a package from its own git repository into app/Vendor via git subtree';

    public function handle(): int
    {
        [$repo, $ref] = $this->resolveSource();

        $studly = $this->option('as')
            ? Str::studly($this->option('as'))
            : Str::studly(preg_replace('/\.git$/', '', basename(rtrim($repo, '/'))));

        $vendorRelative = config('project.paths.vendor', 'app/Vendor')."/{$studly}";
        $targetPath = VendorGit::repoRoot().'/'.$vendorRelative;

        if (File::isDirectory($targetPath)) {
            $this->error("A package already exists at {$vendorRelative}. Use vendor:update instead.");

            return self::FAILURE;
        }

        try {
            VendorGit::ensureCleanWorkingTree();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $headBefore = VendorGit::currentHead();

        $this->info("Pulling {$repo}@{$ref} into {$vendorRelative} ...");

        try {
            VendorGit::subtreeAdd($vendorRelative, $repo, $ref, "vendor: install {$studly} from {$repo}@{$ref}");
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $packageComposerPath = "{$targetPath}/composer.json";

        if (! File::exists($packageComposerPath)) {
            $this->error('Not a valid vendor package: composer.json is missing. Rolling back.');
            VendorGit::resetHardTo($headBefore);

            return self::FAILURE;
        }

        try {
            $packageComposer = VendorGit::readJson($packageComposerPath);
        } catch (RuntimeException $e) {
            $this->error("Not a valid vendor package: {$e->getMessage()} Rolling back.");
            VendorGit::resetHardTo($headBefore);

            return self::FAILURE;
        }

        $packageName = $packageComposer['name'] ?? null;

        if (! is_string($packageName) || $packageName === '') {
            $this->error('Not a valid vendor package: composer.json has no "name". Rolling back.');
            VendorGit::resetHardTo($headBefore);

            return self::FAILURE;
        }

        $version = $packageComposer['version'] ?? null;
        $constraint = is_string($version) && preg_match('/^(\d+)\./', $version, $m)
            ? "^{$m[1]}.0"
            : '^1.0';

        VendorGit::addRequire(VendorGit::repoRoot().'/composer.json', $packageName, $constraint);
        VendorGit::commitAll("vendor: require {$packageName}");

        if (VendorGit::writeVendorSource($packageComposerPath, $repo, $ref)) {
            VendorGit::commitAll("vendor: record source for {$packageName}");
        }

        $this->info("Installed [{$packageName}] at {$vendorRelative}.");
        $this->newLine();
        $this->line('Next steps:');
        $this->line("  composer update {$packageName} --no-interaction");

        if (File::isDirectory("{$targetPath}/database/migrations")) {
            $this->line('  php artisan migrate');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSource(): array
    {
        $source = $this->argument('source');
        $registry = config('vendor_sources', []);

        if (is_array($registry) && isset($registry[$source]) && is_array($registry[$source])) {
            $entry = $registry[$source];
            $repo = $entry['repo'] ?? $source;
            $ref = $this->option('ref') ?? ($entry['ref'] ?? 'main');

            return [$repo, $ref];
        }

        return [$source, $this->option('ref') ?? 'main'];
    }
}
