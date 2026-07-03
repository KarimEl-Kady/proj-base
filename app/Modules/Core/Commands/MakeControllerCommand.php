<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeControllerCommand extends Command
{
    protected $signature = 'make:module:controller
                            {module : Module name}
                            {name : Controller name}
                            {--type=api : Controller type (api or web)}
                            {--service= : Associated service name}
                            {--resource= : Associated resource name}
                            {--create-request= : Create request class}
                            {--update-request= : Update request class}';

    protected $description = 'Create a controller in a module';

    protected string $modulePath;

    protected string $namespace;

    protected string $moduleName;

    protected string $stubsPath;

    public function handle(): int
    {
        $this->stubsPath = base_path('app/Modules/Core/Stubs/standalone');

        $this->moduleName = Str::studly($this->argument('module'));
        $name = Str::studly($this->argument('name'));
        $type = strtolower($this->option('type'));
        $serviceName = $this->option('service') ?: Str::studly($name).'Service';
        $resourceName = $this->option('resource') ?: Str::studly($name).'Resource';
        $createRequest = $this->option('create-request') ?: 'Create'.Str::studly($name).'Request';
        $updateRequest = $this->option('update-request') ?: 'Update'.Str::studly($name).'Request';

        $this->modulePath = module_path($this->moduleName);
        $this->namespace = "App\\Modules\\{$this->moduleName}";

        if (! File::isDirectory($this->modulePath)) {
            $this->error("Module [{$this->moduleName}] does not exist.");

            return self::FAILURE;
        }

        $pluralKebab = Str::kebab(Str::plural($name));
        $pluralSnake = Str::snake(Str::plural($name));
        $paramName = lcfirst($name);
        $serviceVar = lcfirst($serviceName);
        $humanName = Str::headline($pluralKebab);

        if ($type === 'api') {
            $this->makeApiController($name, $serviceName, $serviceVar, $resourceName, $createRequest, $updateRequest, $pluralKebab, $pluralSnake, $paramName, $humanName);
        } else {
            $this->makeWebController($name, $serviceName, $serviceVar, $createRequest, $updateRequest, $pluralKebab, $pluralSnake, $paramName);
        }

        return self::SUCCESS;
    }

    protected function makeApiController(
        string $name,
        string $serviceName,
        string $serviceVar,
        string $resourceName,
        string $createRequest,
        string $updateRequest,
        string $pluralKebab,
        string $pluralSnake,
        string $paramName,
        string $humanName
    ): void {
        $content = $this->renderStub('api-controller', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => $name,
            '{{ serviceName }}' => $serviceName,
            '{{ serviceVar }}' => $serviceVar,
            '{{ resourceName }}' => $resourceName,
            '{{ createRequest }}' => $createRequest,
            '{{ updateRequest }}' => $updateRequest,
            '{{ pluralKebab }}' => $pluralKebab,
            '{{ pluralSnake }}' => $pluralSnake,
            '{{ paramName }}' => $paramName,
            '{{ humanName }}' => $humanName,
        ]);

        $this->write("Controllers/Api/{$name}.php", $content);
        $this->info("API Controller [{$name}] created in [{$this->moduleName}] module.");
    }

    protected function makeWebController(
        string $name,
        string $serviceName,
        string $serviceVar,
        string $createRequest,
        string $updateRequest,
        string $pluralKebab,
        string $pluralSnake,
        string $paramName
    ): void {
        $singularKebab = Str::kebab($name);

        $content = $this->renderStub('web-controller', [
            '{{ namespace }}' => $this->namespace,
            '{{ className }}' => $name,
            '{{ serviceName }}' => $serviceName,
            '{{ serviceVar }}' => $serviceVar,
            '{{ createRequest }}' => $createRequest,
            '{{ updateRequest }}' => $updateRequest,
            '{{ pluralKebab }}' => $pluralKebab,
            '{{ pluralSnake }}' => $pluralSnake,
            '{{ singularKebab }}' => $singularKebab,
            '{{ paramName }}' => $paramName,
        ]);

        $this->write("Controllers/Web/{$name}.php", $content);
        $this->info("Web Controller [{$name}] created in [{$this->moduleName}] module.");
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
