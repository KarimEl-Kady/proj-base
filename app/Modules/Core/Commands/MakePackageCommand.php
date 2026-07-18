<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class MakePackageCommand extends Command
{
    protected $signature = 'make:package
                            {name? : Package name in StudlyCase (e.g. Media, Payment; omit to be asked)}
                            {--description= : Package description for composer.json}';

    protected $description = 'Scaffold a local composer package in app/Vendor (installed via path repository)';

    public function handle(): int
    {
        $interactive = $this->argument('name') === null;

        $studly = Str::studly($this->argument('name') ?? text(
            label: 'Package name',
            placeholder: 'e.g. Payment, Notification',
            required: true,
        ));

        $kebab = Str::kebab($studly);
        $snake = Str::snake($studly);

        $vendorPrefix = config('project.vendor.composer_vendor', 'local');
        $rootNamespace = config('project.vendor.namespace', 'Local');

        $packageName = "{$vendorPrefix}/{$kebab}";
        $namespace = "{$rootNamespace}\\{$studly}";
        $path = base_path(config('project.paths.vendor', 'app/Vendor')."/{$studly}");

        $description = $this->option('description')
            ?? ($interactive ? text('Package description', default: "{$studly} local package") : null)
            ?: "{$studly} local package";

        if (File::isDirectory($path)) {
            $this->error("Package [{$packageName}] already exists at {$path}");

            return self::FAILURE;
        }

        File::ensureDirectoryExists("{$path}/src");
        File::ensureDirectoryExists("{$path}/config");
        File::ensureDirectoryExists("{$path}/database/migrations");
        File::ensureDirectoryExists("{$path}/tests");

        $this->writeComposerJson($path, $packageName, $namespace, $studly, $description);
        $this->writeServiceProvider($path, $namespace, $studly, $snake);
        $this->writeConfig($path, $snake);
        $this->writeReadme($path, $packageName, $studly, $description);
        File::copy(base_path('LICENSE'), "{$path}/LICENSE");
        File::put("{$path}/CHANGELOG.md", "# Changelog\n\n## 1.0.0\n\n- Initial release.\n");

        $this->info("Package [{$packageName}] scaffolded at app/Vendor/{$studly}");
        $this->newLine();
        $this->line('Install it into the project:');
        $this->line("  composer require {$packageName}:\"^1.0\"");

        return self::SUCCESS;
    }

    protected function writeComposerJson(string $path, string $packageName, string $namespace, string $studly, string $description): void
    {
        $json = [
            'name' => $packageName,
            'description' => $description,
            'type' => 'library',
            'version' => '1.0.0',
            'license' => 'MIT',
            'require' => [
                'php' => '^8.3',
            ],
            'autoload' => [
                'psr-4' => [
                    $namespace.'\\' => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    $namespace.'\\Tests\\' => 'tests/',
                ],
            ],
            'extra' => [
                'laravel' => [
                    'providers' => [
                        "{$namespace}\\{$studly}ServiceProvider",
                    ],
                ],
            ],
            'minimum-stability' => 'stable',
        ];

        File::put(
            "{$path}/composer.json",
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->line('  Created: composer.json');
    }

    protected function writeServiceProvider(string $path, string $namespace, string $studly, string $snake): void
    {
        $content = <<<PHP
        <?php

        namespace {$namespace};

        use Illuminate\Support\ServiceProvider;

        class {$studly}ServiceProvider extends ServiceProvider
        {
            public function register(): void
            {
                \$this->mergeConfigFrom(__DIR__.'/../config/{$snake}.php', '{$snake}');
            }

            public function boot(): void
            {
                \$this->loadMigrationsFrom(__DIR__.'/../database/migrations');

                \$this->publishes([
                    __DIR__.'/../config/{$snake}.php' => config_path('{$snake}.php'),
                ], '{$snake}-config');
            }
        }

        PHP;

        File::put("{$path}/src/{$studly}ServiceProvider.php", $content);
        $this->line("  Created: src/{$studly}ServiceProvider.php");
    }

    protected function writeConfig(string $path, string $snake): void
    {
        $content = <<<PHP
        <?php

        return [

            'enabled' => env('{$this->envKey($snake)}_ENABLED', true),

        ];

        PHP;

        File::put("{$path}/config/{$snake}.php", $content);
        $this->line("  Created: config/{$snake}.php");
    }

    protected function writeReadme(string $path, string $packageName, string $studly, string $description): void
    {
        $content = <<<MD
        # {$studly}

        {$description}

        Local package installed via composer path repository.

        ## Install

        ```bash
        composer require {$packageName}:"^1.0"
        ```
        MD;

        File::put("{$path}/README.md", $content."\n");
        $this->line('  Created: README.md');
    }

    protected function envKey(string $snake): string
    {
        return strtoupper($snake);
    }
}
