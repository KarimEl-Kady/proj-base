# Deployment

## Development Server

`deploy-dev.sh` updates one mutable checkout over SSH. It is intentionally a
simple development-server workflow and is not the production deployment
model.

## Production

Version tags (`v*`) build, smoke-test, then tag and publish that exact local
image to GitHub Container Registry through
`.github/workflows/release-image.yml`. GitLab tag pipelines publish the same
Dockerfile to the GitLab Container Registry.

Deploy the exact immutable tag or digest through the target orchestrator:

1. Run one migration job from the new image with `php artisan migrate --force`.
   If the release activates tenancy for existing data, run
   `tenant:backfill --force` and `tenant:check` in the same controlled job.
2. Roll out application replicas without enabling `AUTO_MIGRATE`.
3. Roll out queue workers and one scheduler using the same image digest.
4. Wait for `/api/health/ready` before shifting traffic.
5. Roll back application replicas to the previous digest when needed.

Use expand/contract database changes so both the old and new image can run
during a rolling deployment. Registry credentials, signing/attestation,
replica counts, autoscaling, disruption budgets, and ingress configuration
belong to the selected cloud or orchestrator rather than this application
base.
