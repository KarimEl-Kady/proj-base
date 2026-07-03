#!/bin/sh
set -e

# =============================================================================
# proj-base — Container Entrypoint
# Handles DB readiness, migrations, caching, and CMD delegation.
# =============================================================================

# ---------------------------------------------------------------------------
# Resolve database host based on PROJECT_DB_DRIVER
# ---------------------------------------------------------------------------
resolve_db_host() {
    case "${PROJECT_DB_DRIVER:-mysql}" in
        mysql|mariadb)
            DB_WAIT_HOST="${DB_HOST:-mysql}"
            DB_WAIT_PORT="${DB_PORT:-3306}"
            ;;
        pgsql)
            DB_WAIT_HOST="${DB_HOST:-pgsql}"
            DB_WAIT_PORT="${DB_PORT:-5432}"
            ;;
        sqlite)
            # No network dependency
            DB_WAIT_HOST=""
            ;;
        *)
            DB_WAIT_HOST="${DB_HOST:-mysql}"
            DB_WAIT_PORT="${DB_PORT:-3306}"
            ;;
    esac
}

# ---------------------------------------------------------------------------
# Wait for a TCP service to become available
# ---------------------------------------------------------------------------
wait_for_service() {
    local host="$1"
    local port="$2"
    local service_name="$3"
    local max_attempts="${4:-30}"
    local attempt=0

    if [ -z "$host" ]; then
        return 0
    fi

    echo "⏳ Waiting for ${service_name} at ${host}:${port}..."

    while [ $attempt -lt $max_attempts ]; do
        if nc -z "$host" "$port" 2>/dev/null; then
            echo "✅ ${service_name} is ready."
            return 0
        fi
        attempt=$((attempt + 1))
        sleep 1
    done

    echo "❌ ${service_name} at ${host}:${port} not available after ${max_attempts}s"
    return 1
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

# Install netcat for TCP checks (if not present)
command -v nc >/dev/null 2>&1 || apk add --no-cache netcat-openbsd >/dev/null 2>&1 || true

resolve_db_host

# Wait for database
if [ -n "$DB_WAIT_HOST" ]; then
    wait_for_service "$DB_WAIT_HOST" "$DB_WAIT_PORT" "Database"
fi

# Wait for Redis (if configured)
if [ -n "$REDIS_HOST" ]; then
    wait_for_service "$REDIS_HOST" "${REDIS_PORT:-6379}" "Redis"
fi

# ---------------------------------------------------------------------------
# First-run tasks (only for the primary app process, not queue/scheduler)
# ---------------------------------------------------------------------------
if [ "$1" = "php-fpm" ]; then

    # Auto-migrate
    if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
        echo "🔄 Running migrations..."
        php artisan migrate --force --no-interaction 2>&1 || true
    fi

    # Cache configuration & routes in production
    if [ "${APP_ENV:-local}" = "production" ]; then
        echo "📦 Caching configuration and routes..."
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
    fi

    echo "🚀 Starting PHP-FPM..."
fi

# ---------------------------------------------------------------------------
# Execute CMD
# ---------------------------------------------------------------------------
exec "$@"
