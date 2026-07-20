# Kubernetes reference manifests

What `deploy/deploy-dev.sh` is for a single dev host, this directory is for
a real cluster: **a pattern to adapt, not a finished deployment.** It exists
because the alternative — every team that adopts this base independently
inventing horizontal scaling, rollout, and migration-ordering from
scratch — is a worse starting point than an opinionated, honest example.

It consumes the exact image `ci.yml`'s `container` job already builds and
pushes to GHCR/GitLab Container Registry. Nothing here is Docker-Compose's
dev posture repackaged: `docker-compose.yml` bind-mounts the working tree
and pins fixed container names (single-host, by construction); everything
below runs the immutable built image and is written to support N replicas.

## What's here

| File | Kind | Purpose |
|---|---|---|
| `deployment.yaml` | Deployment | The web app: an initContainer copies `public/` from the app image into a shared `emptyDir`, then two containers share the pod — `app` (php-fpm, the built image, port 9000) and `nginx` (stock `nginx:1.27-alpine`, reverse-proxies to `app` over `127.0.0.1:9000`, serves static assets from the shared volume). Same shape as `docker-compose.yml`'s `app`+`nginx` pair, minus the bind mount. |
| `configmap-nginx.yaml` | ConfigMap | `docker/nginx/default.conf`, adapted for the sidecar (`fastcgi_pass 127.0.0.1:9000` instead of `app:9000`; the Vite HMR block is dropped — that's dev-only). |
| `service.yaml` | Service | ClusterIP :80 → the nginx container. Put your cluster's Ingress (TLS termination, hostnames) in front of this — deliberately out of scope here, since it's the most cluster/provider-specific part. |
| `hpa.yaml` | HorizontalPodAutoscaler | CPU-based, 2–10 replicas. A starting point, not a tuned value — watch real load before trusting these numbers. |
| `queue-deployment.yaml` | Deployments | Independent default, bulk, and notification workers. Separate processes/replicas prevent one lane from consuming another lane's capacity. |
| `outbox-deployment.yaml` | Deployment | Two continuously polling transactional-outbox publishers; row claims make concurrent publishers safe. The minute scheduler remains as a recovery fallback. |
| `scheduler-cronjob.yaml` | CronJob | `php artisan schedule:run`, every minute — replaces `docker-compose.yml`'s always-running `scheduler` service with a real cron primitive instead of a sleep loop. |
| `migrate-job.yaml` | Job | `php artisan migrate --force`. Run this — `kubectl apply -f migrate-job.yaml && kubectl wait --for=condition=complete job/proj-base-migrate` — before rolling `deployment.yaml` to a new image tag. If you templatize this with Helm/Kustomize later, this is what becomes a pre-upgrade hook. |

## What you still have to decide

This is deliberately not turnkey — these are the org-specific choices no
reference manifest can make for you:

- **Secrets.** Every manifest reads `envFrom: secretRef: name: proj-base-env`
  and nothing here creates that Secret. Populate it with everything
  `.env.example` documents (`APP_KEY`, `DB_*`, `REDIS_*`, `PROJECT_*`) via
  your cluster's normal secret-management path — `kubectl create secret`,
  External Secrets Operator, Sealed Secrets, whatever your org already uses.
- **Image tag.** Every manifest has `image: ghcr.io/ORG/proj-base:TAG` —
  replace `ORG` and wire `TAG` to your release process (the `container` CI
  job already tags images on version tags).
- **Ingress / TLS.** Not included — terminate TLS and route hostnames the
  way your cluster already does it, in front of `service.yaml`.
- **Database / Redis.** Not included — this assumes managed MySQL/PostgreSQL
  and Redis (RDS, Cloud SQL, ElastiCache, or your cluster's own operators),
  matching `docker-compose.yml`'s `DB_CONNECTION`/`REDIS_*` variables.
- **Resource requests/limits and replica counts.** Set to conservative
  placeholders — real values depend on real traffic, which this reference
  can't know in advance.

## Why a sidecar instead of baking nginx into the app image

The Dockerfile deliberately builds a php-fpm-only image (`EXPOSE 9000`, no
web server) — the same image works behind Compose's separate `nginx`
service, an ALB/Ingress with a different proxy, or this sidecar, without
rebuilding it three ways. The sidecar pattern keeps that: nginx stays a
stock, unmodified image, and the only project-specific piece
(`docker/nginx/default.conf`) travels as a ConfigMap instead of being
baked into a bespoke image that would need its own build/tag/release
lifecycle alongside the app image.
