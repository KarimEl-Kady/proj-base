<?php

namespace App\Modules\Core\Tests\Feature;

use App\Modules\Core\Support\VendorGit;
use App\Modules\Core\Tests\Feature\Concerns\InteractsWithVendorGitFixtures;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * git (and git-subtree's own internal re-invocation of git fetch/push)
 * treats a leading "-" on a "repository"/"ref" argument as the start of an
 * option, not a literal value. A repo string of
 * "--upload-pack=<command>" genuinely runs <command> as a git-fetch option
 * — reproduced directly against the real git binary before this guard
 * existed: git-subtree's internal fetch call is not itself wrapped in a
 * "--" separator, so even wrapping the *outer* `git subtree add` call in
 * "--" does not stop it. VendorGit::assertSafeGitArgument() is the fix;
 * these tests pin it at both the unit level and through the real
 * vendor:install command, and prove the guard fires before any process
 * actually runs.
 */
class VendorGitArgumentInjectionTest extends TestCase
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

        File::delete('/tmp/vendor_git_injection_canary');

        parent::tearDown();
    }

    public function test_subtree_add_rejects_a_dash_prefixed_repo_argument(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must not start with "-"');

        VendorGit::subtreeAdd('app/Vendor/Evil', '--upload-pack=touch /tmp/vendor_git_injection_canary', 'main', 'test');
    }

    public function test_subtree_add_rejects_a_dash_prefixed_ref_argument(): void
    {
        $this->expectException(RuntimeException::class);

        VendorGit::subtreeAdd('app/Vendor/Evil', $this->makeGitRepo(), '--upload-pack=touch /tmp/vendor_git_injection_canary', 'test');
    }

    public function test_subtree_pull_rejects_a_dash_prefixed_repo_argument(): void
    {
        $this->expectException(RuntimeException::class);

        VendorGit::subtreePull('app/Vendor/Evil', '--upload-pack=touch /tmp/vendor_git_injection_canary', 'main', 'test');
    }

    public function test_push_rejects_a_dash_prefixed_repo_argument(): void
    {
        $this->expectException(RuntimeException::class);

        VendorGit::push('--upload-pack=touch /tmp/vendor_git_injection_canary', 'local-branch', 'main');
    }

    public function test_the_injected_command_never_actually_runs(): void
    {
        try {
            VendorGit::subtreeAdd('app/Vendor/Evil', '--upload-pack=touch /tmp/vendor_git_injection_canary', 'main', 'test');
        } catch (RuntimeException) {
            // Expected — the assertion below is the actual point of this test.
        }

        $this->assertFileDoesNotExist('/tmp/vendor_git_injection_canary');
    }

    public function test_vendor_install_end_to_end_rejects_a_malicious_source_argument(): void
    {
        // Array form, not a shell-style string: this delivers the raw value
        // to the command's "source" argument the way config/vendor_sources.php
        // does (see the next test) — bypassing Symfony Console's own
        // string-tokenizer, which would otherwise reject a leading "--" as
        // an unrecognized option before the command's own code ever runs.
        // That's a real safety net too, but not the one this test targets.
        $this->artisan('vendor:install', [
            'source' => '--upload-pack=touch /tmp/vendor_git_injection_canary',
            '--as' => 'Evil',
            '--ref' => 'main',
        ])->assertFailed();

        $this->assertFileDoesNotExist('/tmp/vendor_git_injection_canary');
        $this->assertDirectoryDoesNotExist("{$this->hostRepo}/app/Vendor/Evil");
    }

    /**
     * The realistic attack shape: not a developer typing a malicious
     * command by hand, but a config/vendor_sources.php entry — reviewable
     * in a PR, easy to mistake for a URL typo — that a later `vendor:install
     * <name>` call resolves and passes straight to git.
     */
    public function test_vendor_install_end_to_end_rejects_a_malicious_registry_entry(): void
    {
        config(['vendor_sources.evil' => [
            'repo' => '--upload-pack=touch /tmp/vendor_git_injection_canary',
            'ref' => 'main',
        ]]);

        $this->artisan('vendor:install', ['source' => 'evil'])->assertFailed();

        $this->assertFileDoesNotExist('/tmp/vendor_git_injection_canary');
        $this->assertDirectoryDoesNotExist("{$this->hostRepo}/app/Vendor/Evil");
    }
}
