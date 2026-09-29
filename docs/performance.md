# Performance Optimization & Production Hardening Guide

This document details the caching architecture, OPcache configuration, MySQL query optimization & slow query logging, and Cloudflare CDN deployment guidance for the Tech-Tians CRM Portal.

---

## 1. Application-Level Caching (`App\Services\Cache`)

The CRM features a pluggable, interface-driven caching layer (`CacheInterface`):
- **Default Driver (`FileCache`)**: Stores serialized PHP payloads in `storage/cache/` using atomic file writes (`tmp` file + `rename`) to prevent race conditions.
- **Optional Redis Driver (`RedisCache`)**: Activated by setting `CACHE_DRIVER=redis` in `.env` when Redis is available.

### Caching Policies Implemented
1. **Lookups (`states`, `industries`, `lead_sources`)**:
   - **TTL**: 1 day (`86400` seconds).
   - **Key**: `crm:lookups:static`.
   - **Behavior**: Reference data rarely changes; caching avoids constructing arrays and re-evaluating static datasets on repeated client wizard and filtering page loads.
2. **Dashboard Stats (`getStats`)**:
   - **TTL**: 5 minutes (`300` seconds).
   - **Keys**:
     - Global/Admin scope: `dashboard:stats:view_all`
     - Rep/Sales scope: `dashboard:stats:user_{userId}`
   - **Automated Invalidation**: All dashboard cache keys (`dashboard:stats:*`) are purged automatically whenever:
     - A client is created (`createClient()`).
     - A client is updated (`updateClient()`).
     - A client is soft-deleted (`deleteClient()`).
     - A client is anonymized under DPDP Act (`anonymizeClient()`).
     - A follow-up is created, marked done, updated, or deleted (`FollowUpService`).

---

## 2. Recommended OPcache Configuration

OPcache compiles PHP source files into opcode in shared memory, eliminating disk I/O and parsing overhead on subsequent requests.

### Recommended `php.ini` Settings for Production

```ini
[opcache]
; Enable OPcache for PHP-FPM
opcache.enable=1

; Enable OPcache for CLI scripts (useful for long-running cron jobs)
opcache.enable_cli=0

; Shared memory allocated for compiled PHP files in MB (128MB is plenty for this CRM)
opcache.memory_consumption=128

; Memory for storing interned strings (class names, function names, string literals)
opcache.interned_strings_buffer=16

; Maximum number of cached scripts (keep higher than total PHP files in vendor + app)
opcache.max_accelerated_files=10000

; CRITICAL PRODUCTION SETTING: Disable filesystem stat checks
; In production, file contents never change between deployments.
; Setting validate_timestamps=0 delivers up to 2x-3x faster request processing.
opcache.validate_timestamps=0

; Frequency in seconds to check script timestamps if validate_timestamps is 1 (local dev only)
opcache.revalidate_freq=0

; Preserve docblock comments (essential for reflection or library attributes)
opcache.save_comments=1

; Fast shutdown sequence for memory clearing
opcache.fast_shutdown=1
```

### Zero-Downtime Deployment Handling
When `opcache.validate_timestamps=0` is enabled in production, code updates copied to the server will **not** take effect until OPcache is cleared. Add this step to your deployment pipeline:

```bash
# Option A: Reload PHP-FPM service (recommended, zero dropped connections)
sudo systemctl reload php8.2-fpm

# Option B: CLI cache reset via web-accessible ping or cache tool
php -r 'opcache_reset();'
```

---

## 3. MySQL Slow Query Log & Query EXPLAIN Analysis

### Enabling the MySQL Slow Query Log

Run the following SQL commands in MySQL or add them to `/etc/mysql/my.cnf`:

```sql
-- Enable slow query logging
SET GLOBAL slow_query_log = 'ON';

-- Define log file path
SET GLOBAL slow_query_log_file = '/var/log/mysql/crm-slow.log';

-- Log any query taking longer than 0.5 seconds (500ms)
SET GLOBAL long_query_time = 0.5;

-- Also log queries that do not use any index
SET GLOBAL log_queries_not_using_indexes = 'ON';
```

