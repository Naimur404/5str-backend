#!/usr/bin/env bash
set -e

cd /var/www/html

echo "==> Preparing application..."

# Generate APP_KEY if it was not supplied via environment.
if [ -z "${APP_KEY}" ]; then
    echo "    APP_KEY not set — generating a temporary one (set it in Coolify!)."
    php artisan key:generate --force --no-interaction || true
fi

# Discover packages (composer ran with --no-scripts during build).
php artisan package:discover --ansi || true

# Publish Filament / vendor assets and link public storage.
php artisan filament:assets --no-interaction || true
php artisan storage:link --no-interaction || true

# Run database migrations.
echo "==> Running migrations..."
php artisan migrate --force --no-interaction

# Rebuild caches for production.
echo "==> Optimizing..."
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan icons:cache --no-interaction || true
php artisan filament:cache-components --no-interaction || true

# Ensure runtime directories are writable.
chown -R www-data:www-data storage bootstrap/cache || true

echo "==> Startup complete — handing off to supervisord."
exec "$@"
