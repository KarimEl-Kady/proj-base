<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module
                            {name : Module name in StudlyCase (e.g. Blog, ProductCatalog)}
                            {--api-only : Skip web controller and requests}
                            {--web-only : Skip API controller and resource}';

    protected $description = 'Scaffold a new HMVC module with controllers, model, repository, service, requests, and resource';

    protected string $moduleName;

    protected string $pluralKebab;

    protected string $pluralSnake;

    protected string $singularKebab;

    protected string $paramName;

    protected string $serviceVar;

    protected string $tableName;

    protected string $modulePath;

    protected string $namespace;

    protected string $stubsPath;

    public function handle(): int
    {
        $this->stubsPath = base_path('app/Modules/Core/Stubs/module');

        $this->moduleName = Str::studly($this->argument('name'));
        $this->pluralKebab = Str::plural(Str::kebab($this->moduleName));
        $this->pluralSnake = Str::plural(Str::snake($this->moduleName));
        $this->singularKebab = Str::kebab($this->moduleName);
        $this->paramName = lcfirst($this->moduleName);
        $this->serviceVar = lcfirst($this->moduleName).'Service';
        $this->tableName = $this->pluralSnake;
        $this->modulePath = module_path($this->moduleName);
        $this->namespace = "App\\Modules\\{$this->moduleName}";

        if (File::isDirectory($this->modulePath)) {
            $this->error("Module [{$this->moduleName}] already exists at {$this->modulePath}");

            return self::FAILURE;
        }

        $this->createDirectories();
        $this->makeServiceProvider();
        $this->makeModel();
        $this->makeRepository();
        $this->makeService();

        if (! $this->option('web-only')) {
            $this->makeApiController();
            $this->makeResource();
        }

        if (! $this->option('api-only')) {
            $this->makeWebController();
        }

        $this->makeRequests();

        $this->info("Module [{$this->moduleName}] scaffolded successfully.");
        $this->info("Add '{$this->moduleName}' to PROJECT_MODULES in your .env to activate it.");

        return self::SUCCESS;
    }

    protected function createDirectories(): void
    {
        foreach (config('project.module_structure', []) as $dir) {
            File::makeDirectory($this->modulePath.'/'.$dir, 0755, true);
        }
    }

    protected function makeServiceProvider(): void
    {
        $content = $this->renderStub('service-provider', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => "{$this->moduleName}ServiceProvider",
        ]);

        $this->write("Providers/{$this->moduleName}ServiceProvider.php", $content);
    }

    protected function makeModel(): void
    {
        $content = $this->renderStub('model', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => $this->moduleName,
            '{{ table }}' => $this->tableName,
        ]);

        $this->write("Models/{$this->moduleName}.php", $content);
    }

    protected function makeRepository(): void
    {
        $content = $this->renderStub('repository', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => "{$this->moduleName}Repository",
            '{{ modelName }}' => $this->moduleName,
        ]);

        $this->write("Repositories/{$this->moduleName}Repository.php", $content);
    }

    protected function makeService(): void
    {
        $content = $this->renderStub('service', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => "{$this->moduleName}Service",
            '{{ modelName }}' => $this->moduleName,
            '{{ repositoryName }}' => "{$this->moduleName}Repository",
        ]);

        $this->write("Services/{$this->moduleName}Service.php", $content);
    }

    protected function makeApiController(): void
    {
        $content = $this->renderStub('api-controller', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => "{$this->moduleName}Controller",
            '{{ serviceName }}' => "{$this->moduleName}Service",
            '{{ serviceVar }}' => $this->serviceVar,
            '{{ resourceName }}' => "{$this->moduleName}Resource",
            '{{ createRequest }}' => "Create{$this->moduleName}Request",
            '{{ updateRequest }}' => "Update{$this->moduleName}Request",
            '{{ pluralKebab }}' => $this->pluralKebab,
            '{{ pluralSnake }}' => $this->pluralSnake,
            '{{ paramName }}' => $this->paramName,
            '{{ humanName }}' => Str::headline($this->pluralKebab),
        ]);

        $this->write("Controllers/Api/{$this->moduleName}Controller.php", $content);
    }

    protected function makeWebController(): void
    {
        $content = $this->renderStub('web-controller', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => "{$this->moduleName}Controller",
            '{{ serviceName }}' => "{$this->moduleName}Service",
            '{{ serviceVar }}' => $this->serviceVar,
            '{{ createRequest }}' => "Create{$this->moduleName}Request",
            '{{ updateRequest }}' => "Update{$this->moduleName}Request",
            '{{ pluralKebab }}' => $this->pluralKebab,
            '{{ pluralSnake }}' => $this->pluralSnake,
            '{{ singularKebab }}' => $this->singularKebab,
            '{{ paramName }}' => $this->paramName,
        ]);

        $this->write("Controllers/Web/{$this->moduleName}Controller.php", $content);
    }

    protected function makeRequests(): void
    {
        $createContent = $this->renderStub('create-request', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => "Create{$this->moduleName}Request",
        ]);

        $this->write("Requests/Create{$this->moduleName}Request.php", $createContent);

        $updateContent = $this->renderStub('update-request', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => "Update{$this->moduleName}Request",
        ]);

        $this->write("Requests/Update{$this->moduleName}Request.php", $updateContent);
    }

    protected function makeResource(): void
    {
        $content = $this->renderStub('resource', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => "{$this->moduleName}Resource",
        ]);

        $this->write("Resources/{$this->moduleName}Resource.php", $content);
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