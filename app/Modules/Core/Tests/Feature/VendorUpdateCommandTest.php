<?php

namespace App\Modules\Core\Tests\Feature;

use App\Modules\Core\Support\VendorGit;
use App\Modules\Core\Tests\Feature\Concerns\InteractsWithVendorGitFixtures;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class VendorUpdateCommandTest extends TestCase
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

    public function test_pulls_upstream_changes_into_an_installed_package(): void
    {
        $source = $this->makePackageSourceRepo('local/wallet', 'Wallet');

        $this->artisan("vendor:install {$source} --as=Wallet --ref=main")->assertSuccessful();

        File::put("{$source}/src/NewFeature.php", "<?php\n\nnamespace Local\\Wallet;\n\nclass NewFeature\n{\n}\n");
        $this->runGit(['git', 'add', '-A'], $source);
        $this->runGit(['git', 'commit', '-q', '-m', 'add new feature'], $source);

        $this->artisan('vendor:update Wallet')->assertSuccessful();

        $this->assertFileExists("{$this->hostRepo}/app/Vendor/Wallet/src/NewFeature.php");
    }

    public function test_fails_when_package_has_no_recorded_source(): void
    {
        File::ensureDirectoryExists("{$this->hostRepo}/app/Vendor/Scratch");
        VendorGit::writeJson("{$this->hostRepo}/app/Vendor/Scratch/composer.json", ['name' => 'local/scratch']);

        $this->artisan('vendor:update Scratch')->assertFailed();
    }
}
