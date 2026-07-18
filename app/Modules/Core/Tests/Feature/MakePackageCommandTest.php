<?php

namespace App\Modules\Core\Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MakePackageCommandTest extends TestCase
{
    protected string $packagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packagePath = base_path('app/Vendor/ReviewPackage');
        File::deleteDirectory($this->packagePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->packagePath);

        parent::tearDown();
    }

    public function test_generated_packages_carry_the_project_license(): void
    {
        $this->artisan('make:package ReviewPackage')->assertSuccessful();

        $composer = json_decode(
            File::get("{$this->packagePath}/composer.json"),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('MIT', $composer['license']);
        $this->assertFileEquals(base_path('LICENSE'), "{$this->packagePath}/LICENSE");
    }
}
