# Tech-Tians CRM Portal

A production-ready CRM portal for Vyapar Care Consultancy, built with PHP 8.2+ (custom MVC, no framework), MySQL 8, and vanilla JavaScript + Bootstrap 5.

---

## Features

### Client Management
- Multi-step wizard registration (5-step form with live validation)
- Individual, Proprietorship, Partnership, Company, LLP client types
- Auto-generated client codes (e.g., `IND-0001`, `PROP-0042`)
- GST/PAN validation with PAN encryption at rest (AES-256-GCM)
- Document uploads with MIME verification
- Soft delete + DPDP Act anonymization

### Follow-up System
- Schedule calls, meetings, emails, visits per client
- Daily reminder emails (cron-driven, queued)
- Auto-mark missed follow-ups past due date

### Dashboard & Analytics
- Stat cards: total clients, new this month, today's & overdue follow-ups
- Charts: monthly trend (line), lead sources (doughnut), status breakdown (bar)
- Top 5 staff leaderboard (admin/manager only)

### User & Permission Management
- Role-based access: Admin, Manager, Sales Representative
- Granular permissions per role (client.create, client.edit, client.export, etc.)
- Sales reps see only their own clients; admins/managers see all

### Security
- Prepared statements everywhere (zero raw SQL concatenation)
- CSRF on every POST/PUT/DELETE
- Rate limiting (login, forgot password, API endpoints)
- Cloudflare Turnstile / reCAPTCHA on login
- Security headers (CSP, HSTS, X-Frame-Options, etc.)
- Password policy: min 8 chars, letters + numbers, common password blocklist
- Account lockout after failed login attempts

### Scaling Readiness
- Configurable session handler (files, database, Redis)
- File storage abstraction (local disk or S3/R2)
- Database-backed job queue with cron worker
- Optional read replica for reports
- Cache layer (file or Redis)

### Monitoring & Observability
- Sentry integration (PHP + browser SDKs)
- Structured logging with daily rotation (app, error, security, audit channels)
- `/health` endpoint (DB, disk, storage checks)
- Admin log viewer

---

## Quick Start

### Prerequisites
- PHP 8.2+ with extensions: `pdo_mysql`, `mbstring`, `openssl`, `json`, `fileinfo`
- MySQL 8.0+
- Composer 2.x

### 1. Clone & Install

```bash
git clone https://github.com/vyaparcareconsultancy/Tech-Tians-Academy.git CRM
cd CRM
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
# Edit .env with your database credentials
```

### 3. Run Migrations & Seed

```bash
php database/migrate.php
php database/seeds/seed.php
```

This creates the admin user (credentials from `.env`: `ADMIN_EMAIL` / `ADMIN_PASSWORD`).

### 4. Start Development Server

```bash
php -S localhost:8000 -t public
```

