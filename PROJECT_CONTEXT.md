# CRM & ERP Portal — Project Context

## Business Domain
The CRM/ERP portal manages operations for two integrated business units:
1. **Tax & Compliance Services**: Professional compliance consultancy covering GST, ITR, Accounting, Tax Audit, Business Registrations (Private Limited, LLP, MSME), ROC Filings, TDS, and PF/ESI.
2. **Training Institute**: Vocational & professional academy offering certification courses, batch scheduling, student enrollments, attendance, and fee management.

## Stack & Architecture
- **Stack**: HTML5, CSS3, Bootstrap 5, vanilla JavaScript (Fetch API), PHP 8.2+ (custom MVC, no framework), MySQL 8 (InnoDB, utf8mb4), Composer.
- **Local Dev**: XAMPP on Windows / macOS CLI.
- **Allowed Libraries**: `vlucas/phpdotenv`, `monolog/monolog`, `phpmailer/phpmailer`, `phpoffice/phpspreadsheet`, `phpunit/phpunit`, `phpstan/phpstan`.
- **Frontend Assets**: Bootstrap 5, DataTables, Chart.js (bundled locally in `/public/assets`).

## Architecture Rules
- Single entry point `public/index.php` with a router. Only `/public` is web-accessible.
- Standard directory layout: `app/Controllers`, `app/Services`, `app/Models`, `app/Middleware`, `app/Views`, `app/Helpers`, `config`, `database/migrations`, `database/seeds`, `storage/uploads`, `storage/logs`, `storage/cache`, `tests`, `public/assets`.
- Controllers handle request/response only. Business rules live in Services. SQL queries live strictly in Models.
- All DB access via PDO prepared statements. Never use string-concatenated SQL.
- API returns standard JSON: `{"status":"success|error","data":...,"message":"...","errors":{field:msg}}` with appropriate HTTP status codes (200, 201, 400, 401, 403, 404, 422, 429, 500).
- Secrets live exclusively in `.env` (never committed). Maintain `.env.example`.
- Soft delete via `deleted_at`. Every table has `created_at` and `updated_at`.
- Escape all HTML output with `htmlspecialchars` (`e()` helper). CSRF token validated on every state-changing request.
- Code style: PSR-12, `strict_types=1`, typed properties, small functions, comments only where logic is non-obvious.

## Strict Backwards Compatibility Invariant
- **Non-Breaking Guarantee**: Every new feature, table, migration, or route MUST preserve 100% backwards compatibility with existing `clients`, `client_documents`, `follow_ups`, and `users` data and endpoints.
- Existing client code generation (`CL-YYYY-XXXX`), document storage paths, and follow-up schedules must continue functioning without regression.

## Modules
1. **Shared Contacts**: Centralized person/organization entity preventing duplicate data across leads, clients, and students.
2. **Lead Management & Lead Sources**: Multi-channel lead intake (tax service inquiries and course inquiries), scoring, assignment, and conversion pipelines.
3. **Follow-ups & Notes**: Scheduled interactions (calls, meetings, emails) and timestamped rich notes across leads, clients, and students.
4. **Client Management (Tax & Compliance)**: Client directory, PAN/GSTIN tracking, recurring and one-off compliance service engagements (`client_services`).
5. **Student Management & Training**: Course catalog, batch scheduling, student profiles, admissions/enrollments, and daily attendance tracking.
6. **Billing & Payments**: Invoicing for compliance engagements and student tuition fees, installment plans, payments recording, and receipt generation.
7. **Document Vault**: Client statutory documents (KYC, PAN, GST certificates), student certificates/ID proofs, with secure storage and role-based access.
8. **Reminders & Deadlines**: Automated statutory compliance deadline alerts (GST filing dates, ITR cutoffs, ROC due dates) and fee payment reminders.
9. **Omnichannel Messaging**: WhatsApp, SMS, and Email delivery via standardized message templates and delivery audit logs.
10. **Role-Based Dashboards & Reports**: Dedicated dashboards and tabular/exportable reports for Admin, Counselor, Accountant, and Trainer.
11. **Security & Compliance**: DPDP Act data privacy (PAN masking, data encryption at rest, right to erasure), audit trails (`activity_log`), brute force prevention, rate limiting.

## Staff Roles & Permissions
- **Admin**: Full system authority — user management, system settings, global financial visibility, all modules.
- **Counselor**: Lead intake and qualification, follow-ups, student inquiries, batch allocation, course enrollments.
- **Accountant**: Invoices generation, fee collection, payment verification, service subscription billing, financial reporting.
- **Trainer**: Assigned batches management, student roster access, daily attendance marking, course progress logs.
- *(Legacy roles `manager` and `sales` remain supported for backward compatibility)*.

### Key Permission Sets:
- `lead.manage`, `lead.view`, `lead.convert`
- `client.create`, `client.view_all`, `client.view_own`, `client.edit`, `client.delete`, `client.export`
- `service.manage`, `client_service.manage`
- `course.manage`, `batch.manage`, `student.manage`, `attendance.manage`, `attendance.view`
- `invoice.manage`, `payment.record`, `payment.view`
- `document.manage`, `document.view`
- `reminder.manage`, `message.send`, `template.manage`
- `report.view_financial`, `report.view_academic`, `report.view_leads`
- `user.manage`, `system.logs`

## Working Rules for the AI Agent
- Do only the current step. Do not modify unrelated files.
- Never write feature code or create migrations during design steps.
- After each step, list files created/changed and give manual verification steps.
- If something is ambiguous, ask before assuming.

