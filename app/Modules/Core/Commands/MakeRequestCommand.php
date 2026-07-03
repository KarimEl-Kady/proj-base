<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeRequestCommand extends Command
{
    protected $signature = 'make:module:request
                            {module : Module name}
                            {name : Request name}
                            {--type=both : Request type (create, update, or both)}
                            {--rules= : Validation rules as JSON or pipe-separated}';

    protected $description = 'Create form requests in a module';

    protected string $modulePath;

    protected string $namespace;

    protected string $moduleName;

    protected string $stubsPath;

    public function handle(): int
    {
        $this->stubsPath = base_path('app/Modules/Core/Stubs/standalone');

        $this->moduleName = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));
        $type = $this->option('type');
        $rules = $this->option('rules');

        $this->modulePath = module_path($this->moduleName);
        $this->namespace = "App\\Modules\\{$this->moduleName}";

        if (! File::isDirectory($this->modulePath)) {
            $this->error("Module [{$this->moduleName}] does not exist.");

            return self::FAILURE;
        }

        $rulesArray = $rules ? $this->parseRules($rules) : [];

        if ($type === 'create' || $type === 'both') {
            $this->makeCreateRequest($name, $rulesArray);
        }

        if ($type === 'update' || $type === 'both') {
            $this->makeUpdateRequest($name, $rulesArray);
        }

        return self::SUCCESS;
    }

    protected function parseRules(string $rules): array
    {
        if (Str::isJson($rules)) {
            return json_decode($rules, true) ?? [];
        }

        $parsed = [];
        $parts = explode('|', $rules);
        foreach ($parts as $part) {
            if (str_contains($part, ':')) {
                [$field, $ruleStr] = explode(':', $part, 2);
                $parsed[$field] = array_map('trim', explode(',', $ruleStr));
            }
        }

        return $parsed;
    }

    protected function makeCreateRequest(string $name, array $rules): void
    {
        $className = "Create{$name}Request";
        $rulesString = $this->buildRulesString($rules, 'required');

        $content = $this->renderStub('create-request', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => $className,
            '{{ rules }}' => $rulesString,
        ]);

        $this->write("Requests/{$className}.php", $content);
        $this->info("Create Request [{$className}] created in [{$this->moduleName}] module.");
    }

    protected function makeUpdateRequest(string $name, array $rules): void
    {
        $className = "Update{$name}Request";
        $rulesString = $this->buildRulesString($rules, 'sometimes');

        $content = $this->renderStub('update-request', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => $className,
            '{{ rules }}' => $rulesString,
        ]);

        $this->write("Requests/{$className}.php", $content);
        $this->info("Update Request [{$className}] created in [{$this->moduleName}] module.");
    }

    protected function buildRulesString(array $rules, string $defaultVerb): string
    {
        if (empty($rules)) {
            return '            //';
        }

        $lines = [];
        foreach ($rules as $field => $fieldRules) {
            $ruleStr = is_array($fieldRules) ? implode("', '", $fieldRules) : $fieldRules;
            $lines[] = "            '{$field}' => ['{$ruleStr}'],";
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
