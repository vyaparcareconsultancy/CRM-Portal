# Tech-Tians CRM - Production Hosting & Deployment Manual

This guide covers complete production deployment configurations for the **Tech-Tians CRM Portal**, providing end-to-end instructions for two hosting models:
- **Option A**: Shared Hosting (cPanel / Hostinger)
- **Option B**: Production VPS (Ubuntu 24.04 LTS with Nginx, PHP 8.2-FPM, MySQL 8, Redis)

It also includes the **Production .env Checklist**, **Automated Backups & Disaster Recovery**, and **Zero-Downtime CI/CD Pipeline**.

---

## 1. Production `.env` Checklist

Before launching, verify every environment variable in `/var/www/crm/shared/.env` (or `.env` in root) satisfies production security standards:

| Variable | Recommended Production Value | Description & Security Check |
| :--- | :--- | :--- |
| `APP_ENV` | `production` | Disables debug stack traces; enforces strict error handlers. |
| `APP_DEBUG` | `false` | **Critical**: Never set to `true` in production to prevent leaking database credentials or paths. |
| `APP_URL` | `https://crm.yourdomain.com` | Full HTTPS domain URL without trailing slash. |
| `APP_KEY` | `base64:32_random_bytes...` | Random 32-byte key for application hashing. |
| `PAN_ENCRYPTION_KEY` | `64_hex_chars...` | **Critical**: 64-character hex key (256-bit) used by AES-256-GCM. *Do not lose this key or stored PAN numbers cannot be decrypted.* |
| `DB_HOST` | `127.0.0.1` | Localhost or managed database cluster endpoint. |
| `DB_PORT` | `3306` | MySQL port. |
| `DB_NAME` | `crm_prod_db` | Dedicated production database name. |
| `DB_USER` | `crm_prod_user` | Dedicated MySQL user (never `root`). |
| `DB_PASS` | `StrongRandomPass#2026!` | High-entropy database password. |
| `SESSION_LIFETIME`| `30` or `60` | Inactivity session timeout in minutes. |
| `SESSION_SECURE` | `true` | Enforces `Secure` flag on session cookies (requires HTTPS). |
| `SESSION_HTTPONLY`| `true` | Prevents JavaScript access to session cookie (mitigates XSS). |
| `SESSION_SAMESITE`| `Lax` or `Strict` | Enforces `SameSite` cookie policy against CSRF. |
| `MAIL_MAILER` | `smtp` | Primary email transport. |
| `MAIL_HOST` | `smtp.sendgrid.net` / `smtp.mailgun.org` | Transactional email provider host. |
| `MAIL_PORT` | `587` | TLS submission port. |
| `MAIL_USERNAME` | `apikey` or SMTP username | Authenticated SMTP account. |
| `MAIL_PASSWORD` | `YourSecretApiKey` | SMTP account password/token. |
| `MAIL_ENCRYPTION`| `tls` | Enforce TLS in transit for notifications. |
| `MAIL_FROM_ADDRESS`| `noreply@crm.yourdomain.com` | Verified sending domain. |
| `MAIL_FROM_NAME` | `"Tech-Tians CRM"` | Sender name on client emails. |
| `RATE_LIMIT_DRIVER`| `database` or `redis` | Store for rate limiter (`database` using `rate_limits` table or `redis`). |
| `CACHE_DRIVER` | `file` or `redis` | Cache driver (`file` using `storage/cache` or `redis`). |
| `REDIS_HOST` | `127.0.0.1` | Redis server IP (if driver is redis). |
| `REDIS_PORT` | `6379` | Redis port. |
| `REDIS_PASSWORD` | `StrongRedisPassword` | Redis authentication password. |
| `TURNSTILE_ENABLED`| `true` | Enables Cloudflare Turnstile bot protection on login. |
| `TURNSTILE_SITE_KEY`| `0x4AAAAAA...` | Turnstile public widget site key. |
| `TURNSTILE_SECRET_KEY`| `0x4AAAAAA...` | Turnstile backend secret verification key. |
| `SENTRY_DSN` | `https://...@sentry.io/...` | Sentry error tracking endpoint (optional; leave blank to disable). |

