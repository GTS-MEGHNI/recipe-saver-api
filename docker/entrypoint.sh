#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# Container start-up tasks for the Recipes API.
# Runs on every boot before handing control to FrankenPHP.
# ---------------------------------------------------------------------------

# SQLite database lives on a persistent volume (see docker-compose.yml).
# DB_DATABASE points at that volume so data survives image rebuilds.
DB_FILE="${DB_DATABASE:-/data/database.sqlite}"
DB_DIR="$(dirname "$DB_FILE")"

if [ ! -f "$DB_FILE" ]; then
    echo "Creating SQLite database at ${DB_FILE}"
    mkdir -p "$DB_DIR"
    touch "$DB_FILE"
fi

# The storage volume mounts over /app/storage, so guarantee the framework
# directories exist before Laravel (file cache/sessions, logs) touches them.
mkdir -p \
    /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/app/public \
    /app/storage/logs

# Ensure the runtime user owns the writable paths (volumes mount as root).
chown -R www-data:www-data "$DB_DIR" /app/storage /app/bootstrap/cache 2>/dev/null || true

# Public symlink for uploaded images (storage/app/public -> public/storage).
php artisan storage:link --force || true

# Apply database migrations.
php artisan migrate --force --no-interaction

# Cache config, routes and events for production performance.
# (view:cache is skipped — this is an API with no Blade views.)
php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
