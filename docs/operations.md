# Operations Runbook

## Release

1. Build an immutable artifact/image from a reviewed commit.
2. Run `composer check` and the frontend build.
3. Back up the database before destructive or irreversible migrations.
4. Run `php artisan project:validate` and migrations as a single release job.
5. When enabling tenancy on existing data, run `tenant:backfill` and require
   `tenant:check` to pass before application traffic resumes.
6. Warm config, route, view, and event caches.
7. Restart all three queue lanes and the outbox publishers, then verify
   `/api/health/ready`.

Migration `2026_07_18_000001` rebuilds the ephemeral password-reset token
table around user UUIDs. Existing email-only reset links are deliberately
invalidated and users must request a new link after that deployment.

See [`deploy/README.md`](../deploy/README.md) for immutable image publishing
and rollout. `deploy-dev.sh` is a mutable development-server convenience, not
the production release mechanism.

Do not run migrations independently in every application replica. Docker
`AUTO_MIGRATE` is off by default; when explicitly enabled, migration failure
stops the container.

## Rollback

Application rollback means deploying the previous immutable artifact. Database
rollback is not automatic: prefer backward-compatible expand/contract
migrations. Restore from backup when an irreversible migration corrupts data.
Do not downgrade active tenancy after per-tenant unique values have diverged
without first reconciling those values.

## Backup And Restore

- Back up the primary database and object-storage media independently.
- Encrypt backups, restrict access, and define retention by environment.
- Record recovery point and recovery time objectives.
- Test restore into an isolated environment on a schedule; an untested backup
  is not a recovery plan.
- After restore, run migrations, `project:validate`, and readiness checks before
  serving traffic.
- Record the latest successful restore date, restored snapshot identifier,
  measured RPO/RTO, row-count checks, and media-object sampling in the release
  system. This evidence is environment-owned and cannot be manufactured by the
  application repository.

## Health

- `/api/health/live`: process liveness only.
- `/api/health/ready`: database, cache, Redis, queue backlog, and optional
  worker heartbeat.
- Set `PROJECT_HEALTH_REQUIRE_QUEUE_WORKER=true` where async processing is a
  required dependency.
- Dependency drivers, latency, queue sizes, and heartbeat timestamps are hidden
  unless `PROJECT_HEALTH_EXPOSE_DETAILS=true`; expose detailed readiness only
  on a protected internal route/network.

## Queue Incidents

Inspect `queue:failed`, per-lane worker heartbeats, backlog, and logs by
`request_id`, `tenant_id`, or `event_id`. Retry only after fixing the cause.
Outbox failures back off and
become dead-lettered after the configured attempt limit. Inspect `last_error`,
then run `outbox:retry {event_id}` (or deliberately `--all`). Consumers must
use Inbox deduplication; external APIs must receive the event ID as their
idempotency key. `messages:prune` applies the configured retention windows.

## Observability

Production should ship `stderr_json` logs to a centralized system and attach
an error/APM/OpenTelemetry provider appropriate to the environment. Preserve
`X-Request-ID` at the edge and include it in support and incident workflows.

## Capacity And Regional Resilience

- Run load tests against the deployed environment, not SQLite or the PHP dev
  server. Capture p50/p95/p99 latency, error rate, DB saturation, cache hit
  rate, and backlog/oldest-message age for each queue lane and the outbox.
- Scale web, default, bulk, notification, and outbox deployments independently.
- Multi-region failover requires provider-specific replicated database, Redis,
  object storage, DNS/edge routing, and a tested promotion procedure. The
  manifests are region-neutral building blocks, not proof that failover works.
- Exercise regional failover and backup restore on a schedule and retain the
  evidence beside the configured SLO/RPO/RTO.
