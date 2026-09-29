#!/usr/bin/env bash
# ==============================================================================
# Tech-Tians CRM - Remote Server Release Provisioner
#
# Executed on the target server during CI/CD zero-downtime deployment.
# Arguments:
#   $1: DEPLOY_PATH (e.g. /var/www/crm)
#   $2: RELEASE_DIR (e.g. /var/www/crm/releases/20260928120000)
# ==============================================================================

set -euo pipefail

DEPLOY_PATH="${1:-/var/www/crm}"
RELEASE_DIR="${2:-}"

if [ -z "$RELEASE_DIR" ] || [ ! -d "$RELEASE_DIR" ]; then
    echo "ERROR: Valid RELEASE_DIR must be provided as argument 2." >&2
    exit 1
fi

RELEASE_TIMESTAMP="$(basename "$RELEASE_DIR")"

echo "=================================================="
echo " CRM Remote Release Provisioner"
echo " Deploy Path: $DEPLOY_PATH"
echo " Release:     $RELEASE_TIMESTAMP"
echo "=================================================="

# 1. Symlink shared .env
echo "[1/7] Symlinking shared .env..."
if [ ! -f "$DEPLOY_PATH/shared/.env" ]; then
    echo "ERROR: Shared .env not found at $DEPLOY_PATH/shared/.env" >&2
    exit 1
fi
ln -sfn "$DEPLOY_PATH/shared/.env" "$RELEASE_DIR/.env"

# 2. Symlink shared storage subdirectories
echo "[2/7] Symlinking shared storage directories..."
mkdir -p "$RELEASE_DIR/storage"
mkdir -p "$DEPLOY_PATH/shared/storage/uploads"
mkdir -p "$DEPLOY_PATH/shared/storage/logs"
mkdir -p "$DEPLOY_PATH/shared/storage/cache"

ln -sfn "$DEPLOY_PATH/shared/storage/uploads" "$RELEASE_DIR/storage/uploads"
ln -sfn "$DEPLOY_PATH/shared/storage/logs" "$RELEASE_DIR/storage/logs"
ln -sfn "$DEPLOY_PATH/shared/storage/cache" "$RELEASE_DIR/storage/cache"

# 3. Install production composer dependencies
echo "[3/7] Installing production composer dependencies..."
cd "$RELEASE_DIR"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 4. Run database migrations
echo "[4/7] Running database migrations..."
php database/migrate.php

# 5. Sync utilities to persistent location
echo "[5/7] Syncing rollback and backup utilities..."
mkdir -p "$DEPLOY_PATH/scripts"
cp "$RELEASE_DIR/scripts/rollback.sh" "$DEPLOY_PATH/scripts/rollback.sh"
chmod +x "$DEPLOY_PATH/scripts/rollback.sh"
if [ -f "$RELEASE_DIR/scripts/backup.sh" ]; then
    cp "$RELEASE_DIR/scripts/backup.sh" "$DEPLOY_PATH/scripts/backup.sh"
    chmod +x "$DEPLOY_PATH/scripts/backup.sh"
fi

# 6. Switch symlink atomically (zero downtime)
echo "[6/7] Atomically switching symlink: $DEPLOY_PATH/current -> $RELEASE_DIR..."
ln -sfn "$RELEASE_DIR" "$DEPLOY_PATH/current"

# 7. Prune old releases, keeping the 5 most recent
echo "[7/7] Pruning old releases (keeping last 5)..."
cd "$DEPLOY_PATH/releases"
ls -1dt 20* 2>/dev/null | tail -n +6 | xargs -r rm -rf || true

# 8. Reload PHP-FPM to invalidate OPcache bytecode cache
echo "Reloading PHP-FPM for OPcache reset..."
if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload php8.2-fpm || sudo systemctl restart php8.2-fpm || echo "Notice: systemctl reload php8.2-fpm returned non-zero (check sudoers)."
elif command -v service >/dev/null 2>&1; then
    sudo service php8.2-fpm reload || true
fi

echo "=================================================="
echo " Release $RELEASE_TIMESTAMP successfully activated!"
echo "=================================================="
