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

    public function handle(): int
    {
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

    // ── ServiceProvider ─────────────────────────────────────────────

    protected function makeServiceProvider(): void
    {
        $name = "{$this->moduleName}ServiceProvider";

        $content = <<<PHP
        <?php

        namespace {$this->namespace}\\Providers;

        use Illuminate\\Support\\ServiceProvider;

        class {$name} extends ServiceProvider
        {
            public function boot(): void
            {
                //
            }
        }

        PHP;

        $this->write("Providers/{$name}.php", $content);
    }

    // ── Model ────────────────────────────────────────────────────────

    protected function makeModel(): void
    {
        $name = $this->moduleName;

        $content = <<<PHP
        <?php

        namespace {$this->namespace}\\Models;

        use App\\Modules\\Core\\Models\\Model;
        use App\\Modules\\Core\\Traits\\HasTenantScope;

        class {$name} extends Model
        {
            use HasTenantScope;

            protected \$table = '{$this->tableName}';

            protected function casts(): array
            {
                return [
                    //
                ];
            }
        }

        PHP;

        $this->write("Models/{$name}.php", $content);
    }

    // ── Repository ───────────────────────────────────────────────────

    protected function makeRepository(): void
    {
        $name = "{$this->moduleName}Repository";
        $modelName = $this->moduleName;

        $content = <<<PHP
        <?php

        namespace {$this->namespace}\\Repositories;

        use App\\Modules\\Core\\Repositories\\BaseRepository;
        use {$this->namespace}\\Models\\{$modelName};

        class {$name} extends BaseRepository
        {
            public function __construct({$modelName} \$model)
            {
                parent::__construct(\$model);
            }
        }

        PHP;

        $this->write("Repositories/{$name}.php", $content);
    }

    // ── Service ──────────────────────────────────────────────────────

    protected function makeService(): void
    {
        $name = "{$this->moduleName}Service";
        $repoName = "{$this->moduleName}Repository";
        $modelName = $this->moduleName;

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
    }

    // ── API Controller ───────────────────────────────────────────────

    protected function makeApiController(): void
    {
        $name = "{$this->moduleName}Controller";
        $serviceName = "{$this->moduleName}Service";
        $resourceName = "{$this->moduleName}Resource";
        $createRequest = "Create{$this->moduleName}Request";
        $updateRequest = "Update{$this->moduleName}Request";
        $humanName = Str::headline($this->pluralKebab);

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

        #[Prefix('api/v1/{$this->pluralKebab}')]
        #[Middleware('api')]
        class {$name} extends Controller
        {
            public function __construct(
                protected {$serviceName} \${$this->serviceVar}
            ) {}

            #[Get('/', name: 'api.{$this->pluralSnake}.index')]
            public function index(): JsonResponse
            {
                \$users = \$this->{$this->serviceVar}->paginate(20);

                return \$this->jsonResponse(
                    {$resourceName}::collection(\$users)->response()->getData(true),
                    '{$humanName} retrieved successfully.'
                );
            }

            #[Post('/', name: 'api.{$this->pluralSnake}.store')]
            public function store({$createRequest} \$request): JsonResponse
            {
                \$user = \$this->{$this->serviceVar}->create(\$request->validated());

                return \$this->jsonResponse(
                    new {$resourceName}(\$user),
                    '{$this->moduleName} created successfully.',
                    201
                );
            }

            #[Get('/{{$this->paramName}}', name: 'api.{$this->pluralSnake}.show')]
            public function show(string \$id): JsonResponse
            {
                \$user = \$this->{$this->serviceVar}->findOrFail(\$id);

                return \$this->jsonResponse(
                    new {$resourceName}(\$user),
                    '{$this->moduleName} retrieved successfully.'
                );
            }

            #[Put('/{{$this->paramName}}', name: 'api.{$this->pluralSnake}.update')]
            public function update({$updateRequest} \$request, string \$id): JsonResponse
            {
                \$user = \$this->{$this->serviceVar}->update(\$id, \$request->validated());

                return \$this->jsonResponse(
                    new {$resourceName}(\$user),
                    '{$this->moduleName} updated successfully.'
                );
            }

            #[Delete('/{{$this->paramName}}', name: 'api.{$this->pluralSnake}.destroy')]
            public function destroy(string \$id): JsonResponse
            {
                \$this->{$this->serviceVar}->delete(\$id);

                return \$this->jsonResponse(null, '{$this->moduleName} deleted successfully.');
            }
        }

        PHP;

        $this->write("Controllers/Api/{$name}.php", $content);
    }

    // ── Web Controller ───────────────────────────────────────────────

    protected function makeWebController(): void
    {
        $name = "{$this->moduleName}Controller";
        $serviceName = "{$this->moduleName}Service";
        $createRequest = "Create{$this->moduleName}Request";
        $updateRequest = "Update{$this->moduleName}Request";

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

        #[Prefix('{$this->pluralKebab}')]
        #[Middleware('web')]
        class {$name} extends Controller
        {
            public function __construct(
                protected {$serviceName} \${$this->serviceVar}
            ) {}

            #[Get('/', name: '{$this->pluralSnake}.index')]
            public function index(): View
            {
                \$records = \$this->{$this->serviceVar}->paginate(20);

                return view('{$this->singularKebab}::index', compact('records'));
            }

            #[Get('/create', name: '{$this->pluralSnake}.create')]
            public function create(): View
            {
                return view('{$this->singularKebab}::create');
            }

            #[Post('/', name: '{$this->pluralSnake}.store')]
            public function store({$createRequest} \$request): RedirectResponse
            {
                \$this->{$this->serviceVar}->create(\$request->validated());

                return redirect()->route('{$this->pluralSnake}.index')
                    ->with('success', '{$this->moduleName} created successfully.');
            }

            #[Get('/{{$this->paramName}}', name: '{$this->pluralSnake}.show')]
            public function show(string \$id): View
            {
                \$record = \$this->{$this->serviceVar}->findOrFail(\$id);

                return view('{$this->singularKebab}::show', compact('record'));
            }

            #[Get('/{{$this->paramName}}/edit', name: '{$this->pluralSnake}.edit')]
            public function edit(string \$id): View
            {
                \$record = \$this->{$this->serviceVar}->findOrFail(\$id);

                return view('{$this->singularKebab}::edit', compact('record'));
            }

            #[Put('/{{$this->paramName}}', name: '{$this->pluralSnake}.update')]
            public function update({$updateRequest} \$request, string \$id): RedirectResponse
            {
                \$this->{$this->serviceVar}->update(\$id, \$request->validated());

                return redirect()->route('{$this->pluralSnake}.show', \$id)
                    ->with('success', '{$this->moduleName} updated successfully.');
            }

            #[Delete('/{{$this->paramName}}', name: '{$this->pluralSnake}.destroy')]
            public function destroy(string \$id): RedirectResponse
            {
                \$this->{$this->serviceVar}->delete(\$id);

                return redirect()->route('{$this->pluralSnake}.index')
                    ->with('success', '{$this->moduleName} deleted successfully.');
            }
        }

        PHP;

        $this->write("Controllers/Web/{$name}.php", $content);
    }

    // ── Requests ─────────────────────────────────────────────────────

    protected function makeRequests(): void
    {
        $createName = "Create{$this->moduleName}Request";
        $updateName = "Update{$this->moduleName}Request";

        $createContent = <<<PHP
        <?php

        namespace {$this->namespace}\\Requests;

        use Illuminate\\Foundation\\Http\\FormRequest;

        class {$createName} extends FormRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return [
                    //
                ];
            }
        }

        PHP;

        $this->write("Requests/{$createName}.php", $createContent);

        $updateContent = <<<PHP
        <?php

        namespace {$this->namespace}\\Requests;

        use Illuminate\\Foundation\\Http\\FormRequest;

        class {$updateName} extends FormRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            public function rules(): array
            {
                return [
                    //
                ];
            }
        }

        PHP;

        $this->write("Requests/{$updateName}.php", $updateContent);
    }

    // ── Resource ─────────────────────────────────────────────────────

    protected function makeResource(): void
    {
        $name = "{$this->moduleName}Resource";

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
                    'id' => \$this->uuid,
                    'created_at' => \$this->created_at?->toISOString(),
                    'updated_at' => \$this->updated_at?->toISOString(),
                ];
            }
        }

        PHP;

        $this->write("Resources/{$name}.php", $content);
    }

    // ── Helpers ──────────────────────────────────────────────────────

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
