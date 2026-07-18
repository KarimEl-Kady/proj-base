#!/bin/sh
set -e

# =============================================================================
# proj-base — Container Entrypoint
# Handles DB readiness, migrations, caching, and CMD delegation.
# =============================================================================

# ---------------------------------------------------------------------------
# Resolve database host based on Laravel's DB_CONNECTION
# ---------------------------------------------------------------------------
resolve_db_host() {
    case "${DB_CONNECTION:-mysql}" in
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
# Wait until the configured database accepts an authenticated PDO connection.
# A listening TCP port is not sufficient: MySQL exposes its port during parts
# of initialization before it can complete a client handshake.
# ---------------------------------------------------------------------------
wait_for_database() {
    local max_attempts="${1:-30}"
    local attempt=0

    if [ -z "$DB_WAIT_HOST" ]; then
        return 0
    fi

    echo "Waiting for Database at ${DB_WAIT_HOST}:${DB_WAIT_PORT}..."

    while [ $attempt -lt $max_attempts ]; do
        if php -r '
            $driver = getenv("DB_CONNECTION") ?: "mysql";
            $host = getenv("DB_HOST") ?: ($driver === "pgsql" ? "pgsql" : "mysql");
            $port = getenv("DB_PORT") ?: ($driver === "pgsql" ? "5432" : "3306");
            $database = getenv("DB_DATABASE") ?: "laravel";
            $username = getenv("DB_USERNAME") ?: "root";
            $password = getenv("DB_PASSWORD") ?: "";
            $socket = getenv("DB_SOCKET") ?: "";

            $dsn = $driver === "pgsql"
                ? "pgsql:host={$host};port={$port};dbname={$database}"
                : ($socket !== ""
                    ? "mysql:unix_socket={$socket};dbname={$database}"
                    : "mysql:host={$host};port={$port};dbname={$database}");

            new PDO($dsn, $username, $password);
        ' >/dev/null 2>&1; then
            echo "Database is ready."
            return 0
        fi

        attempt=$((attempt + 1))
        sleep 1
    done

    echo "Database at ${DB_WAIT_HOST}:${DB_WAIT_PORT} not available after ${max_attempts}s"
    return 1
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

resolve_db_host

# Wait for database
if [ -n "$DB_WAIT_HOST" ]; then
    wait_for_database
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
        php artisan migrate --force --no-interaction
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