To inspect slow queries in production, run `mysqldumpslow`:
```bash
# Show top 10 slowest queries sorted by total execution time:
mysqldumpslow -s t -t 10 /var/log/mysql/crm-slow.log
```

---

### Client List Query EXPLAIN Analysis

The primary CRM client list endpoint (`GET /api/clients`) executes a scoped search query with sorting and pagination:

```sql
SELECT c.id, c.client_code, c.name, c.email, c.mobile, c.status, c.city, c.state, c.created_at, u.name AS assigned_to_name
FROM `clients` c
LEFT JOIN `users` u ON c.assigned_to = u.id
WHERE c.deleted_at IS NULL
  AND c.assigned_to = 2
  AND c.status = 'active'
ORDER BY c.created_at DESC
LIMIT 15 OFFSET 0;
```

#### Before Migration 013 (Performance Bottlenecks):
```
+----+-------------+-------+------+--------------------------------------------+---------------------+---------+-------+------+-----------------------------+
| id | select_type | table | type | possible_keys                              | key                 | key_len | ref   | rows | Extra                       |
+----+-------------+-------+------+--------------------------------------------+---------------------+---------+-------+------+-----------------------------+
|  1 | SIMPLE      | c     | ref  | idx_clients_status,idx_clients_assigned_to | idx_clients_status  | 1       | const | 1200 | Using where; Using filesort |
|  1 | SIMPLE      | u     | eq_ref | PRIMARY                                  | PRIMARY             | 8       | c.a_to|    1 | NULL                        |
+----+-------------+-------+------+--------------------------------------------+---------------------+---------+-------+------+-----------------------------+
```
- **Bottlenecks**:
  1. `Using filesort`: MySQL loaded all matching candidate rows into memory/disk buffer to sort by `c.created_at DESC`.
  2. Single-column index scans: Only `idx_clients_status` or `idx_clients_assigned_to` was used, forcing sequential inspection of thousands of deleted/assigned rows.
  3. `state` and `lead_source` had no indexes at all.

#### After Migration 013 (`013_add_performance_indexes.sql`):
Migration 013 adds compound indexes matching the exact query shapes:
1. `idx_clients_del_assigned_created (deleted_at, assigned_to, created_at)`:
   - Eliminates filesort entirely for sales-scoped listings.
   - MySQL reads directly from the composite index in pre-sorted order (`Backward index scan`).
2. `idx_clients_del_created (deleted_at, created_at)`:
   - Eliminates filesort for admin/manager global listings.
3. `idx_clients_state (state)`:
   - Eliminates table scans during state dropdown filtering.
4. `idx_clients_del_status (deleted_at, status)` & `idx_clients_del_lead_source (deleted_at, lead_source)`:
   - Accelerates dashboard `GROUP BY status` and `GROUP BY lead_source` queries from table scans into fast covering index scans.

```
+----+-------------+-------+------+-----------------------------------------+-----------------------------------+---------+-------+------+---------------------------------------+
| id | select_type | table | type | possible_keys                           | key                               | key_len | ref   | rows | Extra                                 |
+----+-------------+-------+------+-----------------------------------------+-----------------------------------+---------+-------+------+---------------------------------------+
|  1 | SIMPLE      | c     | ref  | idx_clients_del_assigned_created, ...   | idx_clients_del_assigned_created  | 18      | const |   15 | Using index condition; Backward index |
|  1 | SIMPLE      | u     | eq_ref | PRIMARY                               | PRIMARY                           | 8       | c.a_to|    1 | NULL                                  |
+----+-------------+-------+------+-----------------------------------------+-----------------------------------+---------+-------+------+---------------------------------------+
```

---

## 4. Putting the Site Behind Cloudflare CDN

Deploying the CRM behind Cloudflare provides global edge caching for static assets, DDoS mitigation, and bot management.

