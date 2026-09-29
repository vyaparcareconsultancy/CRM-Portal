#!/usr/bin/env bash
# ==============================================================================
# Tech-Tians CRM - Automated Backup Utility
#
# Creates timestamped, gzip-compressed backups of:
#   1. MySQL Database (via mysqldump)
#   2. Storage Uploads directory (storage/uploads)
#
# Features:
#   - Automated 14-day local retention pruning
#   - Optional cloud offload to AWS S3 / Cloudflare R2
#   - Automatic .env detection
#
# Usage:
#   ./scripts/backup.sh
#   Or via cron:
#   0 2 * * * /var/www/crm/scripts/backup.sh >> /var/www/crm/shared/storage/logs/backup.log 2>&1
# ==============================================================================

set -euo pipefail

# Determine application root directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# Resolve active .env path (checks shared/.env first, then root .env)
ENV_FILE=""
if [ -f "$APP_ROOT/../shared/.env" ]; then
    ENV_FILE="$APP_ROOT/../shared/.env"
elif [ -f "$APP_ROOT/shared/.env" ]; then
    ENV_FILE="$APP_ROOT/shared/.env"
elif [ -f "$APP_ROOT/.env" ]; then
    ENV_FILE="$APP_ROOT/.env"
fi

if [ -n "$ENV_FILE" ]; then
    # Safely export environment variables from .env
    set -a
    # shellcheck disable=SC1090
    source <(grep -E '^[A-Za-z0-9_]+=' "$ENV_FILE" | sed -e 's/\r$//')
    set +a
fi

# Fallback defaults
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-crm_db}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

# Backup storage location
BACKUP_DIR="${BACKUP_DIR:-$APP_ROOT/storage/backups}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"

# Resolve uploads directory (support both shared symlinks and standard layout)
UPLOADS_DIR=""
if [ -d "$APP_ROOT/../shared/storage/uploads" ]; then
    UPLOADS_DIR="$APP_ROOT/../shared/storage/uploads"
elif [ -d "$APP_ROOT/shared/storage/uploads" ]; then
    UPLOADS_DIR="$APP_ROOT/shared/storage/uploads"
elif [ -d "$APP_ROOT/storage/uploads" ]; then
    UPLOADS_DIR="$APP_ROOT/storage/uploads"
fi

mkdir -p "$BACKUP_DIR"

DB_BACKUP_FILE="$BACKUP_DIR/crm_db_${TIMESTAMP}.sql.gz"
UPLOADS_BACKUP_FILE="$BACKUP_DIR/crm_uploads_${TIMESTAMP}.tar.gz"

echo "=================================================="
echo " Starting CRM Backup: $TIMESTAMP"
echo " Destination: $BACKUP_DIR"
echo "=================================================="

# ------------------------------------------------------------------------------
# 1. MySQL Database Backup
# ------------------------------------------------------------------------------
echo "[1/4] Dumping MySQL database '$DB_NAME'..."

MYSQL_PWD_OPT=""
if [ -n "$DB_PASS" ]; then
    MYSQL_PWD_OPT="--password=$DB_PASS"
fi

mysqldump \
    --host="$DB_HOST" \
    --port="$DB_PORT" \
    --user="$DB_USER" \
    $MYSQL_PWD_OPT \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --hex-blob \
    --default-character-set=utf8mb4 \
    "$DB_NAME" | gzip -9 > "$DB_BACKUP_FILE"

DB_SIZE="$(du -h "$DB_BACKUP_FILE" | cut -f1)"
echo " Database backup created: $DB_BACKUP_FILE ($DB_SIZE)"

# ------------------------------------------------------------------------------
# 2. Uploads Directory Archive
# ------------------------------------------------------------------------------
echo "[2/4] Archiving uploaded client documents..."
if [ -n "$UPLOADS_DIR" ] && [ -d "$UPLOADS_DIR" ]; then
    tar -czf "$UPLOADS_BACKUP_FILE" -C "$(dirname "$UPLOADS_DIR")" "$(basename "$UPLOADS_DIR")"
    UPLOADS_SIZE="$(du -h "$UPLOADS_BACKUP_FILE" | cut -f1)"
    echo " Uploads backup created: $UPLOADS_BACKUP_FILE ($UPLOADS_SIZE)"
else
    echo " Notice: No uploads directory found at '$UPLOADS_DIR'. Skipping archive."
fi

# ------------------------------------------------------------------------------
# 3. Local Retention Pruning (Keep last 14 days)
# ------------------------------------------------------------------------------
echo "[3/4] Pruning local backups older than $RETENTION_DAYS days..."
PRUNED_COUNT=0
while IFS= read -r -d '' file; do
    rm -f "$file"
    PRUNED_COUNT=$((PRUNED_COUNT + 1))
done < <(find "$BACKUP_DIR" -type f \( -name "crm_db_*.sql.gz" -o -name "crm_uploads_*.tar.gz" \) -mtime +"$RETENTION_DAYS" -print0)

echo " Pruned $PRUNED_COUNT expired backup file(s)."

# ------------------------------------------------------------------------------
# 4. Optional Cloud Upload (AWS S3 or Cloudflare R2)
# ------------------------------------------------------------------------------
echo "[4/4] Checking cloud storage offload..."
if [ -n "${S3_BUCKET:-}" ]; then
    S3_PREFIX="${S3_PREFIX:-crm-backups}"
    S3_ENDPOINT_FLAG=""
    if [ -n "${S3_ENDPOINT_URL:-}" ]; then
        S3_ENDPOINT_FLAG="--endpoint-url=$S3_ENDPOINT_URL"
    fi

    if command -v aws >/dev/null 2>&1; then
        echo " Uploading to s3://$S3_BUCKET/$S3_PREFIX/..."
        aws s3 cp "$DB_BACKUP_FILE" "s3://$S3_BUCKET/$S3_PREFIX/$(basename "$DB_BACKUP_FILE")" $S3_ENDPOINT_FLAG
        if [ -f "$UPLOADS_BACKUP_FILE" ]; then
            aws s3 cp "$UPLOADS_BACKUP_FILE" "s3://$S3_BUCKET/$S3_PREFIX/$(basename "$UPLOADS_BACKUP_FILE")" $S3_ENDPOINT_FLAG
        fi
        echo " Cloud upload to S3/R2 completed successfully."
    elif command -v rclone >/dev/null 2>&1; then
        echo " Uploading via rclone..."
        rclone copy "$DB_BACKUP_FILE" "remote:$S3_BUCKET/$S3_PREFIX/"
        if [ -f "$UPLOADS_BACKUP_FILE" ]; then
            rclone copy "$UPLOADS_BACKUP_FILE" "remote:$S3_BUCKET/$S3_PREFIX/"
        fi
        echo " Cloud upload via rclone completed successfully."
    else
        echo " Warning: S3_BUCKET set but neither 'aws' CLI nor 'rclone' found in PATH. Skipping cloud sync."
    fi
else
    echo " Cloud backup skipped (S3_BUCKET not configured)."
fi

echo "=================================================="
echo " CRM Backup completed successfully at $(date +%H:%M:%S)!"
echo "=================================================="
