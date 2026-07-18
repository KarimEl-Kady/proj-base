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

The tenancy mode is part of the persistence design:

- `none` creates no tenant columns, but still creates the `tenants` table.
- `single` stamps every tenant-owned row with one implicit default tenant.
- `multi` resolves the tenant per request and scopes tenant-owned models.

Tenant-owned models use `HasTenantScope`; global reference data does not.
Strict mode fails closed when scoped models are used without an explicit
context. Tenant records are soft-deleted, and active-schema foreign keys
restrict hard deletion.

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

## Routing

Module API route files own resource-relative paths only. Core applies
`PROJECT_API_PREFIX`, `PROJECT_API_VERSION`, middleware, and path-tenancy
prefixes centrally. Health routes are identified by route name, so they remain
tenant-exempt when the API prefix changes. Disabling the API removes business
API routes but preserves liveness/readiness endpoints.

## Scaling Path

1. Scale PHP-FPM and queue workers horizontally; use shared DB, Redis, object
   storage, and centralized logs.
2. Add database indexes, read replicas, cache hot reads, and replace generic
   `%LIKE%` search in high-volume modules.
3. Split queues by workload and define latency/backlog SLOs.
4. Extract a module only when ownership, load, deployment cadence, or data
   isolation justifies the distributed-system cost. Durable messages form the
   extraction boundary.

The base intentionally does not bundle a search engine, broker, APM vendor, or
cloud-specific deployment. Those are selected from measured workload needs.
