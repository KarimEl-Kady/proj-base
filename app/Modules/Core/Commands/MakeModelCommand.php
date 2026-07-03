<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModelCommand extends Command
{
    protected $signature = 'make:module:model
                            {module : Module name}
                            {name : Model name}
                            {--fillable= : Comma-separated fillable fields}';

    protected $description = 'Create a model in a module';

    protected string $modulePath;

    protected string $namespace;

    protected string $moduleName;

    protected string $stubsPath;

    public function handle(): int
    {
        $this->stubsPath = base_path('app/Modules/Core/Stubs/standalone');

        $this->moduleName = Str::studly($this->argument('module'));
        $modelName = Str::studly($this->argument('name'));
        $fillable = $this->option('fillable');

        $this->modulePath = module_path($this->moduleName);
        $this->namespace = "App\\Modules\\{$this->moduleName}";

        if (! File::isDirectory($this->modulePath)) {
            $this->error("Module [{$this->moduleName}] does not exist.");

            return self::FAILURE;
        }

        $tableName = Str::snake(Str::plural($modelName));
        $fillableArray = $fillable ? $this->parseFillable($fillable) : [];
        $fillableString = $fillableArray
            ? "'".implode("', '", $fillableArray)."'"
            : '';

        $content = $this->renderStub('model', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => $modelName,
            '{{ table }}' => $tableName,
            '{{ fillable }}' => $fillableString,
        ]);

        $this->write("Models/{$modelName}.php", $content);
        $this->info("Model [{$modelName}] created in [{$this->moduleName}] module.");

        return self::SUCCESS;
    }

    protected function parseFillable(string $fields): array
    {
        return array_map('trim', explode(',', $fields));
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
