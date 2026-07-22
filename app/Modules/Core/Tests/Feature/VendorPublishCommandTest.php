<?php

namespace App\Modules\Core\Tests\Feature;

use App\Modules\Core\Support\VendorGit;
use App\Modules\Core\Tests\Feature\Concerns\InteractsWithVendorGitFixtures;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class VendorPublishCommandTest extends TestCase
{
    use InteractsWithVendorGitFixtures;

    protected string $hostRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hostRepo = $this->makeHostRepo();
        VendorGit::$repoRootOverride = $this->hostRepo;
        VendorGit::$processEnvOverride = $this->vendorGitTestEnv();

        // Simulate a package built in-repo via make:package, committed like
        // any other file in the host repo (no subtree involved yet).
        File::ensureDirectoryExists("{$this->hostRepo}/app/Vendor/Wallet/src");
        VendorGit::writeJson("{$this->hostRepo}/app/Vendor/Wallet/composer.json", [
            'name' => 'local/wallet',
            'version' => '1.0.0',
            'autoload' => ['psr-4' => ['Local\\Wallet\\' => 'src/']],
        ]);
        File::put("{$this->hostRepo}/app/Vendor/Wallet/src/WalletServiceProvider.php", "<?php\n\nnamespace Local\\Wallet;\n\nclass WalletServiceProvider\n{\n}\n");
        $this->runGit(['git', 'add', '-A'], $this->hostRepo);
        $this->runGit(['git', 'commit', '-q', '-m', 'add wallet package'], $this->hostRepo);
    }

    protected function tearDown(): void
    {
        VendorGit::$repoRootOverride = null;
        VendorGit::$processEnvOverride = null;
        $this->cleanupVendorGitTempDirs();

        parent::tearDown();
    }

    public function test_publishes_a_package_to_a_new_bare_repository(): void
    {
        $destination = $this->makeGitRepo(bare: true);

        $this->artisan("vendor:publish Wallet {$destination} --ref=main")->assertSuccessful();

        $result = Process::run(['git', "--git-dir={$destination}", 'show', 'main:composer.json'])->throw();
        $published = json_decode($result->output(), true);

        $this->assertSame('local/wallet', $published['name']);

        $recordedSource = VendorGit::readVendorSource("{$this->hostRepo}/app/Vendor/Wallet/composer.json");
        $this->assertSame($destination, $recordedSource['repo']);
        $this->assertSame('main', $recordedSource['ref']);
    }

    public function test_local_split_branch_is_removed_by_default(): void
    {
        $destination = $this->makeGitRepo(bare: true);

        $this->artisan("vendor:publish Wallet {$destination} --ref=main")->assertSuccessful();

        $result = $this->runGit(['git', 'branch', '--list', 'vendor-split/wallet'], $this->hostRepo);

        $this->assertSame('', trim($result->output()));
    }
}
