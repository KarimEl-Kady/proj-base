<?php

namespace App\Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\File;
use Local\Permission\Support\DefinitionLoader;
use Tests\TestCase;

/**
 * The generator is the base's most-used tool: whatever posture it emits
 * becomes the posture of every future module. These tests pin the security
 * relevant parts of its output — a generated module must be authenticated
 * AND authorized out of the box, with the permissions it references
 * actually declared, so nobody can forget a manual wiring step and ship
 * CRUD that is open to every logged-in user.
 */
class MakeModuleCommandTest extends TestCase
{
    protected string $modulePath;

    protected string $registryPath;

    protected string $registryBackup;

    protected string $codeownersPath;

    protected string $codeownersBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->modulePath = module_path('Widget');
        $this->registryPath = config_path('project_modules.php');
        $this->registryBackup = File::get($this->registryPath);
        $this->codeownersPath = base_path('.github/CODEOWNERS');
        $this->codeownersBackup = File::get($this->codeownersPath);

        File::deleteDirectory($this->modulePath);
    }

    protected function tearDown(): void
    {
        // The generator writes to the real filesystem and the real registry —
        // put both back exactly as they were.
        File::deleteDirectory($this->modulePath);
        File::put($this->registryPath, $this->registryBackup);
        File::put($this->codeownersPath, $this->codeownersBackup);

        parent::tearDown();
    }

    protected function generateWidgetModule(string $owner = ''): void
    {
        $this->artisan('make:module')
            ->expectsQuestion('Module name', 'Widget')
            ->expectsQuestion('Owning team (GitHub handle, e.g. @org/blog-team) — optional', $owner)
            ->expectsQuestion('Which controllers does the module need?', 'api')
            // The multiselect is keyed value => label; Laravel asserts against
            // both halves, so both are listed here.
            ->expectsChoice('Extras to generate', ['migration', 'test'], [
                'migration', 'seeder', 'factory', 'test',
                'Migration (create_widgets_table)', 'Seeder', 'Factory',
                'Feature test (API CRUD smoke test)',
            ])
            ->expectsConfirmation('Enable the module now?', 'yes')
            ->assertSuccessful();
    }

    public function test_generated_api_routes_are_authenticated_and_authorized_per_action(): void
    {
        $this->generateWidgetModule();

        $routes = File::get("{$this->modulePath}/Routes/api.php");

        $this->assertStringContainsString("middleware('auth:sanctum')", $routes);

        // Every action carries its own permission — not just auth.
        $this->assertStringContainsString("'index'])->middleware('permission:widgets.view')", $routes);
        $this->assertStringContainsString("'store'])->middleware('permission:widgets.create')", $routes);
        $this->assertStringContainsString("'show'])->middleware('permission:widgets.view')", $routes);
        $this->assertStringContainsString("'update'])->middleware('permission:widgets.update')", $routes);
        $this->assertStringContainsString("'destroy'])->middleware('permission:widgets.delete')", $routes);
    }

    public function test_generated_module_declares_the_permissions_its_routes_reference(): void
    {
        $this->generateWidgetModule();

        $definitions = require "{$this->modulePath}/Config/permissions.php";

        // Declared (not commented out) — so permission:seed picks them up and
        // the routes above are grantable rather than permanently 403.
        $this->assertSame(
            ['widgets.view', 'widgets.create', 'widgets.update', 'widgets.delete'],
            $definitions['permissions'],
        );
    }

    public function test_generated_permissions_are_discovered_by_the_definition_loader(): void
    {
        $this->generateWidgetModule();

        // The module's file must match permission.definition_paths, otherwise
        // the routes reference permissions that seeding never creates.
        $loaded = app(DefinitionLoader::class)->permissions();

        $this->assertContains('widgets.view', $loaded);
        $this->assertContains('widgets.delete', $loaded);
    }

    public function test_an_owner_answer_is_recorded_in_codeowners(): void
    {
        $this->generateWidgetModule('@org/widget-team');

        $this->assertStringContainsString(
            '/app/Modules/Widget/ @org/widget-team',
            File::get($this->codeownersPath),
        );
    }

    public function test_leaving_the_owner_prompt_blank_does_not_touch_codeowners(): void
    {
        $this->generateWidgetModule('');

        $this->assertSame($this->codeownersBackup, File::get($this->codeownersPath));
    }

    public function test_generated_files_are_valid_php(): void
    {
        $this->generateWidgetModule();

        $files = File::allFiles($this->modulePath);
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            exec('php -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $status);

            $this->assertSame(0, $status, "Generated file has a syntax error: {$file->getPathname()}\n".implode("\n", $output));
        }
    }
}
