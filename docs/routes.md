# Route Table — Tech-Tians CRM Portal

All routes are defined in [`config/routes.php`](../config/routes.php). The table below documents every registered route with its middleware stack.

## Legend

| Abbreviation | Middleware | Description |
|:---|:---|:---|
| **Auth** | `AuthMiddleware` | Requires active session (`user_id` set) |
| **CSRF** | `Csrf::validateRequest()` | Validated inside the controller method body on all POST/PUT/DELETE |
| **Perm** | `perm:<permission>` | Requires specific RBAC permission |
| **Rate** | `throttle:<hits>,<minutes>[,<key>]` | Rate limiting (per IP or per user) |

---

## Public Routes (No Authentication)

| Method | Path | Controller | Rate Limit | Notes |
|:---|:---|:---|:---|:---|
| `GET` | `/` | redirect → `/login` | — | Root redirect |
| `GET` | `/health` | `HealthController@check` | — | Health check (DB, disk, storage) |
| `GET` | `/login` | `AuthController@showLogin` | — | Login page |
| `GET` | `/reset-password` | `AuthController@showResetPassword` | — | Password reset page |
| `POST` | `/api/auth/login` | `AuthController@login` | `5/15min (login)` | CSRF ✓ |
| `POST` | `/api/auth/forgot` | `AuthController@forgotPassword` | `3/15min (forgot)` | CSRF ✓ |
| `POST` | `/api/auth/reset` | `AuthController@resetPassword` | `5/15min` | CSRF ✓ |

## Authenticated Routes

### Auth & Profile

| Method | Path | Controller | Middleware | CSRF | Notes |
|:---|:---|:---|:---|:---|:---|
| `POST` | `/api/auth/logout` | `AuthController@logout` | Auth | ✓ | Destroys session |
| `GET` | `/api/auth/me` | `AuthController@me` | Auth, `120/1min` | — | Current user profile |

### Dashboard

| Method | Path | Controller | Middleware | CSRF | Notes |
|:---|:---|:---|:---|:---|:---|
| `GET` | `/dashboard` | `DashboardController@index` | Auth | — | Dashboard page |
| `GET` | `/api/dashboard/stats` | `DashboardController@stats` | Auth, `120/1min` | — | Stats JSON (scoped by role) |

### Clients

| Method | Path | Controller | Middleware | CSRF | Notes |
|:---|:---|:---|:---|:---|:---|
| `GET` | `/clients` | View: `clients/index` | Auth | — | Client list page |
| `GET` | `/clients/create` | View: `clients/create` | Auth, `perm:client.create` | — | Registration form |
| `GET` | `/clients/{id}` | View: `clients/show` | Auth | — | Client profile page (IDOR checked in service) |
| `GET` | `/api/clients` | `ClientController@index` | Auth, `120/1min` | — | Paginated client list |
| `GET` | `/api/clients/export` | `ClientController@export` | Auth, `120/1min`, `10/60min (export)`, `perm:client.export` | — | XLSX/CSV export |
| `GET` | `/api/clients/{id}` | `ClientController@show` | Auth, `120/1min` | — | Client detail JSON |
| `POST` | `/api/clients` | `ClientController@store` | Auth, `120/1min`, `20/60min (client_create)`, `perm:client.create` | ✓ | Create client |
| `PUT` | `/api/clients/{id}` | `ClientController@update` | Auth, `120/1min`, `perm:client.edit` | ✓ | Update client |
| `DELETE` | `/api/clients/{id}` | `ClientController@destroy` | Auth, `120/1min`, `perm:client.delete` | ✓ | Soft-delete client |
| `POST` | `/api/clients/{id}/anonymize` | `ClientController@anonymize` | Auth, `120/1min`, `perm:user.manage` | ✓ | DPDP anonymization (admin) |

### Client Documents

