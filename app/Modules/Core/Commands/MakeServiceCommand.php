<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeServiceCommand extends Command
{
    protected $signature = 'make:module:service
                            {module : Module name}
                            {name : Service name}
                            {--repository= : Associated repository name}';

    protected $description = 'Create a service in a module';

    protected string $modulePath;

    protected string $namespace;

    protected string $moduleName;

    public function handle(): int
    {
        $this->moduleName = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));
        $repoName = $this->option('repository') ?: Str::replace_last('Service', '', $name).'Repository';
        $modelName = Str::replace_last('Service', '', $name);

        $this->modulePath = module_path($this->moduleName);
        $this->namespace = "App\\Modules\\{$this->moduleName}";

        if (! File::isDirectory($this->modulePath)) {
            $this->error("Module [{$this->moduleName}] does not exist.");

            return self::FAILURE;
        }

        $content = <<<PHP
<?php

namespace {$this->namespace}\\Services;

use App\\Modules\\Core\\Services\\BaseService;
use {$this->namespace}\\Models\\{$modelName};
use {$this->namespace}\\Repositories\\{$repoName};

class {$name} extends BaseService
{
    public function __construct({$repoName} \$repository)
    {
        parent::__construct(\$repository);
    }
}

PHP;

        $this->write("Services/{$name}.php", $content);
        $this->info("Service [{$name}] created in [{$this->moduleName}] module.");

        return self::SUCCESS;
    }

    protected function write(string $relativePath, string $content): void
    {
        $path = $this->modulePath.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->cleanContent($content));
        $this->line("  Created: {$relativePath}");
    }

    protected function cleanContent(string $content): string
    {
        $lines = explode("\n", $content);
        if (count($lines) <= 1) {
            return $content;
        }
        $minIndent = PHP_INT_MAX;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^(\s+)/', $line, $m)) {
                $minIndent = min($minIndent, strlen($m[1]));
            } else {
                $minIndent = 0;
                break;
            }
        }
        if ($minIndent > 0 && $minIndent < PHP_INT_MAX) {
            foreach ($lines as &$line) {
                if ($line !== '') {
                    $line = substr($line, $minIndent);
                }
            }
        }

        return implode("\n", $lines)."\n";
    }
}
