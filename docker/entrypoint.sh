#!/usr/bin/env bash
# 5SRT Backend container entrypoint.
#   - generates an APP_KEY if missing
#   - waits briefly for the database to accept connections
#   - runs migrations
#   - rebuilds production caches
#   - syncs Meilisearch index settings (no-op if SCOUT_DRIVER=null)
#   - hands off to supervisord
set -euo pipefail

cd /var/www/html

log()  { printf '\033[36m[entrypoint]\033[0m %s\n' "$*"; }
warn() { printf '\033[33m[entrypoint]\033[0m %s\n' "$*"; }

# ---------------------------------------------------------------------
# 1. APP_KEY safeguard
# ---------------------------------------------------------------------
if [ -z "${APP_KEY:-}" ]; then
    warn "APP_KEY not set — generating an in-memory key. Set APP_KEY in Coolify so it survives restarts."
    php artisan key:generate --force --no-interaction || true
fi

# ---------------------------------------------------------------------
# 2. Package discovery (composer ran with --no-scripts during build)
# ---------------------------------------------------------------------
php artisan package:discover --ansi || true

# ---------------------------------------------------------------------
# 3. Wait for the database (max ~30s) before running migrations.
#    Skipped if DB_CONNECTION is sqlite or DB_HOST is empty.
# ---------------------------------------------------------------------
if [ "${DB_CONNECTION:-mysql}" != "sqlite" ] && [ -n "${DB_HOST:-}" ]; then
    log "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
    for i in $(seq 1 30); do
        if php -r "exit(@(new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306}', '${DB_USERNAME:-root}', getenv('DB_PASSWORD') ?: '')) ? 0 : 1);" 2>/dev/null; then
            log "Database is up after ${i}s."
            break
        fi
        sleep 1
        if [ "$i" -eq 30 ]; then
            warn "Database not reachable after 30s — continuing anyway; readyz will report degraded."
        fi
    done
fi

# ---------------------------------------------------------------------
# 4. Migrations + storage symlink
# ---------------------------------------------------------------------
log "Running migrations..."
php artisan migrate --force --no-interaction
php artisan storage:link --no-interaction || true

# ---------------------------------------------------------------------
# 5. Production caches
# ---------------------------------------------------------------------
log "Rebuilding production caches..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan event:cache || true
# Note: don't view:cache — there are no Blade views in API mode.

# ---------------------------------------------------------------------
# 6. Sync Meilisearch index settings if Scout is configured.
#    Safe no-op when SCOUT_DRIVER=null or empty.
# ---------------------------------------------------------------------
if [ -n "${SCOUT_DRIVER:-}" ] && [ "${SCOUT_DRIVER}" != "null" ] && [ "${SCOUT_DRIVER}" != "collection" ] && [ "${SCOUT_DRIVER}" != "database" ]; then
    log "Syncing Meilisearch index settings (driver=${SCOUT_DRIVER})..."
    php artisan scout:sync-index-settings --no-interaction || warn "scout:sync-index-settings failed; check Meilisearch availability."
fi

# ---------------------------------------------------------------------
# 7. Permissions safety net (volume mounts can reset ownership)
# ---------------------------------------------------------------------
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

log "Startup complete — handing off to supervisord."
exec "$@"
