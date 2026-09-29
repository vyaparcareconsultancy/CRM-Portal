# Production Monitoring, Alerting & Observability Guide

This guide covers real-time health monitoring, UptimeRobot configuration, SSL expiration tracking, disk threshold alerts, and database backup verification for the Tech-Tians CRM Portal.

---

## 1. System Observability Architecture

```
                    ┌────────────────────────┐
                    │  UptimeRobot / Health  │
                    │   Checks (Every 60s)   │
                    └───────────┬────────────┘
                                │ GET /health
                                ▼
┌────────────────────────────────────────────────────────┐
│                   CRM Web Application                  │
│                                                        │
│  ┌─────────────────┐ ┌───────────────┐ ┌────────────┐  │
│  │  Health Checks  │ │ Monolog Logs  │ │   Sentry   │  │
│  │  (DB, Disk, IO) │ │ (30-day rot)  │ │ (PHP + JS) │  │
│  └─────────────────┘ └───────────────┘ └────────────┘  │
└────────────────────────────────────────────────────────┘
```

1. **`GET /health` Endpoint**:
   - Authenticated externally without session cookies.
   - Evaluates:
     - MySQL database connectivity and latency (`SELECT 1`).
     - Storage directory writability (`storage/logs`, `storage/cache`, `storage/uploads`).
     - Free disk space and percentage used.
   - **Response Codes**:
     - `200 OK`: All systems operational (`status: "success"`).
     - `503 Service Unavailable`: Database down, storage unwritable, or disk critically exhausted (`status: "error"`).
   - **Response Header**: Includes unique `X-Request-Id`.

2. **Logging Channels (`storage/logs/`)**:
   - `app-{YYYY-MM-DD}.log`: Application runtime information and warnings.
   - `error-{YYYY-MM-DD}.log`: Uncaught exceptions and 500 error traces.
   - `security-{YYYY-MM-DD}.log`: Failed logins, account lockouts, CSRF rejections, rate limit violations, bot challenges.
   - `audit-{YYYY-MM-DD}.log`: Administrative user management, client creation/updates, DPDP Act anonymization.
   - **Retention**: Kept for 30 days with daily automated pruning. Every line includes `request_id`, client IP, user ID, and URL.
   - **Admin UI**: Accessible at `/admin/logs` for real-time inspection.

3. **Sentry Error Tracking**:
   - Active when `SENTRY_DSN` is configured in `.env`.
   - Automatic backend error reporting with stack traces.
   - Client-side browser SDK error tracking.
   - Automatic data scrubbing: passwords, auth tokens, and Indian PAN numbers (`[A-Z]{5}[0-9]{4}[A-Z]`) are redacted before transmission.

---

## 2. Setting Up UptimeRobot for `/health`

