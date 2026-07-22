<?php

namespace App\Modules\Core\Tests\Feature;

use App\Modules\Core\Support\VendorGit;
use App\Modules\Core\Tests\Feature\Concerns\InteractsWithVendorGitFixtures;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VendorInstallCommandTest extends TestCase
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

    public function test_installs_a_package_from_a_source_repo(): void
    {
        $source = $this->makePackageSourceRepo('local/wallet', 'Wallet');

        $this->artisan("vendor:install {$source} --as=Wallet --ref=main")
            ->assertSuccessful();

        $this->assertFileExists("{$this->hostRepo}/app/Vendor/Wallet/composer.json");
        $this->assertFileExists("{$this->hostRepo}/app/Vendor/Wallet/src/WalletServiceProvider.php");

        $rootComposer = VendorGit::readJson("{$this->hostRepo}/composer.json");
        $this->assertSame('^1.0', $rootComposer['require']['local/wallet']);

        $recordedSource = VendorGit::readVendorSource("{$this->hostRepo}/app/Vendor/Wallet/composer.json");
        $this->assertSame($source, $recordedSource['repo']);
        $this->assertSame('main', $recordedSource['ref']);
    }

    public function test_rejects_a_source_without_a_composer_json(): void
    {
        $source = $this->makeGitRepo();
        File::put("{$source}/README.md", 'no composer.json here');
        $this->runGit(['git', 'add', '-A'], $source);
        $this->runGit(['git', 'commit', '-q', '-m', 'init'], $source);

        $headBefore = VendorGit::currentHead();

        $this->artisan("vendor:install {$source} --as=Broken --ref=main")
            ->assertFailed();

        $this->assertDirectoryDoesNotExist("{$this->hostRepo}/app/Vendor/Broken");
        $this->assertSame($headBefore, VendorGit::currentHead());
    }

    public function test_refuses_to_install_over_an_existing_directory(): void
    {
        File::ensureDirectoryExists("{$this->hostRepo}/app/Vendor/Wallet");

        $source = $this->makePackageSourceRepo('local/wallet', 'Wallet');

        $this->artisan("vendor:install {$source} --as=Wallet --ref=main")
            ->assertFailed();
    }
}
