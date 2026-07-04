<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ModuleMakeCommand extends Command
{
    protected const TYPES = [
        'model', 'migration', 'controller', 'request', 'resource', 'service',
        'repository', 'seeder', 'factory', 'command', 'job', 'event',
        'listener', 'middleware', 'policy', 'observer', 'test',
    ];

    protected $signature = 'module:make
                            {module? : Existing module name in StudlyCase (omit to choose from a list)}
                            {type? : Component type (model, migration, controller, request, resource, service, repository, seeder, factory, command, job, event, listener, middleware, policy, observer)}
                            {name? : Component name (for migrations use snake_case, e.g. create_posts_table)}
                            {--web : Generate a web controller instead of an API controller}
                            {--fetch : Generate a request extending FetchRequest (listing filters) instead of BaseRequest}
                            {--unit : Generate a unit test (Tests/Unit) instead of a feature test}
                            {--fillable= : For models: comma-separated fillable columns}';

    protected $description = 'Generate a single component inside an existing module (interactive when arguments are omitted)';

    protected string $module;

    protected string $namespace;

    protected bool $interactive = false;

    public function handle(): int
    {
        $this->interactive = $this->argument('type') === null;

        $module = $this->argument('module') ?? $this->askForModule();

        if ($module === null) {
            $this->error('No modules exist yet. Run: php artisan make:module');

            return self::FAILURE;
        }

        $this->module = Str::studly($module);
        $this->namespace = "App\\Modules\\{$this->module}";

        if (! File::isDirectory(module_path($this->module))) {
            $this->error("Module [{$this->module}] does not exist. Run: php artisan make:module {$this->module}");

            return self::FAILURE;
        }

        $type = strtolower($this->argument('type') ?? select('Which component type?', self::TYPES, scroll: 12));
        $name = $this->argument('name') ?? $this->askForName($type);

        $result = match ($type) {
            'model' => $this->makeModel(Str::studly($name)),
            'migration' => $this->makeMigration(Str::snake($name)),
            'controller' => $this->makeController(Str::studly($name)),
            'request' => $this->makeRequest(Str::studly($name)),
            'resource' => $this->makeResource(Str::studly($name)),
            'service' => $this->makeService(Str::studly($name)),
            'repository' => $this->makeRepository(Str::studly($name)),
            'seeder' => $this->makeSeeder(Str::studly($name)),
            'factory' => $this->makeFactory(Str::studly($name)),
            'command' => $this->makeCommand(Str::studly($name)),
            'job' => $this->makeSimple(Str::studly($name), 'Jobs', $this->jobStub(Str::studly($name))),
            'event' => $this->makeSimple(Str::studly($name), 'Events', $this->eventStub(Str::studly($name))),
            'listener' => $this->makeSimple(Str::studly($name), 'Listeners', $this->listenerStub(Str::studly($name))),
            'middleware' => $this->makeSimple(Str::studly($name), 'Middleware', $this->middlewareStub(Str::studly($name))),
            'policy' => $this->makeSimple(Str::studly($name), 'Policies', $this->policyStub(Str::studly($name))),
            'observer' => $this->makeSimple(Str::studly($name), 'Observers', $this->observerStub(Str::studly($name))),
            'test' => $this->makeTest(Str::studly($name)),
            default => null,
        };

        if ($result === null) {
            $this->error("Unknown component type [{$type}].");

            return self::FAILURE;
        }

        return $result;
    }

    // ── Interactive prompts ──────────────────────────────────────────

    protected function askForModule(): ?string
    {
        $modules = collect(File::directories(module_path()))
            ->map(fn (string $dir) => basename($dir))
            ->reject(fn (string $module) => $module === 'Core')
            ->values();

        if ($modules->isEmpty()) {
            return null;
        }

        return select('Which module?', $modules->all());
    }

    protected function askForName(string $type): string
    {
        return text(
            label: ucfirst($type).' name',
            placeholder: $type === 'migration' ? 'e.g. create_posts_table' : 'e.g. Post',
            required: true,
        );
    }

    // ── Generators ───────────────────────────────────────────────────

    protected function makeModel(string $name): int
    {
        $table = Str::plural(Str::snake($name));

        $fillable = (string) ($this->option('fillable')
            ?? ($this->interactive ? text('Fillable columns (comma-separated, optional)', placeholder: 'e.g. title,slug,body') : ''));

        $fillableAttr = '';
        $fillableImport = '';

        if (trim($fillable) !== '') {
            $columns = collect(explode(',', $fillable))
                ->map(fn (string $column) => "'".trim($column)."'")
                ->implode(', ');
            $fillableAttr = "#[Fillable([{$columns}])]\n";
            $fillableImport = "use Illuminate\\Database\\Eloquent\\Attributes\\Fillable;\n";
        }

        return $this->write("Models/{$name}.php", <<<PHP
        <?php

        namespace {$this->namespace}\\Models;

        use App\\Modules\\Core\\Models\\Model;
        use App\\Modules\\Core\\Traits\\HasTenantScope;
        {$fillableImport}
        {$fillableAttr}class {$name} extends Model
        {
            use HasTenantScope;

            protected \$table = '{$table}';

            /** Columns matched by the FetchRequest `word` filter. */
            protected array \$searchable = [];

            protected function casts(): array
            {
                return [
                    //
                ];
            }
        }
        PHP);
    }

    protected function makeMigration(string $name): int
    {
        $timestamp = now()->format('Y_m_d_His');
        $table = $this->guessTableName($name);

        if (Str::startsWith($name, 'create_')) {
            $body = <<<PHP
                    Schema::create('{$table}', function (Blueprint \$table) {
                        \$table->id();
                        \$table->uuid('uuid')->unique();
                        // \$table->foreignId('tenant_id')->nullable()->index();
                        \$table->timestamps();
                    });
            PHP;
            $down = "Schema::dropIfExists('{$table}');";
        } else {
            $body = <<<PHP
                    Schema::table('{$table}', function (Blueprint \$table) {
                        //
                    });
            PHP;
            $down = '//';
        }

        return $this->write("Database/Migrations/{$timestamp}_{$name}.php", <<<PHP
        <?php

        use Illuminate\\Database\\Migrations\\Migration;
        use Illuminate\\Database\\Schema\\Blueprint;
        use Illuminate\\Support\\Facades\\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
        {$body}
            }

            public function down(): void
            {
                {$down}
            }
        };
        PHP);
    }

    protected function makeController(string $name): int
    {
        if (! Str::endsWith($name, 'Controller')) {
            $name .= 'Controller';
        }

        $base = Str::beforeLast($name, 'Controller');
        $pluralKebab = Str::plural(Str::kebab($base));
        $pluralSnake = Str::plural(Str::snake($base));

        $web = $this->option('web')
            || ($this->interactive && select('Controller kind', ['api' => 'API', 'web' => 'Web']) === 'web');

        if ($web) {
            $result = $this->write("Controllers/Web/{$name}.php", <<<PHP
            <?php

            namespace {$this->namespace}\\Controllers\\Web;

            use App\\Modules\\Core\\Controllers\\Controller;
            use Illuminate\\View\\View;

            class {$name} extends Controller
            {
                public function index(): View
                {
                    return view('{$pluralKebab}::index');
                }
            }
            PHP);

            $this->hintRouteFile(
                'web',
                "Route::get('/{$pluralKebab}', [{$this->namespace}\\Controllers\\Web\\{$name}::class, 'index'])->name('{$pluralSnake}.index');"
            );

            return $result;
        }

        $apiPrefix = config('project.api.prefix', 'api');
        $apiVersion = config('project.api.version', 'v1');

        $result = $this->write("Controllers/Api/{$name}.php", <<<PHP
        <?php

        namespace {$this->namespace}\\Controllers\\Api;

        use App\\Modules\\Core\\Controllers\\Controller;
        use Illuminate\\Http\\JsonResponse;

        class {$name} extends Controller
        {
            public function index(): JsonResponse
            {
                return \$this->successResponse([]);
            }
        }
        PHP);

        $this->hintRouteFile(
            'api',
            "Route::get('/{$apiPrefix}/{$apiVersion}/{$pluralKebab}', [{$this->namespace}\\Controllers\\Api\\{$name}::class, 'index'])->name('api.{$pluralSnake}.index');"
        );

        return $result;
    }

    /**
     * Controllers generated one-at-a-time aren't auto-wired into
     * Routes/{api,web}.php (unlike make:module, which writes the whole file
     * up front, since it knows every route in advance) — point the
     * developer at what to add.
     */
    protected function hintRouteFile(string $type, string $snippet): void
    {
        $this->newLine();
        $this->warn("Add this to Routes/{$type}.php:");
        $this->line("  {$snippet}");
    }

    protected function makeRequest(string $name): int
    {
        if (! Str::endsWith($name, 'Request')) {
            $name .= 'Request';
        }

        $fetch = $this->option('fetch')
            || ($this->interactive && select('Request kind', [
                'basic' => 'Basic (extends BaseRequest)',
                'fetch' => 'Fetch/listing (extends FetchRequest — pagination, word, sort)',
            ]) === 'fetch');

        if ($fetch) {
            return $this->write("Requests/{$name}.php", <<<PHP
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
            PHP);
        }

        return $this->write("Requests/{$name}.php", <<<PHP
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
        PHP);
    }

    protected function makeResource(string $name): int
    {
        if (! Str::endsWith($name, 'Resource')) {
            $name .= 'Resource';
        }

        return $this->write("Resources/{$name}.php", <<<PHP
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
        PHP);
    }

    protected function makeService(string $name): int
    {
        if (! Str::endsWith($name, 'Service')) {
            $name .= 'Service';
        }

        $base = Str::beforeLast($name, 'Service');
        $repository = "{$base}Repository";

        if (! File::exists(module_path($this->module, "Repositories/{$repository}.php"))) {
            $this->warn("Repository [{$repository}] does not exist yet. Generate it with: php artisan module:make {$this->module} repository {$base}");
        }

        return $this->write("Services/{$name}.php", <<<PHP
        <?php

        namespace {$this->namespace}\\Services;

        use App\\Modules\\Core\\Services\\BaseService;
        use {$this->namespace}\\Repositories\\{$repository};

        class {$name} extends BaseService
        {
            public function __construct({$repository} \$repository)
            {
                parent::__construct(\$repository);
            }
        }
        PHP);
    }

    protected function makeRepository(string $name): int
    {
        if (! Str::endsWith($name, 'Repository')) {
            $name .= 'Repository';
        }

        $model = Str::beforeLast($name, 'Repository');

        if (! File::exists(module_path($this->module, "Models/{$model}.php"))) {
            $this->warn("Model [{$model}] does not exist yet. Generate it with: php artisan module:make {$this->module} model {$model}");
        }

        return $this->write("Repositories/{$name}.php", <<<PHP
        <?php

        namespace {$this->namespace}\\Repositories;

        use App\\Modules\\Core\\Repositories\\BaseRepository;
        use {$this->namespace}\\Models\\{$model};

        class {$name} extends BaseRepository
        {
            public function __construct({$model} \$model)
            {
                parent::__construct(\$model);
            }
        }
        PHP);
    }

    protected function makeSeeder(string $name): int
    {
        if (! Str::endsWith($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        return $this->write("Database/Seeders/{$name}.php", <<<PHP
        <?php

        namespace {$this->namespace}\\Database\\Seeders;

        use Illuminate\\Database\\Seeder;

        class {$name} extends Seeder
        {
            public function run(): void
            {
                //
            }
        }
        PHP);
    }

    protected function makeFactory(string $name): int
    {
        if (! Str::endsWith($name, 'Factory')) {
            $name .= 'Factory';
        }

        $model = Str::beforeLast($name, 'Factory');

        return $this->write("Database/Factories/{$name}.php", <<<PHP
        <?php

        namespace {$this->namespace}\\Database\\Factories;

        use {$this->namespace}\\Models\\{$model};
        use Illuminate\\Database\\Eloquent\\Factories\\Factory;

        /**
         * @extends Factory<{$model}>
         */
        class {$name} extends Factory
        {
            protected \$model = {$model}::class;

            public function definition(): array
            {
                return [
                    //
                ];
            }
        }
        PHP);
    }

    protected function makeCommand(string $name): int
    {
        if (! Str::endsWith($name, 'Command')) {
            $name .= 'Command';
        }

        $signature = Str::kebab(Str::beforeLast($name, 'Command'));
        $moduleKebab = Str::kebab($this->module);

        return $this->write("Commands/{$name}.php", <<<PHP
        <?php

        namespace {$this->namespace}\\Commands;

        use Illuminate\\Console\\Command;

        class {$name} extends Command
        {
            protected \$signature = '{$moduleKebab}:{$signature}';

            protected \$description = 'Command description';

            public function handle(): int
            {
                return self::SUCCESS;
            }
        }
        PHP);
    }

    protected function makeSimple(string $name, string $dir, string $stub): int
    {
        return $this->write("{$dir}/{$name}.php", $stub);
    }

    protected function makeTest(string $name): int
    {
        if (! Str::endsWith($name, 'Test')) {
            $name .= 'Test';
        }

        if ($this->option('unit')
            || ($this->interactive && select('Test kind', ['feature' => 'Feature (HTTP + database)', 'unit' => 'Unit']) === 'unit')) {
            return $this->write("Tests/Unit/{$name}.php", <<<PHP
            <?php

            namespace {$this->namespace}\\Tests\\Unit;

            use Tests\\TestCase;

            class {$name} extends TestCase
            {
                public function test_example(): void
                {
                    \$this->assertTrue(true);
                }
            }
            PHP);
        }

        // For SomethingApiTest / SomethingTest, "Something" is the model base name.
        $base = preg_replace('/(Api)?Test$/', '', $name) ?: $this->module;

        if (File::exists(module_path($this->module, "Controllers/Api/{$base}Controller.php"))
            && File::exists(module_path($this->module, "Models/{$base}.php"))) {
            return $this->write("Tests/Feature/{$name}.php", $this->apiCrudTestStub($name, $base));
        }

        return $this->write("Tests/Feature/{$name}.php", <<<PHP
        <?php

        namespace {$this->namespace}\\Tests\\Feature;

        use Illuminate\\Foundation\\Testing\\RefreshDatabase;
        use Tests\\TestCase;

        class {$name} extends TestCase
        {
            use RefreshDatabase;

            protected function setUp(): void
            {
                parent::setUp();

                if (! in_array('{$this->module}', config('project.modules'))) {
                    \$this->markTestSkipped('Module [{$this->module}] is disabled.');
                }
            }

            public function test_example(): void
            {
                \$this->assertTrue(true);
            }
        }
        PHP);
    }

    protected function apiCrudTestStub(string $name, string $base): string
    {
        $pluralKebab = Str::plural(Str::kebab($base));
        $table = Str::plural(Str::snake($base));
        $apiPrefix = config('project.api.prefix', 'api');
        $apiVersion = config('project.api.version', 'v1');
        $url = "/{$apiPrefix}/{$apiVersion}/{$pluralKebab}";

        return <<<PHP
        <?php

        namespace {$this->namespace}\\Tests\\Feature;

        use {$this->namespace}\\Models\\{$base};
        use Illuminate\\Foundation\\Testing\\RefreshDatabase;
        use Tests\\TestCase;

        class {$name} extends TestCase
        {
            use RefreshDatabase;

            protected function setUp(): void
            {
                parent::setUp();

                if (! in_array('{$this->module}', config('project.modules'))) {
                    \$this->markTestSkipped('Module [{$this->module}] is disabled.');
                }
            }

            protected function makeRecord(): {$base}
            {
                return {$base}::query()->create([
                    // attributes required by your schema
                ]);
            }

            public function test_index_returns_records(): void
            {
                \$this->makeRecord();

                \$this->getJson('{$url}')
                    ->assertOk()
                    ->assertJsonPath('success', true);
            }

            public function test_store_creates_a_record(): void
            {
                \$this->postJson('{$url}', [
                    // payload matching Create{$base}Request rules
                ])->assertCreated();

                \$this->assertDatabaseCount('{$table}', 1);
            }

            public function test_show_returns_a_record(): void
            {
                \$record = \$this->makeRecord();

                \$this->getJson("{$url}/{\$record->uuid}")->assertOk();
            }

            public function test_update_modifies_a_record(): void
            {
                \$record = \$this->makeRecord();

                \$this->putJson("{$url}/{\$record->uuid}", [
                    // payload matching Update{$base}Request rules
                ])->assertOk();
            }

            public function test_destroy_deletes_a_record(): void
            {
                \$record = \$this->makeRecord();

                \$this->deleteJson("{$url}/{\$record->uuid}")->assertOk();

                \$this->assertDatabaseCount('{$table}', 0);
            }
        }
        PHP;
    }

    // ── Simple stubs ─────────────────────────────────────────────────

    protected function jobStub(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->namespace}\\Jobs;

        use Illuminate\\Contracts\\Queue\\ShouldQueue;
        use Illuminate\\Foundation\\Queue\\Queueable;

        class {$name} implements ShouldQueue
        {
            use Queueable;

            public function __construct()
            {
                //
            }

            public function handle(): void
            {
                //
            }
        }
        PHP;
    }

    protected function eventStub(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->namespace}\\Events;

        use Illuminate\\Foundation\\Events\\Dispatchable;
        use Illuminate\\Queue\\SerializesModels;

        class {$name}
        {
            use Dispatchable, SerializesModels;

            public function __construct()
            {
                //
            }
        }
        PHP;
    }

    protected function listenerStub(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->namespace}\\Listeners;

        class {$name}
        {
            public function handle(object \$event): void
            {
                //
            }
        }
        PHP;
    }

    protected function middlewareStub(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->namespace}\\Middleware;

        use Closure;
        use Illuminate\\Http\\Request;
        use Symfony\\Component\\HttpFoundation\\Response;

        class {$name}
        {
            public function handle(Request \$request, Closure \$next): Response
            {
                return \$next(\$request);
            }
        }
        PHP;
    }

    protected function policyStub(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->namespace}\\Policies;

        class {$name}
        {
            //
        }
        PHP;
    }

    protected function observerStub(string $name): string
    {
        return <<<PHP
        <?php

        namespace {$this->namespace}\\Observers;

        class {$name}
        {
            //
        }
        PHP;
    }

    // ── Helpers ──────────────────────────────────────────────────────

    protected function guessTableName(string $migration): string
    {
        if (preg_match('/^(?:create|update|alter)_(.+?)_table/', $migration, $m)) {
            return $m[1];
        }

        return Str::plural(Str::snake($this->module));
    }

    protected function write(string $relativePath, string $content): int
    {
        $path = module_path($this->module, $relativePath);

        if (File::exists($path)) {
            $this->error("File already exists: {$relativePath}");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, rtrim($content)."\n");

        $this->info("Created: app/Modules/{$this->module}/{$relativePath}");

        return self::SUCCESS;
    }
}
