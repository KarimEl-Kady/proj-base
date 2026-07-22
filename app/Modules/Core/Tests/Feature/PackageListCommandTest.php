<?php

namespace App\Modules\Core\Tests\Feature;

use App\Modules\Core\Support\VendorGit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PackageListCommandTest extends TestCase
{
    protected string $localPackagePath;

    protected string $pulledPackagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->localPackagePath = base_path('app/Vendor/ReviewLocalPackage');
        $this->pulledPackagePath = base_path('app/Vendor/ReviewPulledPackage');

        File::deleteDirectory($this->localPackagePath);
        File::deleteDirectory($this->pulledPackagePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->localPackagePath);
        File::deleteDirectory($this->pulledPackagePath);

        parent::tearDown();
    }

    public function test_lists_source_and_ref_columns(): void
    {
        File::ensureDirectoryExists($this->localPackagePath);
        VendorGit::writeJson("{$this->localPackagePath}/composer.json", [
            'name' => 'local/review-local-package',
        ]);

        File::ensureDirectoryExists($this->pulledPackagePath);
        VendorGit::writeJson("{$this->pulledPackagePath}/composer.json", [
            'name' => 'local/review-pulled-package',
            'extra' => ['vendor-source' => ['repo' => 'git@example.com:org/pulled.git', 'ref' => 'main']],
        ]);

        Artisan::call('package:list');
        $output = Artisan::output();

        $this->assertStringContainsString('local/review-local-package', $output);
        $this->assertStringContainsString('local/review-pulled-package', $output);
        $this->assertStringContainsString('git@example.com:org/pulled.git', $output);
    }
}
