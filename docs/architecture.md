# Architecture Guide

## Module Or Package

Use an HMVC module for application domain behavior that belongs to this
platform: orders, billing, catalog, support, reporting. Modules may depend on
Core and declared modules and are deployed with the application.

Use a local package for a host-agnostic capability that should work in several
projects: response envelopes, media attachments, permissions, or reference
data. Packages must not import `App\` classes. Promote a package to its own
repository when it needs an independent release cadence or compatibility
matrix. `php artisan module:boundaries` enforces both module declarations and
package independence.

**Worked example — when to merge instead of declaring a cycle.** Country and
City used to be separate modules: `City belongsTo Country` and
`Country hasMany City`, a genuinely bidirectional relationship. Making that
legal under `module:boundaries` required declaring it both ways in
`boundaries.allow` *and* listing the pair in `boundaries.allow_cycles` — and
neither module could actually be enabled, disabled, or extracted without the
other, so the "two modules" framing was never true. They were merged into one
`Geo` module instead, which removed both boundary entries entirely. `Auth -> User` (declared in `boundaries.allow`) is the ordinary,
one-directional case: Auth depends on User, User has no reverse dependency,
no cycle exists. Reach for `allow_cycles` only when the dependency actually
runs both ways *and* the two modules still have independent reasons to
change. Reach for a merge, as with Geo, when it's bidirectional and neither
side is independently deployable — that isn't two modules, it's one module
that got split too early.

## Request Flow

`Route -> Middleware -> Request -> Controller -> Service -> Repository -> Model`

Controllers translate transport concerns. Services own use cases and
transactions. Repositories compose persistence queries. Models own persistence
behavior and relations. Resources own public serialization.

## Dependency Rules

- Core cannot depend on business modules.
- A module may depend on Core and dependencies declared in
  `project.boundaries.allow`.
- Enabled modules must have enabled dependencies and a service provider.
- Cycles fail validation unless their full module set is explicitly listed in
  `project.boundaries.allow_cycles`.
- Run `php artisan module:boundaries` before committing architecture changes.

## Messaging

Use `DomainEvent` for normal after-commit reactions where application-level
reconciliation is sufficient. Use jobs for directed work with one owner.

Use `Outbox::record()` inside the domain transaction for critical messages.
The scheduled publisher emits `DurableMessage`; consumers use
`Inbox::consume()` for tenant restoration and idempotency. Delivery is
at-least-once, so external side effects must also use idempotency keys. Inbox
claims and consumer database writes commit atomically. Publisher claims expire
after a crash; failures use bounded backoff and move to a dead-letter state.

## Tenancy

The tenancy mode governs *behavior*, not schema — the schema is identical in
every mode:

- Every tenant-owned table gets a nullable, indexed `tenant_id` column (via
  the `tenantColumn()` migration macro) and the `tenants` table always
  exists, regardless of mode. Switching modes is therefore a config +
  backfill change, never a schema migration: no environment can end up with
  a different shape depending on which mode it happened to be migrated
  under, and there is no catch-up-migration bookkeeping between modes.
- `none`: no code path stamps or scopes on `tenant_id`; it stays null on
  every row.
- `single`: every request runs under one implicit default tenant, so rows
  are stamped and scoped from day one.
- `multi`: the tenant is resolved per request and scoped models are
  filtered/stamped to it.

Tenant-owned models use `HasTenantScope`; global reference data does not.
Strict mode fails closed when scoped models are used without an explicit
context. Tenant records are soft-deleted, and active-schema foreign keys
restrict hard deletion.

Composite unique indexes (`[tenant_id, column]`) are unconditional too, for
the same reason. One caveat: SQL unique indexes treat every `NULL` as
distinct, so in "none" mode — where `tenant_id` is null on every row — such
an index alone would not stop two rows from sharing a value. Application-
level uniqueness rules (e.g. `UserRules::uniqueEmail()`) run an unscoped,
NULL-safe check whenever `has_tenancy()` is false and are the real backstop
in that mode; only a direct DB write that bypasses validation could exploit
the gap.

Email is tenant-relative identity, while user UUID is globally unique.
Authentication lookups run inside tenant context, and password-reset tokens
are stored and verified by user UUID so credentials cannot cross tenant
boundaries when two tenants use the same email address.

Changing an existing database from `none` to an active mode requires
`tenant:migrations`, `migrate`, `tenant:backfill`, then `tenant:check`.
`tenantUniqueColumns()` declares global indexes that must become composite
tenant indexes during that transition. Once tenants contain duplicate values
for a formerly global unique key, downgrading to `none` is a deliberate data
consolidation project, not an automatic rollback.

### Testing Under Tenancy

The suite runs under whichever `project.tenancy.mode` the environment
defaults to (`none`, from `.env.example`), so tests that care about `single`
or `multi` behavior establish it themselves rather than depending on a
separate CI run per mode:

- Within one test, override `config(['project.tenancy.mode' => ...])`
  directly — see `TenancyModesTest`. This is enough for anything resolved at
  request time (middleware, scoping, strict-mode).
- For anything baked in at boot (route registration/prefixing — path
  identification adds a `{tenant}/` segment to every module route), a
  `config()` override is too late. Use `#[RunTestsInSeparateProcesses]` with
  `putenv('PROJECT_TENANCY_MODE=multi')` in `setUpBeforeClass()` instead —
  see `MultiTenantIdentityTest` and `UserTenantIsolationTest`. CI also boots
  the app directly under `single`/`multi`/path-identified `multi` as a cheap
  smoke check independent of the test suite (see the CI workflow's "Boot
  smoke check under active tenancy modes" step) specifically to catch this
  class of bug.
- `Tests\TestCase::actingAsUser()` and `withTestTenant()` create their
  fixtures under a tenant automatically whenever tenancy is active and none
  is already in `Context`, mirroring what `TenantMiddleware` does for a real
  request — use them (or wrap direct `Model::factory()->create()` calls in
  `withTestTenant()` yourself) so a tenant-scoped-model test doesn't depend
  on `none` being the active mode to pass.

Known gap: the `local/permission` and `local/media` packages' own test
suites, and a few Auth module tests, still create `User`/fixture models
directly and assume `none` mode — they only fail if the project actually
runs those suites under `single`/`multi`, which nothing in CI does today.
Route the failure through `withTestTenant()` the same way when touching one
of those files, rather than reintroducing an env-specific workaround.

## Routing

Module API route files own resource-relative paths only. Core applies
`PROJECT_API_PREFIX`, `PROJECT_API_VERSION`, middleware, and path-tenancy
prefixes centrally. Health routes are identified by route name, so they remain
tenant-exempt when the API prefix changes. Disabling the API removes business
API routes but preserves liveness/readiness endpoints.

## Scaling Path

1. Scale PHP-FPM and queue workers horizontally; use shared DB, Redis, object
   storage, and centralized logs. `deploy/k8s/` is a reference manifest set
   for this step — a pattern built on the image CI already produces, not a
   mandated target; see `deploy/k8s/README.md`.
2. Add database indexes, read replicas, cache hot reads, and replace generic
   `%LIKE%` search in high-volume modules. `BaseRepository::searchStrategy()`
   is the seam for the last part — see `App\Modules\Core\Repositories\Search`
   (`LikeSearch` default, `FullTextSearch` opt-in) rather than editing
   `BaseRepository::fetch()` per module.
3. Split queues by workload and define latency/backlog SLOs.
   `QueuedListener::$lane` (`config('project.events.lanes')`) is the seam —
   every lane resolves to the same queue until one is actually named.
4. Extract a module only when ownership, load, deployment cadence, or data
   isolation justifies the distributed-system cost. Durable messages form the
   extraction boundary.

The base intentionally does not bundle a search engine, broker, or APM
vendor — those are selected from measured workload needs, and the seams
above exist so adding one later is additive, not a refactor. A generic,
cloud-agnostic Kubernetes reference *is* included (`deploy/k8s/`) since
"how do you actually run more than one instance of this" isn't a
measured-workload question the way a search engine choice is — every team
that adopts this base needs an answer to it eventually, and reinventing it
independently each time is a worse default than one honest example.