### Key Generation Commands:
```bash
# Generate 64-character hex PAN Encryption Key:
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# Generate base64 APP_KEY:
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

---

## 2. Option A: Shared Hosting Guide (cPanel / Hostinger)

Shared hosting is suitable for low-to-medium traffic deployments without SSH root access.

### Step 2.1: Package and Upload Application Code
1. On your development machine or CI, create an archive excluding development files:
   ```bash
   zip -r crm-deploy.zip . \
     -x "*.git*" ".github/*" "tests/*" "node_modules/*" "playwright-report/*" \
     "test-results/*" ".phpunit.cache/*" "storage/uploads/*" "storage/logs/*" "storage/cache/*"
   ```
2. Log in to **cPanel / Hostinger File Manager** or connect via **SFTP**.
3. Upload and extract `crm-deploy.zip` into the user home directory (e.g. `/home/username/crm_app` or `domains/yourdomain.com/crm_app`).

> [!IMPORTANT]
> **Security Rule**: Never place the root project directory directly into `public_html`. Keep the core code outside `public_html`, and point only the `public/` directory as the web root.

### Step 2.2: Configure Document Root to `/public`
- **In cPanel**:
  1. Navigate to **Domains** ➔ **Domains** or **Subdomains**.
  2. Edit the document root of your domain to point to `/home/username/crm_app/public`.
- **In Hostinger (hPanel)**:
  1. Navigate to **Websites** ➔ **Dashboard** ➔ **Advanced** ➔ **Websites / Document Root**.
  2. Change target directory to `crm_app/public`.
- **Fallback (If host does not allow modifying document root)**:
  If forced to use `public_html/`, place all application folders inside `public_html/` and add the following root `.htaccess` in `public_html/`:
  ```apache
  <IfModule mod_rewrite.c>
      RewriteEngine On
      RewriteRule ^$ public/ [L]
      RewriteRule (.*) public/$1 [L]
  </IfModule>
  ```
  And secure the parent directory so `.env` and `storage` cannot be downloaded:
  ```apache
  <Files ".env*">
      Order allow,deny
      Deny from all
  </Files>
  ```

### Step 2.3: Create MySQL Database & User
1. In cPanel, open **MySQL Database Wizard** (or Hostinger **Databases**).
2. Create database: `username_crmdb`.
3. Create user: `username_crmuser` with a strong random password.
4. Assign user to database with **ALL PRIVILEGES**.
5. Set database collation to `utf8mb4_unicode_ci`.

### Step 2.4: Configure Environment (`.env`)
1. Create or edit `.env` in the root application directory (`/home/username/crm_app/.env`).
2. Populate the production values from the **Production .env Checklist** (DB credentials, encryption keys, mail, etc.).
3. Set file permissions to `600` via File Manager or Terminal:
   ```bash
   chmod 600 .env
   ```

### Step 2.5: Execute Database Migrations
- **Via cPanel Terminal / SSH**:
  ```bash
  cd ~/crm_app
  php database/migrate.php
  ```
- **Via phpMyAdmin (Manual)**:
  If SSH/Terminal is unavailable:
  1. Open **phpMyAdmin**.
  2. Select `username_crmdb`.
  3. Go to **Import**, and sequentially import the migration files located in `database/migrations/*.sql` in alphabetical order (`001_...`, `002_...`, etc.).

### Step 2.6: Configure Cron Jobs
In cPanel, open **Cron Jobs** (or Hostinger **Cron Jobs**):

1. **Mark Missed Follow-ups** (Runs every 15 minutes):
   ```bash
   */15 * * * * /usr/local/bin/php /home/username/crm_app/cron/mark_missed.php >/dev/null 2>&1
   ```
2. **Daily Reminder Emails** (Runs daily at 08:00 AM IST):
   ```bash
   0 8 * * * /usr/local/bin/php /home/username/crm_app/cron/daily_reminders.php >/dev/null 2>&1
   ```
3. **Automated Daily Backup** (Runs daily at 02:00 AM):
   ```bash
   0 2 * * * /bin/bash /home/username/crm_app/scripts/backup.sh >/dev/null 2>&1
   ```
*(Note: Replace `/usr/local/bin/php` with your host's PHP 8.2 CLI binary path, e.g. `/usr/bin/ea-php82` on cPanel).*

### Step 2.7: Activate Free SSL & Force HTTPS
1. In cPanel, navigate to **SSL/TLS Status** ➔ Run **AutoSSL** (or Let's Encrypt in Hostinger).
2. Enable **Force HTTPS Redirect** in cPanel **Domains** or in `public/.htaccess`.

---

## 3. Option B: Production VPS Guide (Ubuntu 24.04 LTS)

Recommended for full control, maximum security, high concurrency, and zero-downtime automated deployment.

### Step 3.1: System Packages & Updates
Log in via SSH as `root` or `sudo` user:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip zip rsync htop ufw fail2ban software-properties-common
```

