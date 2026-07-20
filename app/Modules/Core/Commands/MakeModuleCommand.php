<?php

namespace App\Modules\Core\Commands;

use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Core\Support\ModuleRuntimeCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeModuleCommand extends Command
{
    protected $signature = 'make:module';

    protected $description = 'Scaffold a new HMVC module (interactive wizard)';

    protected string $moduleName;

    protected string $pluralKebab;

    protected string $pluralSnake;

    protected string $singularKebab;

    protected string $paramName;

    protected string $serviceVar;

    protected string $tableName;

    protected string $modulePath;

    protected string $namespace;

    protected bool $withApi = true;

    protected bool $withWeb = true;

    /** @var array<int, string> */
    protected array $extras = [];

    protected bool $enable = true;

    protected ?string $owner = null;

    public function handle(): int
    {
        $this->gatherInteractively();

        $this->prepareNames();

        if (File::isDirectory($this->modulePath)) {
            $this->error("Module [{$this->moduleName}] already exists at {$this->modulePath}");

            return self::FAILURE;
        }

        $this->createDirectories();
        $this->makeServiceProvider();
        $this->makeModel();
        $this->makeRepository();
        $this->makeService();

        if ($this->withApi) {
            $this->makeApiController();
            $this->makeResource();
            $this->makeFetchRequest();
            $this->makeApiRoutes();
        }

        $this->makePermissionsConfig();

        if ($this->withWeb) {
            $this->makeWebController();
            $this->makeWebRoutes();
        }

        $this->makeDashboardRoutes();
        $this->makeRequests();
        $this->makeExtras();

        $this->recordOwnership();

        $this->newLine();
        $this->info("Module [{$this->moduleName}] scaffolded successfully.");

        if ($this->enable) {
            ModuleRegistry::set($this->moduleName, true);
            ModuleRuntimeCache::clear();
            $this->info('Registered as enabled; config, route, and event caches cleared.');
        } else {
            $this->line("Enable it later with: php artisan module:enable {$this->moduleName}");
        }

        return self::SUCCESS;
    }

    // ── Input gathering ──────────────────────────────────────────────

    protected function gatherInteractively(): void
    {
        $this->moduleName = Str::studly(text(
            label: 'Module name',
            placeholder: 'e.g. Blog, ProductCatalog',
            required: true,
            validate: fn (string $value) => File::isDirectory(module_path(Str::studly($value)))
                ? 'A module with this name already exists.'
                : null,
        ));

        $owner = text(
            label: 'Owning team (GitHub handle, e.g. @org/blog-team) — optional',
            required: false,
        );
        $this->owner = trim($owner) !== '' ? trim($owner) : null;

        $platform = select(
            label: 'Which controllers does the module need?',
            options: [
                'full' => 'API + Web',
                'api' => 'API only',
                'web' => 'Web only',
            ],
            default: match (config('project.platform')) {
                'api' => 'api',
                'web' => 'full',
                default => 'full',
            },
        );

        $this->withApi = $platform !== 'web';
        $this->withWeb = $platform !== 'api';

        $this->extras = multiselect(
            label: 'Extras to generate',
            options: [
                'migration' => 'Migration (create_'.Str::plural(Str::snake($this->moduleName)).'_table)',
                'seeder' => 'Seeder',
                'factory' => 'Factory',
                'test' => 'Feature test (API CRUD smoke test)',
            ],
            default: ['migration', 'test'],
        );

        $this->enable = confirm('Enable the module now?', default: true);
    }

    protected function prepareNames(): void
    {
        $this->pluralKebab = Str::plural(Str::kebab($this->moduleName));
        $this->pluralSnake = Str::plural(Str::snake($this->moduleName));
        $this->singularKebab = Str::kebab($this->moduleName);
        $this->paramName = lcfirst($this->moduleName);
        $this->serviceVar = lcfirst($this->moduleName).'Service';
        $this->tableName = $this->pluralSnake;
        $this->modulePath = module_path($this->moduleName);
        $this->namespace = "App\\Modules\\{$this->moduleName}";
    }

    /**
     * Appends this module to .github/CODEOWNERS if an owner was given and
     * the module isn't already listed there — so ownership is recorded at
     * the moment it's decided, not left to whoever remembers to edit the
     * file later. Silently does nothing without an owner or without a
     * CODEOWNERS file to append to (this command doesn't invent one; see
     * the shipped .github/CODEOWNERS for the format).
     */
    protected function recordOwnership(): void
    {
        if ($this->owner === null) {
            return;
        }

        $path = base_path('.github/CODEOWNERS');

        if (! File::exists($path)) {
            return;
        }

        $existing = File::get($path);
        $entry = "/app/Modules/{$this->moduleName}/";

        if (str_contains($existing, $entry)) {
            return;
        }

        File::append($path, "{$entry} {$this->owner}\n");
        $this->line("Recorded [{$this->owner}] as the owner of {$entry} in .github/CODEOWNERS.");
    }

    // ── Extras (delegated to module:make) ────────────────────────────

    protected function makeExtras(): void
    {
        foreach ($this->extras as $extra) {
            $this->callSilently('module:make', [
                'module' => $this->moduleName,
                'type' => $extra,
                'name' => match ($extra) {
                    'migration' => "create_{$this->tableName}_table",
                    'test' => "{$this->moduleName}Api",
                    default => $this->moduleName,
                },
            ]);

            $this->line("  Created: {$extra}");
        }
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

            /** Columns matched by the FetchRequest `word` filter. */
            protected array \$searchable = [];

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

        $content = <<<PHP
        <?php

        namespace {$this->namespace}\\Services;

        use App\\Modules\\Core\\Services\\BaseService;
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
        $fetchRequest = "Fetch{$this->moduleName}Request";
        $createRequest = "Create{$this->moduleName}Request";
        $updateRequest = "Update{$this->moduleName}Request";
        $humanName = Str::headline($this->pluralKebab);

        $content = <<<PHP
        <?php

        namespace {$this->namespace}\\Controllers\\Api;

        use App\\Modules\\Core\\Controllers\\Controller;
        use {$this->namespace}\\Requests\\{$createRequest};
        use {$this->namespace}\\Requests\\{$fetchRequest};
        use {$this->namespace}\\Requests\\{$updateRequest};
        use {$this->namespace}\\Resources\\{$resourceName};
        use {$this->namespace}\\Services\\{$serviceName};
        use Illuminate\\Http\\JsonResponse;

        class {$name} extends Controller
        {
            public function __construct(
                protected {$serviceName} \${$this->serviceVar}
            ) {}

            public function index({$fetchRequest} \$request): JsonResponse
            {
                \$records = \$this->{$this->serviceVar}->fetch(\$request);

                return \$this->successResponse(
                    {$resourceName}::collection(\$records)->response()->getData(true),
                    '{$humanName} retrieved successfully.'
                );
            }

            public function store({$createRequest} \$request): JsonResponse
            {
                \$record = \$this->{$this->serviceVar}->create(\$request->validated());

                return \$this->successResponse(
                    new {$resourceName}(\$record),
                    '{$this->moduleName} created successfully.',
                    201
                );
            }

            public function show(string \$id): JsonResponse
            {
                \$record = \$this->{$this->serviceVar}->findOrFail(\$id);

                return \$this->successResponse(
                    new {$resourceName}(\$record),
                    '{$this->moduleName} retrieved successfully.'
                );
            }

            public function update({$updateRequest} \$request, string \$id): JsonResponse
            {
                \$record = \$this->{$this->serviceVar}->update(\$id, \$request->validated());

                return \$this->successResponse(
                    new {$resourceName}(\$record),
                    '{$this->moduleName} updated successfully.'
                );
            }

            public function destroy(string \$id): JsonResponse
            {
                \$this->{$this->serviceVar}->delete(\$id);

                return \$this->successResponse(null, '{$this->moduleName} deleted successfully.');
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

        class {$name} extends Controller
        {
            public function __construct(
                protected {$serviceName} \${$this->serviceVar}
            ) {}

            public function index(): View
            {
                \$records = \$this->{$this->serviceVar}->paginate();

                return view('{$this->singularKebab}::index', compact('records'));
            }

            public function create(): View
            {
                return view('{$this->singularKebab}::create');
            }

            public function store({$createRequest} \$request): RedirectResponse
            {
                \$this->{$this->serviceVar}->create(\$request->validated());

                return redirect()->route('{$this->pluralSnake}.index')
                    ->with('success', '{$this->moduleName} created successfully.');
            }

            public function show(string \$id): View
            {
                \$record = \$this->{$this->serviceVar}->findOrFail(\$id);

                return view('{$this->singularKebab}::show', compact('record'));
            }

            public function edit(string \$id): View
            {
                \$record = \$this->{$this->serviceVar}->findOrFail(\$id);

                return view('{$this->singularKebab}::edit', compact('record'));
            }

            public function update({$updateRequest} \$request, string \$id): RedirectResponse
            {
                \$this->{$this->serviceVar}->update(\$id, \$request->validated());

                return redirect()->route('{$this->pluralSnake}.show', \$id)
                    ->with('success', '{$this->moduleName} updated successfully.');
            }

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

    // ── Route files ──────────────────────────────────────────────────

    protected function makeApiRoutes(): void
    {
        $controller = "{$this->moduleName}Controller";

        $content = <<<PHP
        <?php

        use {$this->namespace}\\Controllers\\Api\\{$controller};
        use Illuminate\\Support\\Facades\\Route;

        /*
        |--------------------------------------------------------------------------
        | {$this->moduleName} API Routes
        |--------------------------------------------------------------------------
        |
        | Loaded under the "api" middleware group by CoreServiceProvider.
        |
        */

        // Authenticated AND authorized by default: every action requires its
        // own permission (declared in Config/permissions.php, generated
        // alongside this file). Run `php artisan permission:seed` and grant a
        // role — until then these endpoints correctly 403 for everyone (fail
        // closed). If a read is truly public, move it out of this group and
        // drop its permission middleware deliberately.
        Route::prefix('{$this->pluralKebab}')->middleware('auth:sanctum')->group(function () {
            Route::get('/', [{$controller}::class, 'index'])->middleware('permission:{$this->pluralSnake}.view')->name('api.{$this->pluralSnake}.index');
            Route::post('/', [{$controller}::class, 'store'])->middleware('permission:{$this->pluralSnake}.create')->name('api.{$this->pluralSnake}.store');
            Route::get('/{{$this->paramName}}', [{$controller}::class, 'show'])->middleware('permission:{$this->pluralSnake}.view')->name('api.{$this->pluralSnake}.show');
            Route::put('/{{$this->paramName}}', [{$controller}::class, 'update'])->middleware('permission:{$this->pluralSnake}.update')->name('api.{$this->pluralSnake}.update');
            Route::delete('/{{$this->paramName}}', [{$controller}::class, 'destroy'])->middleware('permission:{$this->pluralSnake}.delete')->name('api.{$this->pluralSnake}.destroy');
        });

        PHP;

        $this->write('Routes/api.php', $content);
    }

    protected function makePermissionsConfig(): void
    {
        $content = <<<PHP
        <?php

        /*
        |--------------------------------------------------------------------------
        | {$this->moduleName} Module — Permission Definitions
        |--------------------------------------------------------------------------
        |
        | Owned by this module and merged into the central definitions by
        | local/permission's DefinitionLoader (config/permission.php →
        | definition_paths). Apply with: php artisan permission:seed
        |
        | These four back the per-action permission: middleware already wired
        | into Routes/api.php (and Routes/web.php). Reshape them if this
        | resource wants a coarser split (e.g. a single '{$this->pluralSnake}.manage'
        | for all writes) — just keep the route middleware in step. After
        | editing, run `php artisan permission:seed` and grant a role.
        |
        */

        return [

            'permissions' => [
                '{$this->pluralSnake}.view',
                '{$this->pluralSnake}.create',
                '{$this->pluralSnake}.update',
                '{$this->pluralSnake}.delete',
            ],

        ];

        PHP;

        $this->write('Config/permissions.php', $content);
    }

    protected function makeWebRoutes(): void
    {
        $controller = "{$this->moduleName}Controller";

        $content = <<<PHP
        <?php

        use {$this->namespace}\\Controllers\\Web\\{$controller};
        use Illuminate\\Support\\Facades\\Route;

        /*
        |--------------------------------------------------------------------------
        | {$this->moduleName} Web Routes
        |--------------------------------------------------------------------------
        |
        | Loaded under the "web" middleware group by CoreServiceProvider.
        |
        */

        // Authenticated AND authorized by default, same posture as the API
        // routes — each action requires its permission (Config/permissions.php).
        // Move a read out of the group and drop its permission middleware only
        // when it is deliberately public.
        Route::prefix('{$this->pluralKebab}')->middleware('auth')->group(function () {
            Route::get('/', [{$controller}::class, 'index'])->middleware('permission:{$this->pluralSnake}.view')->name('{$this->pluralSnake}.index');
            Route::get('/create', [{$controller}::class, 'create'])->middleware('permission:{$this->pluralSnake}.create')->name('{$this->pluralSnake}.create');
            Route::post('/', [{$controller}::class, 'store'])->middleware('permission:{$this->pluralSnake}.create')->name('{$this->pluralSnake}.store');
            Route::get('/{{$this->paramName}}', [{$controller}::class, 'show'])->middleware('permission:{$this->pluralSnake}.view')->name('{$this->pluralSnake}.show');
            Route::get('/{{$this->paramName}}/edit', [{$controller}::class, 'edit'])->middleware('permission:{$this->pluralSnake}.update')->name('{$this->pluralSnake}.edit');
            Route::put('/{{$this->paramName}}', [{$controller}::class, 'update'])->middleware('permission:{$this->pluralSnake}.update')->name('{$this->pluralSnake}.update');
            Route::delete('/{{$this->paramName}}', [{$controller}::class, 'destroy'])->middleware('permission:{$this->pluralSnake}.delete')->name('{$this->pluralSnake}.destroy');
        });

        PHP;

        $this->write('Routes/web.php', $content);
    }

    protected function makeDashboardRoutes(): void
    {
        if (! $this->withWeb) {
            $this->write('Routes/dashboard.php', <<<PHP
            <?php

            /*
            |--------------------------------------------------------------------------
            | {$this->moduleName} Dashboard Routes
            |--------------------------------------------------------------------------
            |
            | Loaded under project.routes.dashboard by CoreServiceProvider. This
            | module has no web controller to expose here — add one and wire it
            | up if needed.
            |
            */

            PHP);

            return;
        }

        $controller = "{$this->moduleName}Controller";

        $content = <<<PHP
        <?php

        use {$this->namespace}\\Controllers\\Web\\{$controller};
        use Illuminate\\Support\\Facades\\Route;

        /*
        |--------------------------------------------------------------------------
        | {$this->moduleName} Dashboard Routes
        |--------------------------------------------------------------------------
        |
        | Loaded under project.routes.dashboard (prefix, middleware, name prefix
        | from config/project.php) by CoreServiceProvider. Reuses the Web
        | controller — swap in a dedicated Controllers/Dashboard controller for
        | a real backoffice.
        |
        */

        Route::prefix('{$this->pluralKebab}')->group(function () {
            Route::get('/', [{$controller}::class, 'index'])->name('{$this->pluralSnake}.index');
            Route::get('/{{$this->paramName}}', [{$controller}::class, 'show'])->name('{$this->pluralSnake}.show');
            Route::get('/{{$this->paramName}}/edit', [{$controller}::class, 'edit'])->name('{$this->pluralSnake}.edit');
            Route::put('/{{$this->paramName}}', [{$controller}::class, 'update'])->name('{$this->pluralSnake}.update');
            Route::delete('/{{$this->paramName}}', [{$controller}::class, 'destroy'])->name('{$this->pluralSnake}.destroy');
        });

        PHP;

        $this->write('Routes/dashboard.php', $content);
    }

    // ── Requests ─────────────────────────────────────────────────────

    protected function makeFetchRequest(): void
    {
        $name = "Fetch{$this->moduleName}Request";

        $content = <<<PHP
        <?php

        namespace {$this->namespace}\\Requests;

        use App\\Modules\\Core\\Requests\\FetchRequest;

        class {$name} extends FetchRequest
        {
            public function rules(): array
            {
                return parent::rules() + [
                    // module-specific filters, e.g. 'status' => ['sometimes', 'in:draft,published'],
                ];
            }
        }

        PHP;

        $this->write("Requests/{$name}.php", $content);
    }

    protected function makeRequests(): void
    {
        foreach (['Create', 'Update'] as $action) {
            $name = "{$action}{$this->moduleName}Request";

            $content = <<<PHP
            <?php

            namespace {$this->namespace}\\Requests;

            use App\\Modules\\Core\\Requests\\BaseRequest;

            class {$name} extends BaseRequest
            {
                public function rules(): array
                {
                    return [
                        //
                    ];
                }
            }

            PHP;

            $this->write("Requests/{$name}.php", $content);
        }
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
