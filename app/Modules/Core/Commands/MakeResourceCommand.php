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

    protected string $stubsPath;

    public function handle(): int
    {
        $this->stubsPath = base_path('app/Modules/Core/Stubs/standalone');

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

        $content = $this->renderStub('resource', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => $name,
            '{{ fields }}' => $fieldsString,
        ]);

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

    protected function renderStub(string $name, array $replacements = []): string
    {
        $path = $this->stubsPath.'/'.$name.'.stub';
        $content = File::get($path);

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    protected function write(string $relativePath, string $content): void
    {
        $path = $this->modulePath.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
        $this->line("  Created: {$relativePath}");
    }
}