# Local Packages

Packages in this directory are Composer path packages shared across projects.
They must not import `App\` classes or depend on the host project's modules.

## Contract

- Use semantic versions and install with a bounded constraint such as `^1.0`.
- Keep host integration behind configuration, contracts, and service providers.
- Own package migrations only when the package inherently owns the data.
- Add tests under `tests/`, a package README, and a changelog.
- Run package tests through the root `Packages` suite before release.
- When a package is promoted beyond this repository, give it an independent
  repository and Orchestra Testbench matrix for every supported Laravel/PHP pair.

The root CI tests these packages against PHP 8.3 and 8.4 and Laravel's locked
version. Independent repositories should broaden that matrix before claiming a
wider compatibility range.

## Installing from another repository

Packages developed in their own repository (e.g. `Wallet`, `Blog` — global
features meant to be reused across platforms built from this base) are
pulled in and pushed out with `git subtree`, wrapped in artisan commands so
the workflow stays a one-liner:

- `php artisan vendor:install <repo-or-name> [--as=Name] [--ref=main]` — pulls
  the package into `app/Vendor/{Name}`, adds it to the root `composer.json`
  `require`, and records `{repo, ref}` in the package's own
  `extra.vendor-source` so it can be updated later. `<repo-or-name>` can be a
  short name registered in `config/vendor_sources.php`, or a literal git
  URL/path.
- `php artisan vendor:update <Name> [--ref=]` — `git subtree pull`s upstream
  changes into the already-customized copy. Conflicts are left as normal git
  conflict markers to resolve by hand, same as any merge.
- `php artisan vendor:publish <Name> <repo> [--ref=main]` — the reverse:
  `git subtree split`s an in-repo package and pushes it to a new/independent
  repository, so other platforms can `vendor:install` it. Also backfills
  `extra.vendor-source` on the local copy.
- `php artisan vendor:remove <Name> [--keep-files] [--force]` — drops the
  composer require and (unless `--keep-files`) the directory.
- `php artisan package:list` shows each package's Source/Ref alongside the
  existing Package/Version/Namespace/Status columns — `local` means it was
  scaffolded in-repo via `make:package` and never pulled from anywhere.

All four commands refuse to run against a dirty working tree, and
`vendor:install` rolls back automatically if the pulled repository doesn't
look like a valid package (no `composer.json`).