### Step 3.2: Install Nginx, PHP 8.2-FPM, MySQL 8, Redis & Certbot
```bash
# Add ondrej/php PPA for verified PHP 8.2 packages on Ubuntu
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# Install Nginx, MySQL, Redis, and Certbot
sudo apt install -y nginx mysql-server redis-server certbot python3-certbot-nginx

# Install PHP 8.2-FPM and required extensions
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
                    php8.2-curl php8.2-gd php8.2-bcmath php8.2-zip php8.2-redis \
                    php8.2-intl php8.2-cli

# Install Composer globally
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

### Step 3.3: Secure MySQL Installation & Create CRM Database
```bash
# Secure initial MySQL installation
sudo mysql_secure_installation

# Configure MySQL database and dedicated user
sudo mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS crm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'crm_user'@'127.0.0.1' IDENTIFIED BY 'StrongDatabasePassword#2026';
GRANT ALL PRIVILEGES ON crm_db.* TO 'crm_user'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
```

### Step 3.4: Configure System Firewall (`ufw`)
```bash
# Default policies: block incoming, allow outgoing
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Allow SSH and Nginx (HTTP & HTTPS)
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'

# Enable firewall
sudo ufw --force enable
sudo ufw status verbose
```

### Step 3.5: Configure Intrusion Prevention (`fail2ban`)
Create `/etc/fail2ban/jail.local`:
```ini
[DEFAULT]
bantime  = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
port    = ssh
backend = systemd

[nginx-http-auth]
enabled = true
port    = http,https

[nginx-botsearch]
enabled  = true
port     = http,https
filter   = nginx-botsearch
logpath  = /var/log/nginx/error.log
maxretry = 2
```
Start and enable fail2ban:
```bash
sudo systemctl enable --now fail2ban
sudo fail2ban-client status
```

### Step 3.6: Nginx Server Block with Security Headers & Rate Limiting
Create `/etc/nginx/sites-available/crm`:

```nginx
# Rate limiting zone: 10 requests/second per IP with 10MB memory buffer
limit_req_zone $binary_remote_addr zone=crm_api:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=crm_login:10m rate=5r/m;

