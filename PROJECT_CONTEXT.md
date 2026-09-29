# CRM Portal — Project Context
Goal: A CRM portal for managing clients, starting with a client registration form.
Stack: HTML5, CSS3, Bootstrap 5, vanilla JavaScript (fetch API), PHP 8.2+ (no framework, custom MVC), MySQL 8 (InnoDB, utf8mb4), Composer.
Local dev: XAMPP on Windows.
Libraries allowed: vlucas/phpdotenv, monolog/monolog, phpmailer/phpmailer, phpoffice/phpspreadsheet, phpunit/phpunit, phpstan/phpstan. Frontend: Bootstrap 5, DataTables, Chart.js (bundled locally).

Architecture rules:
- Single entry point public/index.php with a router. Only /public is web-accessible.
- Folders: app/Controllers, app/Services, app/Models, app/Middleware, app/Views, app/Helpers, config, database/migrations, database/seeds, storage/uploads, storage/logs, storage/cache, tests, public/assets.
- Controllers handle request/response only. Business rules go in Services. SQL goes only in Models.
- All DB access via PDO prepared statements. No string-concatenated SQL ever.
- API returns JSON: {"status":"success|error","data":...,"message":"...","errors":{field:msg}} with correct HTTP codes (200,201,400,401,403,404,422,429,500).
- Secrets only in .env (never committed). Provide .env.example.
- Soft delete via deleted_at. Every table has created_at, updated_at.
- Escape all output with htmlspecialchars. CSRF token on every state-changing request.
- Code style: PSR-12, strict_types=1, typed properties, small functions, comments only where logic is non-obvious.

Roles: admin (everything), manager (view/edit all clients, no user management), sales (only clients assigned to them).

Core entities: users, roles, permissions, role_permissions, clients, client_documents, follow_ups, activity_log, login_attempts, rate_limits.

Working rules for the AI agent:
- Do only the current step. Do not modify unrelated files.
- After each step, list files created/changed and give manual test steps.
- If something is ambiguous, ask before assuming.
