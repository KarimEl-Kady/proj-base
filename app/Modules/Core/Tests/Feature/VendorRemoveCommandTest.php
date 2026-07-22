<?php

namespace App\Modules\Core\Tests\Feature;

use App\Modules\Core\Support\VendorGit;
use App\Modules\Core\Tests\Feature\Concerns\InteractsWithVendorGitFixtures;
use Tests\TestCase;

class VendorRemoveCommandTest extends TestCase
{
    use InteractsWithVendorGitFixtures;

    protected string $hostRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostRepo = $this->makeHostRepo();
        VendorGit::$repoRootOverride = $this->hostRepo;
        VendorGit::$processEnvOverride = $this->vendorGitTestEnv();
    }

    protected function tearDown(): void
    {
        VendorGit::$repoRootOverride = null;
        VendorGit::$processEnvOverride = null;
        $this->cleanupVendorGitTempDirs();

        parent::tearDown();
    }

    public function test_removes_the_directory_and_the_composer_require(): void
    {
        $source = $this->makePackageSourceRepo('local/wallet', 'Wallet');
        $this->artisan("vendor:install {$source} --as=Wallet --ref=main")->assertSuccessful();

        $this->artisan('vendor:remove Wallet --force')->assertSuccessful();

        $this->assertDirectoryDoesNotExist("{$this->hostRepo}/app/Vendor/Wallet");
        $rootComposer = VendorGit::readJson("{$this->hostRepo}/composer.json");
        $this->assertArrayNotHasKey('local/wallet', $rootComposer['require']);
    }

    public function test_keep_files_option_leaves_the_directory_in_place(): void
    {
        $source = $this->makePackageSourceRepo('local/wallet', 'Wallet');
        $this->artisan("vendor:install {$source} --as=Wallet --ref=main")->assertSuccessful();

        $this->artisan('vendor:remove Wallet --force --keep-files')->assertSuccessful();

        $this->assertDirectoryExists("{$this->hostRepo}/app/Vendor/Wallet");
        $rootComposer = VendorGit::readJson("{$this->hostRepo}/composer.json");
        $this->assertArrayNotHasKey('local/wallet', $rootComposer['require']);
    }
}
