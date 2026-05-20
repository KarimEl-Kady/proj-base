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

    public function handle(): int
    {
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

        $content = <<<PHP
<?php

namespace {$this->namespace}\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;

class {$className} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
{$rulesString}
        ];
    }
}

PHP;

        $this->write("Requests/{$className}.php", $content);
        $this->info("Create Request [{$className}] created in [{$this->moduleName}] module.");
    }

    protected function makeUpdateRequest(string $name, array $rules): void
    {
        $className = "Update{$name}Request";
        $rulesString = $this->buildRulesString($rules, 'sometimes');

        $content = <<<PHP
<?php

namespace {$this->namespace}\\Requests;

use Illuminate\\Foundation\\Http\\FormRequest;

class {$className} extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
{$rulesString}
        ];
    }
}

PHP;

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
