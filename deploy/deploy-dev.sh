#!/usr/bin/env bash
# =============================================================================
# Dev server deployment script
#
# Executed ON the dev server (piped over SSH by CI, or run manually:
#   ssh deploy@dev-server 'bash -s' < deploy/deploy-dev.sh
# ).
#
# CI passes DEPLOY_PATH and GIT_BRANCH as environment variables; everything
# below falls back to the defaults in the CONFIGURATION block — customize
# them for your server.
# =============================================================================
set -euo pipefail

# ─── CONFIGURATION (edit these defaults for your dev server) ─────────────────
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/proj-base}"   # project root on the server
GIT_BRANCH="${GIT_BRANCH:-develop}"                # branch to deploy
GIT_REMOTE="${GIT_REMOTE:-origin}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-true}"           # php artisan migrate --force
BUILD_ASSETS="${BUILD_ASSETS:-true}"               # npm ci + npm run build
RESTART_QUEUE="${RESTART_QUEUE:-true}"             # php artisan queue:restart
USE_MAINTENANCE_MODE="${USE_MAINTENANCE_MODE:-true}" # artisan down/up around deploy
# ─────────────────────────────────────────────────────────────────────────────

log() { printf '\n\033[1;36m▶ %s\033[0m\n' "$*"; }

log "Deploying branch [$GIT_BRANCH] to $DEPLOY_PATH"
cd "$DEPLOY_PATH"

if [ "$USE_MAINTENANCE_MODE" = "true" ]; then
    log "Entering maintenance mode"
    "$PHP_BIN" artisan down --retry=15 || true
fi

# Ensure we always come back up, even if a step below fails.
finish() {
    if [ "$USE_MAINTENANCE_MODE" = "true" ]; then
        log "Leaving maintenance mode"
        "$PHP_BIN" artisan up || true
    fi
}
trap finish EXIT

log "Updating code from $GIT_REMOTE/$GIT_BRANCH"
git fetch "$GIT_REMOTE" "$GIT_BRANCH"
git reset --hard "$GIT_REMOTE/$GIT_BRANCH"

log "Installing composer dependencies"
"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

if [ "$BUILD_ASSETS" = "true" ] && [ -f package.json ]; then
    log "Building frontend assets"
    "$NPM_BIN" ci --ignore-scripts
    "$NPM_BIN" run build
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
    log "Running migrations"
    "$PHP_BIN" artisan migrate --force
fi

log "Refreshing caches"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache
# route:cache is skipped on purpose: closure-based routes (routes/web.php)
# are not cacheable. Enable it once all routes are controller-based:
# "$PHP_BIN" artisan route:cache

if [ "$RESTART_QUEUE" = "true" ]; then
    log "Restarting queue workers"
    "$PHP_BIN" artisan queue:restart || true
fi

log "Deployment finished successfully"