[UptimeRobot](https://uptimerobot.com/) is a free, high-reliability external monitoring service that performs HTTP pings, keyword verification, and SSL monitoring.

### Step 1: Create the Main HTTP Monitor
1. Log in to your UptimeRobot dashboard.
2. Click **+ Add New Monitor**.
3. Fill in the following settings:
   - **Monitor Type**: `HTTP(s)`
   - **Friendly Name**: `Tech-Tians CRM Portal`
   - **URL (or IP)**: `https://crm.yourcompany.com/health`
   - **Monitoring Interval**: `1 minute` (or `5 minutes`)
   - **Monitor Timeout**: `30 seconds`
4. Expand **Advanced Options**:
   - **HTTP Method**: `GET`
   - **Accepted HTTP Status Codes**: Select `200` only. (Any 500, 502, 503, or 504 will trigger an immediate incident).

### Step 2: Set Keyword Checking (Optional but Recommended)
To prevent "false positives" where a web server serves a cached maintenance page:
- **Monitor Type**: `Keyword`
- **Keyword to check**: `"status": "success"`
- **Alert when**: `Keyword Not Exists`

### Step 3: Configure Notification Channels ("Alert Contacts")
Under **Alert Contacts to Notify**:
- Email to DevOps/Sysadmin.
- Slack / Discord / Telegram Webhook integration.
- SMS / Phone call for P1 outages.

---

## 3. SSL Certificate Expiry Alerting

SSL expiration halts customer operations and breaks mobile/AJAX workflows.

### Configuring in UptimeRobot
1. In UptimeRobot, edit your CRM monitor.
2. Scroll to **SSL Certificate Monitoring**.
3. Enable **Alert when certificate expires within**:
   - Set to **30 days** (early warning).
   - Set second alert to **7 days** (escalation).

### Verifying Automated Renewal on Origin Server
For Let's Encrypt (Certbot), test the automatic renewal timer:
```bash
# Verify Certbot dry-run renewal
sudo certbot renew --dry-run

# Check systemd certbot timer status
sudo systemctl status certbot.timer
```

---

## 4. Disk Space Alerting (`disk > 80%`)

The `/health` endpoint monitors disk space automatically. However, an independent server-level cron provides defense in depth.

### Origin Server Disk Alert Script
Save the following script as `/usr/local/bin/crm-disk-check.sh`:

```bash
#!/usr/bin/env bash
# ==============================================================================
# Tech-Tians CRM — Disk Space Monitor
# Alerts when storage partition exceeds 80% capacity
# ==============================================================================
set -euo pipefail

THRESHOLD=80
ALERT_EMAIL="devops@yourcompany.com"
STORAGE_DIR="/var/www/crm/storage"

# Read usage percentage of partition hosting /storage
CURRENT_USAGE=$(df -h "$STORAGE_DIR" | awk 'NR==2 {print $5}' | tr -d '%')

if [ "$CURRENT_USAGE" -gt "$THRESHOLD" ]; then
    HOSTNAME=$(hostname)
    SUBJECT="[ALERT] High Disk Usage on ${HOSTNAME}: ${CURRENT_USAGE}%"
    BODY="WARNING: Disk usage on ${STORAGE_DIR} has reached ${CURRENT_USAGE}%, exceeding the ${THRESHOLD}% threshold.\n\nPlease inspect storage/logs, storage/uploads, and temporary cache."
    
    # Send alert via mailx or curl webhook
    echo -e "$BODY" | mail -s "$SUBJECT" "$ALERT_EMAIL"
fi
```

Make it executable and add to crontab:
```bash
sudo chmod +x /usr/local/bin/crm-disk-check.sh
# Run every hour
echo "0 * * * * root /usr/local/bin/crm-disk-check.sh" | sudo tee -a /etc/cron.d/crm_disk_check
```

---

## 5. Database Backup Failure Alerting

Nightly database backups must be verified to ensure recovery point objectives (RPO).

### Automated Backup Script with Failure Alerts
Save as `/usr/local/bin/crm-db-backup.sh`:

```bash
#!/usr/bin/env bash
# ==============================================================================
# Tech-Tians CRM — MySQL Automated Backup with Failure Alerting
# ==============================================================================
set -euo pipefail

BACKUP_DIR="/var/backups/crm"
DATE=$(date +%Y-%m-%d_%H%M%S)
BACKUP_FILE="${BACKUP_DIR}/crm_db_${DATE}.sql.gz"
ALERT_EMAIL="devops@yourcompany.com"
DB_NAME="crm_db"
DB_USER="crm_backup"
DB_PASS="YourSecureBackupPassword"

mkdir -p "$BACKUP_DIR"

# Execute single-transaction backup without locking writes
if mysqldump -u"${DB_USER}" -p"${DB_PASS}" --single-transaction --quick --routines "${DB_NAME}" | gzip > "$BACKUP_FILE"; then
    # Verify file is not empty (> 10 KB)
    FILE_SIZE=$(stat -c%s "$BACKUP_FILE" 2>/dev/null || stat -f%z "$BACKUP_FILE" 2>/dev/null || echo 0)
    if [ "$FILE_SIZE" -lt 10240 ]; then
        SUBJECT="[CRITICAL] CRM Database Backup Empty: ${BACKUP_FILE}"
        echo "Backup failed: Generated file size is under 10KB (${FILE_SIZE} bytes)." | mail -s "$SUBJECT" "$ALERT_EMAIL"
        exit 1
    fi

    # Prune backups older than 30 days
    find "$BACKUP_DIR" -name "crm_db_*.sql.gz" -mtime +30 -delete
else
    SUBJECT="[CRITICAL] CRM Database Backup Failed on $(hostname)"
    echo "mysqldump encountered an error while backing up ${DB_NAME} at ${DATE}." | mail -s "$SUBJECT" "$ALERT_EMAIL"
    exit 1
fi
```

Make it executable and schedule via cron:
```bash
sudo chmod 700 /usr/local/bin/crm-db-backup.sh
# Run daily at 02:00 AM
echo "0 2 * * * root /usr/local/bin/crm-db-backup.sh" | sudo tee -a /etc/cron.d/crm_backup
```

---

## 6. Sentry Configuration Checklist

To activate Sentry tracking:
1. In your `.env` file, configure your Sentry DSN:
   ```dotenv
   SENTRY_DSN=https://your-public-key@o123456.ingest.sentry.io/7891011
   ```
2. Verify that `APP_ENV` is set accurately (`production`, `staging`, `local`).
3. Sensitive fields (`password`, `token`, `pan_no`) are automatically sanitized before events are dispatched to Sentry.
4. If `SENTRY_DSN` is left blank, Sentry remains completely disabled with zero runtime performance cost.
