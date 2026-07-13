# Architecture Guide

## Module Or Package

Use an HMVC module for application domain behavior that belongs to this
platform: orders, billing, catalog, support, reporting. Modules may depend on
Core and declared modules and are deployed with the application.

Use a local package for a host-agnostic capability that should work in several
projects: response envelopes, media attachments, permissions, or reference
data. Packages must not import `App\` classes. Promote a package to its own
repository when it needs an independent release cadence or compatibility
matrix.

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