server {
    listen 80;
    listen [::]:80;
    server_name crm.yourdomain.com;

    # Document root points strictly to /public inside the atomic current symlink
    root /var/www/crm/current/public;
    index index.php index.html;

    # Maximum file upload size (matches php.ini)
    client_max_body_size 25M;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/xml+rss text/javascript;

    # Security Headers
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com;" always;

    # Primary Routing
    location / {
        limit_req zone=crm_api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Stricter rate limiting on authentication
    location ~* /(login|api/login|forgot-password) {
        limit_req zone=crm_login burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Static Assets Caching (1 year)
    location ~* \.(css|js|jpg|jpeg|png|gif|ico|svg|woff2|woff|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, no-transform, immutable";
        access_log off;
    }

    # PHP-FPM Execution
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param HTTP_PROXY "";
        fastcgi_read_timeout 60;
    }

    # Deny access to hidden files and dotfiles (.env, .git, etc.)
    location ~ /\.(?!well-known).* {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny direct access to sensitive storage directories
    location ~ ^/(storage/logs|storage/cache) {
        deny all;
        return 404;
    }
}
```

Enable site configuration and test syntax:
```bash
sudo ln -s /etc/nginx/sites-available/crm /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

### Step 3.7: PHP-FPM 8.2 Production Tuning
1. **Configure PHP-FPM Pool (`/etc/php/8.2/fpm/pool.d/www.conf`)**:
   Tune server counts based on available server RAM (e.g. 2GB - 4GB RAM):
   ```ini
   pm = dynamic
   pm.max_children = 50
   pm.start_servers = 10
   pm.min_spare_servers = 5
   pm.max_spare_servers = 20
   pm.max_requests = 1000
   ```

2. **Configure Production PHP Settings (`/etc/php/8.2/fpm/php.ini`)**:
   ```ini
   expose_php = Off
   display_errors = Off
   display_startup_errors = Off
   error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
   log_errors = On
   error_log = /var/log/php8.2-fpm.log

   memory_limit = 256M
   upload_max_filesize = 20M
   post_max_size = 25M
   max_execution_time = 60

   # OPcache High Performance Settings
   opcache.enable = 1
   opcache.memory_consumption = 128
   opcache.interned_strings_buffer = 16
   opcache.max_accelerated_files = 10000
   opcache.validate_timestamps = 0
   opcache.save_comments = 1
   opcache.revalidate_freq = 0
   ```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.2-fpm
```

### Step 3.8: Issue Free SSL Certificate via Certbot
```bash
sudo certbot --nginx -d crm.yourdomain.com --agree-tos --email admin@yourdomain.com --redirect
sudo certbot renew --dry-run
```

### Step 3.9: Configure Production Crontab
Edit the crontab for user `deploy` or `www-data`:
```bash
sudo crontab -u deploy -e
```
Add the following entries:
```cron
# 1. Mark missed pending follow-ups every 15 minutes
*/15 * * * * /usr/bin/php /var/www/crm/current/cron/mark_missed.php >> /var/www/crm/shared/storage/logs/cron.log 2>&1

# 2. Send daily morning reminder emails at 08:00 AM IST
0 8 * * * /usr/bin/php /var/www/crm/current/cron/daily_reminders.php >> /var/www/crm/shared/storage/logs/cron.log 2>&1

# 3. Nightly database and document backup at 02:00 AM with 14-day retention & S3 offload
0 2 * * * /bin/bash /var/www/crm/scripts/backup.sh >> /var/www/crm/shared/storage/logs/backup.log 2>&1
```

---

## 4. Zero-Downtime CI/CD Pipeline Reference

Deployments are driven automatically via GitHub Actions upon push to `main` (Production) and `develop` (Staging).

### Server Topology
```
/var/www/crm/
├── current ─────────────────────────► symlink to /releases/YYYYMMDDHHMMSS
├── releases/
│   ├── 20260928010000/
│   └── 20260928020000/               # Active release
├── shared/
│   ├── .env                          # Production secrets (mode 600)
│   └── storage/
│       ├── uploads/                  # Persistent documents
│       ├── logs/                     # Persistent daily logs
│       └── cache/                    # Persistent cache
└── scripts/
    ├── backup.sh                     # Automated daily backup
    └── rollback.sh                   # Instant release rollback
```

### GitHub Secrets Configuration

| Secret Name | Description | Example |
| :--- | :--- | :--- |
| `SSH_HOST` | Remote server IP address or hostname | `198.51.100.42` or `crm.techtians.in` |
| `SSH_USER` | Deploy user configured on server | `deploy` |
| `SSH_KEY` | Private SSH key (without passphrase) | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `SSH_PORT` | SSH port (defaults to 22) | `22` |
| `DEPLOY_PATH` | Base application root | `/var/www/crm` |

---

## 5. Instant Rollback Utility

If a newly deployed release experiences issues, execute the rollback script on the server:

```bash
# Instant rollback to immediately previous release:
/var/www/crm/scripts/rollback.sh

# Or rollback to an explicit historical release:
/var/www/crm/scripts/rollback.sh 20260928010000
```

The script:
1. Atomically repoints `/var/www/crm/current` to the prior release directory.
2. Flushes OPcache by reloading PHP-FPM (`sudo systemctl reload php8.2-fpm`).
3. Completes in `< 500ms` with zero dropped HTTP requests.

---

## 6. Verification and Health Checking

After initial setup or updates, verify system status:

```bash
# 1. Check health check endpoint
curl -i https://crm.yourdomain.com/health

# 2. Check Nginx and PHP-FPM service statuses
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql
sudo systemctl status redis-server

# 3. Verify security headers in production
curl -I https://crm.yourdomain.com/
```
