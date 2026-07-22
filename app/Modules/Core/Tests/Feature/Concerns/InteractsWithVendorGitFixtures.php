<?php

namespace App\Modules\Core\Tests\Feature\Concerns;

use App\Modules\Core\Support\VendorGit;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Scaffolds throwaway git repositories under storage/framework/testing so
 * vendor:install/update/remove/publish can be exercised with real git
 * subtree commands without ever touching this project's own git history.
 * VendorGit::$repoRootOverride is what redirects the commands under test.
 */
trait InteractsWithVendorGitFixtures
{
    /** @var array<int, string> */
    protected array $vendorGitTempDirs = [];

    /**
     * GIT_AUTHOR_ / GIT_COMMITTER_ env vars used for every git command this
     * trait runs, so no .git/config (local or global) is ever written to
     * give these throwaway repos a commit identity.
     *
     * @return array<string, string>
     */
    protected function vendorGitTestEnv(): array
    {
        return [
            'GIT_AUTHOR_NAME' => 'Vendor Test',
            'GIT_AUTHOR_EMAIL' => 'vendor-test@example.com',
            'GIT_COMMITTER_NAME' => 'Vendor Test',
            'GIT_COMMITTER_EMAIL' => 'vendor-test@example.com',
        ];
    }

    /**
     * @param  array<int, string>  $argv
     */
    protected function runGit(array $argv, string $cwd): ProcessResult
    {
        return Process::path($cwd)->env($this->vendorGitTestEnv())->run($argv)->throw();
    }

    protected function makeGitRepo(bool $bare = false): string
    {
        $path = storage_path('framework/testing/vendor-git-'.uniqid());
        File::ensureDirectoryExists($path);
        $this->vendorGitTempDirs[] = $path;

        $init = $bare ? ['git', 'init', '-q', '--bare'] : ['git', 'init', '-q', '-b', 'main'];
        $this->runGit($init, $path);

        return $path;
    }

    /**
     * A "host project" repo: has a root composer.json (sort-packages) and
     * app/Vendor directory, ready to be VendorGit::$repoRootOverride target.
     */
    protected function makeHostRepo(): string
    {
        $path = $this->makeGitRepo();

        File::ensureDirectoryExists("{$path}/app/Vendor");
        VendorGit::writeJson("{$path}/composer.json", [
            'name' => 'test/host',
            'require' => [],
            'config' => ['sort-packages' => true],
        ]);
        File::put("{$path}/.gitkeep", '');

        $this->runGit(['git', 'add', '-A'], $path);
        $this->runGit(['git', 'commit', '-q', '-m', 'init'], $path);

        return $path;
    }

    /**
     * An "upstream package" repo shaped like a local/{name} package:
     * composer.json + src/, no runtime deps, so nothing needs network.
     */
    protected function makePackageSourceRepo(string $composerName, string $studly): string
    {
        $path = $this->makeGitRepo();

        File::ensureDirectoryExists("{$path}/src");
        VendorGit::writeJson("{$path}/composer.json", [
            'name' => $composerName,
            'version' => '1.0.0',
            'require' => ['php' => '^8.3'],
            'autoload' => ['psr-4' => ["Local\\{$studly}\\" => 'src/']],
        ]);
        File::put("{$path}/src/{$studly}ServiceProvider.php", "<?php\n\nnamespace Local\\{$studly};\n\nclass {$studly}ServiceProvider\n{\n}\n");

        $this->runGit(['git', 'add', '-A'], $path);
        $this->runGit(['git', 'commit', '-q', '-m', 'initial release'], $path);

        return $path;
    }

    protected function cleanupVendorGitTempDirs(): void
    {
        foreach ($this->vendorGitTempDirs as $dir) {
            File::deleteDirectory($dir);
        }

        $this->vendorGitTempDirs = [];
    }
}
