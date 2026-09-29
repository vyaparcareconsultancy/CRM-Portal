# Security Architecture & Hardening Guide

## 1. Overview & Threat Model

The Tech-Tians CRM Portal is built with defense-in-depth principles to protect enterprise client records, financial identifiers (PAN, GSTIN), authentication credentials, and business activity logs against unauthorized access, data leaks, and tampering.

This document details the hardening controls implemented across the system and outlines operational compliance with the **Digital Personal Data Protection (DPDP) Act, 2023**.

---

## 2. Security Controls & Hardening Checklist

### 2.1 SQL Injection Prevention
- **Parameterized Queries**: 100% of database interactions in models (`Client`, `FollowUp`, `User`, `ClientDocument`, `ActivityLog`, `PasswordReset`, `LoginAttempt`, `Model`) utilize PDO prepared statements with positional (`?`) placeholders.
- **Whitelist Column Binding**: Dynamic table sorting parameters (`sort_by`, `sort_dir`) are strictly checked against explicit allowed column whitelists.
- **Integer Bounds**: Pagination offsets and limits are clamped to positive integers using `max()` and `min()`.
- **Auditing Result**: Zero dynamic variable string interpolations into SQL execution statements exist across the entire codebase.

### 2.2 Cross-Site Scripting (XSS) & Content Security
- **Output Escaping**: All dynamic data rendered in PHP templates is sanitized using `e()` / `View::e()`, which calls `htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- **Proper JSON Headers**: All API endpoints use `Response::json()`, which explicitly sets `Content-Type: application/json; charset=utf-8` and encodes with `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`.
- **Content-Security-Policy (CSP)**: Handled via `SecurityHeadersMiddleware` with `default-src 'self'`.
- **MIME Sniffing**: Header `X-Content-Type-Options: nosniff` is attached to every response.
- **Anti-Clickjacking**: Header `X-Frame-Options: DENY` and `frame-ancestors 'none'` prevent embedding in malicious iframes.
- **Referrer Policy**: `Referrer-Policy: strict-origin-when-cross-origin` restricts leaking URLs across domains.
- **Permissions Policy**: `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()` disables unauthorized hardware and sensor access.

### 2.3 Cross-Site Request Forgery (CSRF)
- **Token Generation**: Cryptographically secure 256-bit random tokens (`bin2hex(random_bytes(32))`) stored in `$_SESSION['_csrf_token']`.
- **Enforcement**: Validated on every state-altering request (`POST`, `PUT`, `DELETE`, `PATCH`) across all controllers (`AuthController`, `ClientController`, `UserController`, `FollowUpController`).
- **Timing Safe**: Token comparison uses PHP's constant-time `hash_equals()` to prevent timing attacks.
- **Frontend Integration**: Automatically attached to all AJAX requests via `X-CSRF-Token` header in `public/assets/js/api.js`.

### 2.4 Transport Security & Production Environment
- **HSTS**: `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload` header sent when HTTPS is detected.
- **Error Concealment**: When `APP_DEBUG=false`, `display_errors` and `display_startup_errors` are turned off (`ini_set('display_errors', '0')`). Stack traces and internal paths are never returned to clients.
- **Information Leak Prevention**: `expose_php = 0` and removal of `X-Powered-By` header prevent banner grabbing.
- **Cookie Security**: Session cookies enforce:
  - `httponly`: `true` (unreachable via JavaScript `document.cookie`).
  - `samesite`: `'Lax'` (mitigates CSRF via cross-site links).
  - `secure`: `true` on HTTPS or when `APP_ENV=production`.

### 2.5 Authentication & Password Policy
- **Hashing**: Passwords hashed using `password_hash($password, PASSWORD_BCRYPT)`.
- **Brute-Force Protection**: Every login attempt is tracked in `login_attempts`. Accounts are locked for 15 minutes after 5 consecutive failed attempts.
- **Generic Errors**: Failed authentication always returns "Invalid email or password" to prevent user enumeration.
- **Password Complexity Policy**:
  - Minimum 8 characters in length.
  - Mandatory combination of letters (`[A-Za-z]`) and numbers (`[0-9]`).
  - Blacklist rejection of common weak passwords (e.g., `password123`, `admin123`, `qwerty123`).
- **Password Reset**: Single-use tokens with 15-minute validity; only the SHA-256 hash of the token is saved in the database.

### 2.6 Sensitive Financial Data Encryption at Rest (PAN)
- **Encryption Algorithm**: Native OpenSSL `AES-256-GCM` with a 96-bit (12-byte) random initialization vector (IV) and a 128-bit (16-byte) authentication tag.
- **Key Storage**: 256-bit key configured via `APP_KEY` or `PAN_ENCRYPTION_KEY` in `.env`.
- **Database Schema**: `pan_no` column expanded to `VARCHAR(255)` to store the base64-encoded `IV + TAG + CIPHERTEXT` payload.
- **Masking on Display**: `Crypto::maskPan()` formats PANs as `******234F` (only the last 4 characters visible) across UI views, client profiles, lists, and API responses.
- **Audit Masking**: Activity logs and change diffs mask PAN numbers prior to persisting to `activity_log`.

### 2.7 Insecure Direct Object References (IDOR) & Scoping
- **Database Scoping**: Sales staff (`client.view_own`) are restricted to records where `assigned_to = :user_id`. Queries execute `findScoped` and `searchClients`, preventing any sales representative from viewing or mutating another user's client records.
- **Document Scoping**: Document downloads (`GET /api/clients/{id}/documents/{docId}`) verify both client ownership and document relationship.
- **Web Route Protection**: `GET /clients/{id}` checks permission and scoping before rendering the view; unauthorized access triggers an immediate 403 page.

### 2.8 Upload Hardening
- **Physical Isolation**: Uploads stored in `storage/uploads/clients/{client_id}/`, completely isolated from the web document root (`public/`).
- **Execution Prevention**:
  - `.htaccess` enforces `Require all denied` / `Deny from all`.
  - PHP execution engine disabled (`php_flag engine off`).
  - Script handlers stripped (`RemoveHandler .php .phtml .cgi .sh`).
- **File Validation**:
  - MIME type verified with `finfo_file(FILEINFO_MIME_TYPE)` (whitelisted: `application/pdf`, `image/jpeg`, `image/png`).
  - Whitelisted extensions (`pdf`, `jpg`, `jpeg`, `png`).
  - Max size limited to 5 MB per file.
  - Files renamed with cryptographically random identifiers (`bin2hex(random_bytes(16))`).

---

## 3. Digital Personal Data Protection (DPDP) Act, 2023 Compliance

### 3.1 Lawful Basis & Notice (Section 6)
- **Affirmative Consent**: Client registration enforces explicit consent before saving (`consent_given = 1`).
- **Timestamping**: Registration records exact ISO timestamp of consent (`consent_at`).

### 3.2 Right to Correction & Erasure (Section 12)
Data Principals (clients) have the right to request erasure of their personal data. When a legitimate request is received, an authorized administrator can trigger the Anonymize action.

#### Anonymization Workflow:
1. **Access Control**: Limited to administrators (`user.manage` permission).
2. **Purging Physical Documents**: All files in `storage/uploads/clients/{id}/` are permanently unlinked and deleted from storage.
3. **Document Metadata Redaction**: In `client_documents`, `stored_name` is emptied and `original_name` is updated to `[Redacted]`.
4. **PII Pseudonymization & Redaction**: The `clients` table record is overwritten:
   - `name`: `'Anonymized Client #' . $id`
   - `contact_person`: `NULL`
   - `email`: `anonymized_{id}_{random8}@deleted.local` (ensures uniqueness without retaining user identity)
   - `mobile`: `9900000000` (pseudonymized dummy)
   - `alt_mobile`, `gst_no`, `pan_no`, `website`, `tags`: `NULL`
   - `address_line1`: `[Redacted for Privacy]`
   - `city`, `state`: `[Redacted]`
   - `pincode`: `'000000'`
   - `notes`: `[Redacted pursuant to DPDP Act Section 12 erasure request]`
   - `status`: `'inactive'`
   - `deleted_at`: Timestamp of anonymization
5. **Audit Logging**: An immutable record of the deletion action is logged to `activity_log` (`action: dpdp_anonymize`) referencing only the client code and reason, with zero PII captured.

---

## 4. Security Incident Response & Audit Trail

- **System Log**: `storage/logs/crm.log` records application errors, warnings, and unhandled exceptions.
- **Security Log**: `storage/logs/security.log` records failed authentications, locked accounts, and privilege escalations.
- **Activity Log**: Database table `activity_log` records user mutations (create, update, delete, anonymize) with user ID, IP address, and user agent.
