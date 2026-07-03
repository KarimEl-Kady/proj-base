<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PackageListCommand extends Command
{
    protected $signature = 'package:list';

    protected $description = 'List local packages in app/Vendor and their install status';

    public function handle(): int
    {
        $vendorPath = base_path(config('project.paths.vendor', 'app/Vendor'));

        if (! File::isDirectory($vendorPath)) {
            $this->warn("Local vendor directory not found: {$vendorPath}");

            return self::SUCCESS;
        }

        $required = $this->rootRequirements();

        $rows = collect(File::directories($vendorPath))
            ->map(function (string $dir) use ($required) {
                $composerFile = "{$dir}/composer.json";

                if (! File::exists($composerFile)) {
                    return [basename($dir), '<fg=red>missing composer.json</>', '—', '—'];
                }

                $composer = json_decode(File::get($composerFile), true) ?: [];
                $name = $composer['name'] ?? basename($dir);

                return [
                    $name,
                    $composer['version'] ?? 'dev',
                    array_key_first($composer['autoload']['psr-4'] ?? []) ?? '—',
                    isset($required[$name]) ? '<fg=green>installed</>' : '<fg=yellow>not required</>',
                ];
            })
            ->values();

        if ($rows->isEmpty()) {
            $this->info('No local packages found. Create one with: php artisan make:package <Name>');

            return self::SUCCESS;
        }

        $this->table(['Package', 'Version', 'Namespace', 'Status'], $rows->all());

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    protected function rootRequirements(): array
    {
        $composer = json_decode(File::get(base_path('composer.json')), true) ?: [];

        return ($composer['require'] ?? []) + ($composer['require-dev'] ?? []);
    }
}
