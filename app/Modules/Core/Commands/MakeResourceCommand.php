<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeResourceCommand extends Command
{
    protected $signature = 'make:module:resource
                            {module : Module name}
                            {name : Resource name}
                            {--fields= : Comma-separated field names to include}';

    protected $description = 'Create an API resource in a module';

    protected string $modulePath;

    protected string $namespace;

    protected string $moduleName;

    public function handle(): int
    {
        $this->moduleName = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));
        $fields = $this->option('fields');

        $this->modulePath = module_path($this->moduleName);
        $this->namespace = "App\\Modules\\{$this->moduleName}";

        if (! File::isDirectory($this->modulePath)) {
            $this->error("Module [{$this->moduleName}] does not exist.");

            return self::FAILURE;
        }

        $fieldsArray = $fields ? array_map('trim', explode(',', $fields)) : ['id', 'created_at', 'updated_at'];
        $fieldsString = $this->buildFieldsString($fieldsArray);

        $content = <<<PHP
<?php

namespace {$this->namespace}\\Resources;

use App\\Modules\\Core\\Resources\\BaseResource;
use Illuminate\\Http\\Request;

class {$name} extends BaseResource
{
    public function toArray(Request \$request): array
    {
        return [
{$fieldsString}
        ];
    }
}

PHP;

        $this->write("Resources/{$name}.php", $content);
        $this->info("Resource [{$name}] created in [{$this->moduleName}] module.");

        return self::SUCCESS;
    }

    protected function buildFieldsString(array $fields): string
    {
        $lines = [];
        foreach ($fields as $field) {
            $field = trim($field);
            if (Str::ends_with($field, '_at')) {
                $lines[] = "            '{$field}' => \$this->{$field}?->toISOString(),";
            } elseif ($field === 'id') {
                $lines[] = "            '{$field}' => \$this->uuid,";
            } else {
                $lines[] = "            '{$field}' => \$this->{$field},";
            }
        }

        return implode("\n", $lines);
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
