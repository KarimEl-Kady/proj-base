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

    public function handle(): int
    {
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
        $content = <<<PHP
<?php

namespace {$this->namespace}\\Controllers\\Api;

use App\\Modules\\Core\\Controllers\\Controller;
use {$this->namespace}\\Requests\\{$createRequest};
use {$this->namespace}\\Requests\\{$updateRequest};
use {$this->namespace}\\Resources\\{$resourceName};
use {$this->namespace}\\Services\\{$serviceName};
use Illuminate\\Http\\JsonResponse;
use Spatie\\RouteAttributes\\Attributes\\Delete;
use Spatie\\RouteAttributes\\Attributes\\Get;
use Spatie\\RouteAttributes\\Attributes\\Middleware;
use Spatie\\RouteAttributes\\Attributes\\Post;
use Spatie\\RouteAttributes\\Attributes\\Prefix;
use Spatie\\RouteAttributes\\Attributes\\Put;

#[Prefix('api/v1/{$pluralKebab}')]
#[Middleware('api')]
class {$name} extends Controller
{
    public function __construct(
        protected {$serviceName} \${$serviceVar}
    ) {}

    #[Get('/', name: 'api.{$pluralSnake}.index')]
    public function index(): JsonResponse
    {
        \$records = \$this->{$serviceVar}->paginate(20);

        return \$this->jsonResponse(
            {$resourceName}::collection(\$records)->response()->getData(true),
            '{$humanName} retrieved successfully.'
        );
    }

    #[Post('/', name: 'api.{$pluralSnake}.store')]
    public function store({$createRequest} \$request): JsonResponse
    {
        \$record = \$this->{$serviceVar}->create(\$request->validated());

        return \$this->jsonResponse(
            new {$resourceName}(\$record),
            '{$name} created successfully.',
            201
        );
    }

    #[Get('/{{$paramName}}', name: 'api.{$pluralSnake}.show')]
    public function show(string \$id): JsonResponse
    {
        \$record = \$this->{$serviceVar}->findOrFail(\$id);

        return \$this->jsonResponse(
            new {$resourceName}(\$record),
            '{$name} retrieved successfully.'
        );
    }

    #[Put('/{{$paramName}}', name: 'api.{$pluralSnake}.update')]
    public function update({$updateRequest} \$request, string \$id): JsonResponse
    {
        \$record = \$this->{$serviceVar}->update(\$id, \$request->validated());

        return \$this->jsonResponse(
            new {$resourceName}(\$record),
            '{$name} updated successfully.'
        );
    }

    #[Delete('/{{$paramName}}', name: 'api.{$pluralSnake}.destroy')]
    public function destroy(string \$id): JsonResponse
    {
        \$this->{$serviceVar}->delete(\$id);

        return \$this->jsonResponse(null, '{$name} deleted successfully.');
    }
}

PHP;

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

        $content = <<<PHP
<?php

namespace {$this->namespace}\\Controllers\\Web;

use App\\Modules\\Core\\Controllers\\Controller;
use {$this->namespace}\\Requests\\{$createRequest};
use {$this->namespace}\\Requests\\{$updateRequest};
use {$this->namespace}\\Services\\{$serviceName};
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\View\\View;
use Spatie\\RouteAttributes\\Attributes\\Delete;
use Spatie\\RouteAttributes\\Attributes\\Get;
use Spatie\\RouteAttributes\\Attributes\\Middleware;
use Spatie\\RouteAttributes\\Attributes\\Post;
use Spatie\\RouteAttributes\\Attributes\\Prefix;
use Spatie\\RouteAttributes\\Attributes\\Put;

#[Prefix('{$pluralKebab}')]
#[Middleware('web')]
class {$name} extends Controller
{
    public function __construct(
        protected {$serviceName} \${$serviceVar}
    ) {}

    #[Get('/', name: '{$pluralSnake}.index')]
    public function index(): View
    {
        \$records = \$this->{$serviceVar}->paginate(20);

        return view('{$singularKebab}::index', compact('records'));
    }

    #[Get('/create', name: '{$pluralSnake}.create')]
    public function create(): View
    {
        return view('{$singularKebab}::create');
    }

    #[Post('/', name: '{$pluralSnake}.store')]
    public function store({$createRequest} \$request): RedirectResponse
    {
        \$this->{$serviceVar}->create(\$request->validated());

        return redirect()->route('{$pluralSnake}.index')
            ->with('success', '{$name} created successfully.');
    }

    #[Get('/{{$paramName}}', name: '{$pluralSnake}.show')]
    public function show(string \$id): View
    {
        \$record = \$this->{$serviceVar}->findOrFail(\$id);

        return view('{$singularKebab}::show', compact('record'));
    }

    #[Get('/{{$paramName}}/edit', name: '{$pluralSnake}.edit')]
    public function edit(string \$id): View
    {
        \$record = \$this->{$serviceVar}->findOrFail(\$id);

        return view('{$singularKebab}::edit', compact('record'));
    }

    #[Put('/{{$paramName}}', name: '{$pluralSnake}.update')]
    public function update({$updateRequest} \$request, string \$id): RedirectResponse
    {
        \$this->{$serviceVar}->update(\$id, \$request->validated());

        return redirect()->route('{$pluralSnake}.show', \$id)
            ->with('success', '{$name} updated successfully.');
    }

    #[Delete('/{{$paramName}}', name: '{$pluralSnake}.destroy')]
    public function destroy(string \$id): RedirectResponse
    {
        \$this->{$serviceVar}->delete(\$id);

        return redirect()->route('{$pluralSnake}.index')
            ->with('success', '{$name} deleted successfully.');
    }
}

PHP;

        $this->write("Controllers/Web/{$name}.php", $content);
        $this->info("Web Controller [{$name}] created in [{$this->moduleName}] module.");
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
