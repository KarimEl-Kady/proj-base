<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'make:module:repository
                            {module : Module name}
                            {name : Repository name}
                            {--model= : Associated model name}';

    protected $description = 'Create a repository in a module';

    protected string $modulePath;

    protected string $namespace;

    protected string $moduleName;

    protected string $stubsPath;

    public function handle(): int
    {
        $this->stubsPath = base_path('app/Modules/Core/Stubs/standalone');

        $this->moduleName = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));
        $modelName = $this->option('model') ?: Str::replace_last('Repository', '', $name);

        $this->modulePath = module_path($this->moduleName);
        $this->namespace = "App\\Modules\\{$this->moduleName}";

        if (! File::isDirectory($this->modulePath)) {
            $this->error("Module [{$this->moduleName}] does not exist.");

            return self::FAILURE;
        }

        $content = $this->renderStub('repository', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => $name,
            '{{ modelName }}' => $modelName,
        ]);

        $this->write("Repositories/{$name}.php", $content);
        $this->info("Repository [{$name}] created in [{$this->moduleName}] module.");

        return self::SUCCESS;
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