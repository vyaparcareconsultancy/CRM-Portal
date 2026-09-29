# CRM Portal — Entity Relationship (ER) Diagram & Database Architecture

## Overview

The database is built on **MySQL 8 (InnoDB)** using the `utf8mb4_unicode_ci` collation. It provides:
- Role-Based Access Control (RBAC) via `roles`, `permissions`, and `role_permissions`.
- Client and Document management with audit tracking and soft-deletes (`deleted_at`).
- Sales workflow tracking through scheduled `follow_ups`.
- Security and audit log enforcement through `activity_log`, `login_attempts`, and IP/Route-level `rate_limits`.

---

## Mermaid ER Diagram

```mermaid
erDiagram
    roles {
        bigint id PK
        varchar name UK "e.g. admin, manager, sales"
        varchar label "Human-readable label"
        datetime created_at
        datetime updated_at
    }

    permissions {
        bigint id PK
        varchar name UK "e.g. client.create, user.manage"
        varchar label "Human-readable label"
        datetime created_at
        datetime updated_at
    }

    role_permissions {
        bigint role_id PK,FK
        bigint permission_id PK,FK
        datetime created_at
    }

    users {
        bigint id PK
        bigint role_id FK
        varchar name
        varchar email UK
        varchar mobile
        varchar password_hash
        tinyint is_active "1=active, 0=inactive"
        datetime last_login_at
        int failed_attempts
        datetime locked_until
        datetime created_at
        datetime updated_at
        datetime deleted_at "Soft delete"
    }

    clients {
        bigint id PK
        varchar client_code UK
        enum client_type "individual, company"
        varchar name
        varchar contact_person
        varchar email UK
        varchar mobile UK
        varchar alt_mobile
        varchar gst_no UK "Nullable"
        varchar pan_no
        varchar industry
        varchar company_size
        varchar website
        varchar address_line1
        varchar address_line2
        varchar city
        varchar state
        varchar pincode
        varchar country "Default: India"
        varchar lead_source
        bigint assigned_to FK "Assigned sales rep"
        enum status "new, active, inactive"
        text tags
        text notes
        tinyint consent_given "GDPR/DPDP consent flag"
        datetime consent_at
        bigint created_by FK "Creator user"
        datetime created_at
        datetime updated_at
        datetime deleted_at "Soft delete"
    }

    client_documents {
        bigint id PK
        bigint client_id FK
        varchar original_name
        varchar stored_name
        varchar mime_type
        bigint size_bytes
        bigint uploaded_by FK
        datetime created_at
        datetime updated_at
        datetime deleted_at "Soft delete"
    }

    follow_ups {
        bigint id PK
        bigint client_id FK
        bigint user_id FK "Sales rep handling follow up"
        datetime due_at
        enum type "call, meeting, email"
        text notes
        enum status "pending, done, missed"
        datetime created_at
        datetime updated_at
        datetime deleted_at "Soft delete"
    }

    activity_log {
        bigint id PK
        bigint user_id FK "Nullable"
        varchar entity_type "e.g. client, user"
        bigint entity_id
        varchar action "e.g. created, updated, deleted"
        json old_values "Pre-modification state"
        json new_values "Post-modification state"
        varchar ip_address
        varchar user_agent
        datetime created_at
    }

    login_attempts {
        bigint id PK
        varchar email
        varchar ip_address
        tinyint success "1=success, 0=fail"
        datetime created_at
    }

    rate_limits {
        varchar key PK "e.g. login:127.0.0.1"
        int hits "Request counter"
        datetime reset_at "Window expiry"
        datetime created_at
        datetime updated_at
    }

    password_resets {
        bigint id PK
        varchar email
        varchar token_hash "SHA-256 hash"
        datetime expires_at "15-minute validity"
        datetime created_at
    }

    roles ||--o{ users : "assigns"
    roles ||--|{ role_permissions : "contains"
    permissions ||--|{ role_permissions : "grants"
    users ||--o{ clients : "assigned_to"
    users ||--o{ clients : "created_by"
    clients ||--o{ client_documents : "owns"
    users ||--o{ client_documents : "uploaded_by"
    clients ||--o{ follow_ups : "receives"
    users ||--o{ follow_ups : "conducts"
    users |o--o{ activity_log : "triggers"
```

---

## Table Summary & Indexes

| Table | Soft Delete (`deleted_at`) | Indexes & Constraints |
| :--- | :--- | :--- |
| `roles` | No | `PRIMARY KEY (id)`, `UNIQUE (name)` |
| `permissions` | No | `PRIMARY KEY (id)`, `UNIQUE (name)` |
| `role_permissions` | No | `PRIMARY KEY (role_id, permission_id)`, `FK (role_id)`, `FK (permission_id)` |
| `users` | Yes | `PRIMARY KEY (id)`, `UNIQUE (email)`, `INDEX (role_id)`, `INDEX (deleted_at)` |
| `clients` | Yes | `PRIMARY KEY (id)`, `UNIQUE (client_code)`, `UNIQUE (email)`, `UNIQUE (mobile)`, `UNIQUE (gst_no)`, `INDEX (status)`, `INDEX (assigned_to)`, `INDEX (created_at)`, `INDEX (city)`, `INDEX (deleted_at)` |
| `client_documents`| Yes | `PRIMARY KEY (id)`, `INDEX (client_id)`, `INDEX (uploaded_by)`, `INDEX (deleted_at)` |
| `follow_ups` | Yes | `PRIMARY KEY (id)`, `INDEX (due_at, status)`, `INDEX (client_id)`, `INDEX (user_id)`, `INDEX (deleted_at)` |
| `activity_log` | No | `PRIMARY KEY (id)`, `INDEX (entity_type, entity_id)`, `INDEX (user_id)`, `INDEX (created_at)` |
| `login_attempts` | No | `PRIMARY KEY (id)`, `INDEX (email)`, `INDEX (ip_address, created_at)` |
| `rate_limits` | No | `PRIMARY KEY (key)`, `INDEX (reset_at)` |
| `password_resets` | No | `PRIMARY KEY (id)`, `INDEX (email)`, `INDEX (token_hash)` |

---

## Migration & Seeding Commands

### 1. Run Pending Migrations
Runs all unapplied SQL migration files in `database/migrations/` sequentially and records them in the `migrations` table:
```bash
php database/migrate.php
```

### 2. Seed Default Roles, Permissions, and Admin User
Inserts initial RBAC roles (`admin`, `manager`, `sales`), permissions mapping, and creates/updates the initial administrator account from `.env`:
```bash
php database/seeds/seed.php
```

### 3. Verification Queries (MySQL CLI / phpMyAdmin)
```sql
USE crm_db;

-- Verify migration history
SELECT * FROM migrations ORDER BY id ASC;

-- Verify seeded roles & permissions
SELECT r.name AS role, p.name AS permission 
FROM roles r
JOIN role_permissions rp ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
ORDER BY r.name, p.name;

-- Verify default admin user
SELECT id, name, email, role_id, is_active FROM users;
```