| Method | Path | Controller | Middleware | CSRF | Notes |
|:---|:---|:---|:---|:---|:---|
| `POST` | `/api/clients/{id}/documents` | `ClientController@uploadDocument` | Auth, `120/1min`, `perm:client.edit` | ✓ | Upload attachment |
| `GET` | `/api/clients/{id}/documents/{docId}` | `ClientController@downloadDocument` | Auth, `120/1min` | — | Download file |
| `DELETE` | `/api/clients/{id}/documents/{docId}` | `ClientController@destroyDocument` | Auth, `120/1min`, `perm:client.edit` | ✓ | Delete attachment |

### Follow-ups

| Method | Path | Controller | Middleware | CSRF | Notes |
|:---|:---|:---|:---|:---|:---|
| `GET` | `/follow-ups` | View: `followups/index` | Auth | — | Follow-ups page |
| `GET` | `/followups` | View: `followups/index` | Auth | — | Alias for above |
| `GET` | `/api/followups` | `FollowUpController@index` | Auth, `120/1min` | — | List follow-ups |
| `GET` | `/api/followups/{id}` | `FollowUpController@show` | Auth, `120/1min` | — | Follow-up detail |
| `POST` | `/api/followups` | `FollowUpController@store` | Auth, `120/1min`, `perm:followup.manage` | ✓ | Create follow-up |
| `PUT` | `/api/followups/{id}` | `FollowUpController@update` | Auth, `120/1min`, `perm:followup.manage` | ✓ | Update follow-up |
| `DELETE` | `/api/followups/{id}` | `FollowUpController@destroy` | Auth, `120/1min`, `perm:followup.manage` | ✓ | Delete follow-up |
| `GET` | `/api/clients/{id}/followups` | `FollowUpController@clientFollowups` | Auth, `120/1min` | — | Follow-ups for a client |
| `POST` | `/api/clients/{id}/followups` | `FollowUpController@store` | Auth, `120/1min`, `perm:followup.manage` | ✓ | Create follow-up for client |

### Lookups & Staff

| Method | Path | Controller | Middleware | CSRF | Notes |
|:---|:---|:---|:---|:---|:---|
| `GET` | `/api/lookups` | `ClientController@lookups` | Auth, `120/1min` | — | States, industries, lead sources |
| `GET` | `/api/staff` | Inline closure | Auth, `120/1min` | — | Active staff list for assignment |

### User Administration (Admin Only)

| Method | Path | Controller | Middleware | CSRF | Notes |
|:---|:---|:---|:---|:---|:---|
| `GET` | `/users` | View: `users/index` | Auth, `perm:user.manage` | — | Users page |
| `GET` | `/api/users` | `UserController@index` | `120/1min`, `perm:user.manage` | — | List users |
| `POST` | `/api/users` | `UserController@store` | `120/1min`, `perm:user.manage` | ✓ | Create user |
| `PUT` | `/api/users/{id}` | `UserController@update` | `120/1min`, `perm:user.manage` | ✓ | Update user |

### System Logs (Admin Only)

| Method | Path | Controller | Middleware | CSRF | Notes |
|:---|:---|:---|:---|:---|:---|
| `GET` | `/admin/logs` | `LogController@index` | Auth, `perm:user.manage` | — | Log viewer page |
| `GET` | `/api/admin/logs` | `LogController@api` | Auth, `120/1min`, `perm:user.manage` | — | Log data JSON |

---

## Security Audit Notes

- **CSRF**: Enforced inside every `POST`, `PUT`, and `DELETE` controller method via `Csrf::validateRequest()`. Token sent as `X-CSRF-TOKEN` header (set by `api.js`) or `_csrf_token` form field.
- **Auth**: Every non-public API route passes through `AuthMiddleware`. The `perm:user.manage` middleware implicitly requires authentication.
- **IDOR**: Client endpoints verify ownership via `findScoped()` which checks `assigned_to` for non-admin roles. Follow-ups are similarly scoped.
- **Rate Limiting**: All API routes have `throttle:120,1` (120 req/min general). Sensitive endpoints have tighter limits: login (5/15min), forgot password (3/15min), export (10/hour), client create (20/hour).
