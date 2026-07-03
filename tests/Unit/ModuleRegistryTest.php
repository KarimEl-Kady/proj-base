<?php

namespace Tests\Unit;

use App\Modules\Core\Support\ModuleRegistry;
use Tests\TestCase;

class ModuleRegistryTest extends TestCase
{
    protected string $registryFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registryFile = sys_get_temp_dir().'/module_registry_test_'.uniqid().'.php';
        ModuleRegistry::$pathOverride = $this->registryFile;
    }

    protected function tearDown(): void
    {
        ModuleRegistry::$pathOverride = null;
        @unlink($this->registryFile);

        parent::tearDown();
    }

    public function test_missing_file_yields_empty_registry(): void
    {
        $this->assertSame([], ModuleRegistry::all());
        $this->assertSame([], ModuleRegistry::enabled());
        $this->assertFalse(ModuleRegistry::isEnabled('Blog'));
    }

    public function test_set_enable_disable_and_remove(): void
    {
        ModuleRegistry::set('Blog', true);
        ModuleRegistry::set('Shop', false);

        $this->assertTrue(ModuleRegistry::isEnabled('Blog'));
        $this->assertFalse(ModuleRegistry::isEnabled('Shop'));
        $this->assertTrue(ModuleRegistry::has('Shop'));
        $this->assertSame(['Blog'], ModuleRegistry::enabled());

        ModuleRegistry::set('Shop', true);
        $this->assertSame(['Blog', 'Shop'], ModuleRegistry::enabled());

        ModuleRegistry::remove('Blog');
        $this->assertFalse(ModuleRegistry::has('Blog'));
        $this->assertSame(['Shop'], ModuleRegistry::enabled());
    }

    public function test_written_file_is_valid_php_returning_the_map(): void
    {
        ModuleRegistry::set('Blog', true);

        $loaded = require $this->registryFile;

        $this->assertSame(['Blog' => true], $loaded);
    }
}