Visit: [http://localhost:8000/login](http://localhost:8000/login)

### XAMPP / WAMP (Windows)

1. Copy project to `C:\xampp\htdocs\CRM`
2. Set document root or access via `http://localhost/CRM/public`
3. Ensure `mod_rewrite` is enabled

---

## Environment Variables

| Variable | Default | Description |
|:---|:---|:---|
| `APP_ENV` | `local` | `local` or `production` |
| `APP_DEBUG` | `true` | Show errors in dev |
| `APP_URL` | `http://localhost/CRM/public` | Base URL |
| `APP_KEY` | — | Application encryption key |
| `DB_HOST` | `127.0.0.1` | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_NAME` | `crm_db` | Database name |
| `DB_USER` | `root` | Database user |
| `DB_PASS` | — | Database password |
| `DB_READ_HOST` | — | Read replica host (optional) |
| `SESSION_LIFETIME` | `120` | Session timeout (minutes) |
| `SESSION_DRIVER` | `files` | `files`, `database`, or `redis` |
| `CACHE_DRIVER` | `file` | `file` or `redis` |
| `STORAGE_DRIVER` | `local` | `local` or `s3` |
| `RATE_LIMIT_DRIVER` | `database` | `database` or `redis` |
| `MAIL_HOST` | — | SMTP host |
| `TURNSTILE_ENABLED` | `false` | Enable Cloudflare Turnstile |
| `SENTRY_DSN` | — | Sentry error tracking DSN |
| `PAN_ENCRYPTION_KEY` | — | AES-256-GCM key for PAN encryption |

See [`.env.example`](.env.example) for the full list.

---

## Commands

```bash
# Development
php -S localhost:8000 -t public          # Start dev server

# Database
php database/migrate.php                 # Run all migrations
php database/seeds/seed.php              # Seed admin user + roles
php scripts/reset_local_data.php         # Clean demo/test data (local dev only)

# Cron Jobs (add to crontab in production)
php cron/worker.php                      # Process job queue (every minute)
php cron/daily_reminders.php             # Send follow-up reminders (daily 8 AM)
php cron/mark_missed.php                 # Mark overdue follow-ups (daily 11 PM)

# Backup & Restore
bash scripts/backup.sh                   # Database + uploads backup
# See scripts/restore.md for recovery steps

# Testing & Quality
bash scripts/lint.sh                     # Check syntax on all PHP files (exits non-zero on error)
composer test                            # PHPStan + PHPUnit + Playwright
```

---

## Folder Structure

```
CRM/
├── app/
│   ├── Controllers/          # Request handlers (thin, delegate to Services)
│   ├── Core/                 # Database, Logger, Model, Request, Response, Session, Validator, View
│   ├── Exceptions/           # ValidationException
│   ├── Helpers/              # Csrf, Crypto (PAN encryption)
│   ├── Middleware/            # Auth, Permission, RateLimit, SecurityHeaders
│   ├── Models/               # Eloquent-style models (Client, User, FollowUp, etc.)
│   ├── Services/             # Business logic (ClientService, AuthService, JobQueue, etc.)
│   │   ├── Cache/            # CacheInterface + FileCache/RedisCache
│   │   ├── RateLimiter/      # RateLimitStore interface + DB/Redis implementations
│   │   └── Storage/          # StorageInterface + LocalStorage/S3Storage
│   ├── Views/                # PHP templates (layouts, auth, clients, dashboard, etc.)
│   └── Router.php            # Route dispatcher
├── config/
│   ├── database.php          # PDO configuration
│   └── routes.php            # All route definitions
├── cron/                     # Cron scripts (worker, reminders, missed follow-ups)
├── database/
│   ├── migrations/           # Numbered SQL migration files (001–014)
│   ├── migrate.php           # Migration runner
│   └── seeds/                # Database seeders
├── docs/                     # Documentation
│   ├── deployment.md         # Production hosting guide (shared + VPS)
│   ├── scaling.md            # Scaling readiness guide + architecture diagram
│   ├── monitoring.md         # Monitoring setup guide
│   ├── performance.md        # Caching & performance tuning
│   ├── routes.md             # Complete route table
│   ├── SECURITY.md           # Security measures & DPDP compliance
│   └── er-diagram.md         # Entity relationship diagram
├── public/                   # Web root (document root points here)
│   ├── index.php             # Single entry point
│   ├── .htaccess             # Apache rewrite rules
│   └── assets/               # CSS, JS, vendor libraries
├── scripts/                  # DevOps & utility scripts
│   ├── lint.sh               # Syntax linter (php -l on all PHP files)
│   ├── backup.sh             # Automated backup
│   ├── deploy_remote.sh      # Atomic deployment
│   ├── rollback.sh           # Release rollback
│   └── restore.md            # Disaster recovery
├── storage/
│   ├── cache/                # File cache (gitignored)
│   ├── logs/                 # Application logs (gitignored)
│   └── uploads/              # Client document uploads (gitignored)
├── tests/                    # PHPUnit + Playwright tests
├── .github/workflows/        # CI/CD (ci.yml, deploy.yml)
├── .env.example              # Environment template
├── composer.json             # PHP dependencies
├── CONTRIBUTING.md           # Branch strategy, PR rules
└── PROJECT_CONTEXT.md        # Architecture decisions & constraints
```

---

## Screenshots

> Screenshots to be added after deployment.

| Screen | Path |
|:---|:---|
| Login Page | `docs/screenshots/login.png` |
| Dashboard | `docs/screenshots/dashboard.png` |
| Client List | `docs/screenshots/clients.png` |
| Client Registration | `docs/screenshots/create-client.png` |
| Client Profile | `docs/screenshots/client-profile.png` |
| Follow-ups | `docs/screenshots/followups.png` |
| User Management | `docs/screenshots/users.png` |

---

## Documentation

- [Deployment Guide](docs/deployment.md) — Shared hosting & VPS setup
- [Scaling Guide](docs/scaling.md) — When and how to scale
- [Security Measures](docs/SECURITY.md) — Security audit & DPDP compliance
- [Monitoring Guide](docs/monitoring.md) — UptimeRobot, Sentry, alerts
- [Performance Guide](docs/performance.md) — OPcache, caching, indexes
- [Route Table](docs/routes.md) — All endpoints with middleware
- [Contributing](CONTRIBUTING.md) — Branch strategy & PR rules
- [API Collection](docs/CRM_Portal.postman_collection.json) — Postman import

---

## License

Proprietary — Vyapar Care Consultancy. All rights reserved.