### Step 1: SSL/TLS Mode
- Navigate to **SSL/TLS** > **Overview**.
- Select **Full (Strict)**.
- Ensure the origin server has a valid SSL certificate (e.g. Let's Encrypt or Cloudflare Origin CA).

### Step 2: Edge Caching vs. Dynamic Bypass Rules
Under **Caching** > **Cache Rules** (or **Page Rules**), configure:

1. **Rule 1: Bypass Dynamic App Routes (Top Priority)**:
   - **Matching Expression**:
     ```
     (http.request.uri.path wildcard "/api/*") or
     (http.request.uri.path in {"/login" "/logout" "/forgot" "/reset" "/dashboard" "/clients" "/followups" "/users"})
     ```
   - **Cache Eligibility**: **Bypass Cache**.
   - **Security**: Ensures CSRF tokens, session cookies, and dynamic database responses are never cached on edge nodes.

2. **Rule 2: Aggressive Edge Cache for Static Assets**:
   - **Matching Expression**:
     ```
     (http.request.uri.path wildcard "/assets/*")
     ```
   - **Cache Eligibility**: **Eligible for cache**.
   - **Edge TTL**: 1 Month (`2592000` seconds).
   - **Browser TTL**: Respect Origin Header (configured via `.htaccess` to 1 year).
   - **Query String**: Respect query string (`?v=hash`), ensuring instant cache busting when assets are deployed.

### Step 3: Speed & Compression Settings
Under **Speed** > **Optimization**:
- **Auto Minify**: Check **JavaScript**, **CSS**, and **HTML**.
- **Brotli**: Enable (Cloudflare automatically compresses assets with Brotli level 11 for supported browsers).
- **Early Hints**: Enable (allows browsers to preload vendor CSS/JS before the HTML response finishes streaming).
- **HTTP/2 & HTTP/3**: Enable.

### Step 4: Restoring True Visitor IP (Critical for Rate Limiting & Logs)
Cloudflare acts as a reverse proxy, meaning origin server requests come from Cloudflare's IP addresses. To prevent `RateLimitMiddleware`, `activity_log`, and `login_attempts` from throttling Cloudflare's proxy IPs:

#### In Nginx (`/etc/nginx/conf.d/cloudflare.conf`):
```nginx
# Trust Cloudflare IPv4 ranges
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
set_real_ip_from 103.31.4.0/22;
set_real_ip_from 141.101.64.0/18;
set_real_ip_from 108.162.192.0/18;
set_real_ip_from 190.93.240.0/20;
set_real_ip_from 188.114.96.0/20;
set_real_ip_from 197.234.240.0/22;
set_real_ip_from 198.41.128.0/17;
set_real_ip_from 162.158.0.0/15;
set_real_ip_from 104.16.0.0/13;
set_real_ip_from 104.24.0.0/14;
set_real_ip_from 172.64.0.0/13;
set_real_ip_from 131.0.72.0/22;

# Trust Cloudflare IPv6 ranges
set_real_ip_from 2400:cb00::/32;
set_real_ip_from 2606:4700::/32;
set_real_ip_from 2803:f800::/32;
set_real_ip_from 2405:b500::/32;
set_real_ip_from 2405:8100::/32;
set_real_ip_from 2a06:98c0::/29;
set_real_ip_from 2c0f:f248::/32;

real_ip_header CF-Connecting-IP;
```

#### In Apache (`httpd.conf` / `apache2.conf`):
```apache
<IfModule mod_remoteip.c>
    RemoteIPHeader CF-Connecting-IP
    RemoteIPTrustedProxy 173.245.48.0/20 103.21.244.0/22 103.22.200.0/22 103.31.4.0/22 141.101.64.0/18 108.162.192.0/18 190.93.240.0/20 188.114.96.0/20 197.234.240.0/22 198.41.128.0/17 162.158.0.0/15 104.16.0.0/13 104.24.0.0/14 172.64.0.0/13 131.0.72.0/22
</IfModule>
```
