# Disaster Recovery & Restoration Guide

This document describes the exact step-by-step recovery process to restore the **Tech-Tians CRM Portal** from a backup created by `scripts/backup.sh` or an offsite snapshot.

---

## 1. Prerequisites & Emergency Checklist

Before beginning restoration, ensure you have:
1. Target server shell access (SSH as `deploy` or `root`).
2. MySQL root or database user credentials (`DB_USER`, `DB_PASS`, `DB_NAME`).
3. The backup archives:
   - Database dump: `crm_db_YYYYMMDD_HHMMSS.sql.gz`
   - Uploaded files: `crm_uploads_YYYYMMDD_HHMMSS.tar.gz`
4. The production `.env` file containing the critical `PAN_ENCRYPTION_KEY`.

> [!CAUTION]
> **Data Loss Warning**: Restoring the database will overwrite existing tables and data. If recovering from a partial outage, create an immediate snapshot of the current database before restoring:
> ```bash
> mysqldump -u root -p crm_db | gzip -9 > /tmp/crm_db_pre_restore_$(date +%Y%m%d%H%M%S).sql.gz
> ```

---

## 2. Restoring the MySQL Database

### Step 2.1: Locate or Download the Backup Archive
If backups are stored in S3/R2, retrieve the latest backup:
```bash
# Example from AWS S3:
aws s3 cp s3://my-crm-backups/crm-backups/crm_db_20260928_020000.sql.gz /tmp/

# Or from local backup directory:
cp /var/www/crm/storage/backups/crm_db_20260928_020000.sql.gz /tmp/
```

### Step 2.2: Recreate or Clean the Target Database
For a clean state, recreate the database with utf8mb4 collation:
```bash
mysql -u root -p -e "
DROP DATABASE IF EXISTS crm_db;
CREATE DATABASE crm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"
```

### Step 2.3: Import the Gzipped Database Dump
Decompress and pipe the dump directly into MySQL:
```bash
# Decompress and import directly into MySQL without writing raw SQL to disk:
gunzip -c /tmp/crm_db_20260928_020000.sql.gz | mysql -u crm_user -p crm_db
```

If using a root password via MySQL command line:
```bash
gunzip -c /tmp/crm_db_20260928_020000.sql.gz | mysql -u root -p crm_db
```

### Step 2.4: Run Database Migrations
Run the migration runner to apply any schema updates that were released after the backup was created:
```bash
cd /var/www/crm/current
php database/migrate.php
```

---

## 3. Restoring Uploaded Client Documents

Client documents (PAN card scans, GST certificates, registration docs) are stored in `storage/uploads/`.

### Step 3.1: Locate or Download the Uploads Archive
```bash
# If using S3:
aws s3 cp s3://my-crm-backups/crm-backups/crm_uploads_20260928_020000.tar.gz /tmp/

# Or from local backup directory:
cp /var/www/crm/storage/backups/crm_uploads_20260928_020000.tar.gz /tmp/
```

### Step 3.2: Restore to Storage Directory
Extract the archive directly into the shared storage directory:

**For Zero-Downtime VPS setup (`/var/www/crm/shared/storage`):**
```bash
# Extract into /var/www/crm/shared/storage (which contains the uploads/ directory)
tar -xzf /tmp/crm_uploads_20260928_020000.tar.gz -C /var/www/crm/shared/storage/
```

**For Standard or Shared Hosting setup (`/var/www/crm/storage`):**
```bash
tar -xzf /tmp/crm_uploads_20260928_020000.tar.gz -C /var/www/crm/storage/
```

### Step 3.3: Set Permissions & Security Hardening
Ensure web server (`www-data`) can read and write uploaded files, and that execution protection is active:
```bash
# 1. Fix ownership & permissions
chown -R www-data:www-data /var/www/crm/shared/storage/uploads
chmod -R 775 /var/www/crm/shared/storage/uploads

# 2. Verify .htaccess blocks direct script execution inside uploads
cat << 'EOF' > /var/www/crm/shared/storage/uploads/.htaccess
# Prevent script execution inside upload directory
RemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .cgi .pl .py .jsp .asp .htm .html .sh
RemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phps .cgi .pl .py .jsp .asp .htm .html .sh
php_flag engine off

<FilesMatch "(?i)\.(php|phtml|php3|php4|php5|php7|php8|phps|cgi|pl|py|jsp|asp|sh|exe|bat)$">
    Order Deny,Allow
    Deny from all
</FilesMatch>
EOF
chmod 644 /var/www/crm/shared/storage/uploads/.htaccess
```

---

## 4. Restoring Configuration & Encryption Keys

### Step 4.1: Verify `.env` Encryption Key
The system encrypts PAN numbers at rest using AES-256-GCM. 

> [!IMPORTANT]
> If `PAN_ENCRYPTION_KEY` in `.env` does not match the key used when encrypting client records, PAN decryption will fail and throw an encryption MAC mismatch exception. Always safeguard your `PAN_ENCRYPTION_KEY` in password managers or vault solutions.

Ensure `/var/www/crm/shared/.env` (or root `.env`) contains:
```ini
PAN_ENCRYPTION_KEY=your_64_character_hex_encryption_key_here
```

---

## 5. Cache & OPcache Invalidation

To prevent stale queries or orphaned cached stats from persisting after a database restore:

```bash
# 1. Clear file cache
rm -rf /var/www/crm/shared/storage/cache/* 2>/dev/null || rm -rf /var/www/crm/storage/cache/* 2>/dev/null

# 2. Flush Redis cache (if CACHE_DRIVER=redis or RATE_LIMIT_DRIVER=redis)
if command -v redis-cli >/dev/null 2>&1; then
    redis-cli flushdb || true
fi

# 3. Reload PHP-FPM to invalidate OPcache bytecode
sudo systemctl reload php8.2-fpm || sudo systemctl restart php8.2-fpm || true
```

---

## 6. Post-Restoration Verification Checklist

Run these commands to verify the system is 100% operational:

1. **Health Check Endpoint**:
   ```bash
   curl -i http://localhost/health
   # Expected response: HTTP/1.1 200 OK
   # {"status":"healthy","database":"connected","storage":"writable","disk_free":"...GB"}
   ```

2. **Verify Database Counts**:
   ```bash
   mysql -u crm_user -p crm_db -e "
   SELECT 
       (SELECT COUNT(*) FROM users) AS users_count,
       (SELECT COUNT(*) FROM clients) AS clients_count,
       (SELECT COUNT(*) FROM client_documents) AS documents_count,
       (SELECT COUNT(*) FROM follow_ups) AS followups_count;
   "
   ```

3. **Verify Uploads Accessibility**:
   Log in to the web UI (`/login`) as an administrator, navigate to a client profile (`/clients/{id}`), and download an uploaded document to verify decryption and download integrity.

4. **Clean Up Temporary Backup Files**:
   ```bash
   rm -f /tmp/crm_db_*.sql.gz /tmp/crm_uploads_*.tar.gz
   ```
