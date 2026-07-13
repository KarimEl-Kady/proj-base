# Operations Runbook

## Release

1. Build an immutable artifact/image from a reviewed commit.
2. Run `composer check` and the frontend build.
3. Back up the database before destructive or irreversible migrations.
4. Run `php artisan project:validate` and migrations as a single release job.
5. Warm config, route, view, and event caches.
6. Restart queue workers and verify `/api/health/ready`.

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

## Backup And Restore

- Back up the primary database and object-storage media independently.
- Encrypt backups, restrict access, and define retention by environment.
- Record recovery point and recovery time objectives.
- Test restore into an isolated environment on a schedule; an untested backup
  is not a recovery plan.
- After restore, run migrations, `project:validate`, and readiness checks before
  serving traffic.

## Health

- `/api/health/live`: process liveness only.
- `/api/health/ready`: database, cache, Redis, queue backlog, and optional
  worker heartbeat.
- Set `PROJECT_HEALTH_REQUIRE_QUEUE_WORKER=true` where async processing is a
  required dependency.

## Queue Incidents

Inspect `queue:failed`, worker heartbeat, backlog, and logs by `request_id` or
`event_id`. Retry only after fixing the cause. Outbox failures back off and
become dead-lettered after the configured attempt limit. Inspect `last_error`,
then run `outbox:retry {event_id}` (or deliberately `--all`). Consumers must
use Inbox deduplication; external APIs must receive the event ID as their
idempotency key. `messages:prune` applies the configured retention windows.

## Observability

Production should ship `stderr_json` logs to a centralized system and attach
an error/APM/OpenTelemetry provider appropriate to the environment. Preserve
`X-Request-ID` at the edge and include it in support and incident workflows.
