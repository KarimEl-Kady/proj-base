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
