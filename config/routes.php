<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Core\Response;
use App\Middleware\AuthMiddleware;
use App\Router;

/** @var Router $router */

// Health check
$router->get('/health', [HealthController::class, 'check']);

// Authentication Pages
$router->get('/login', [AuthController::class, 'showLogin']);
$router->get('/reset-password', [AuthController::class, 'showResetPassword']);

// Authentication API Endpoints
$router->post('/api/auth/login', [AuthController::class, 'login'], ['throttle:5,15,login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->post('/api/auth/forgot', [AuthController::class, 'forgotPassword'], ['throttle:3,15,forgot']);
$router->post('/api/auth/reset', [AuthController::class, 'resetPassword'], ['throttle:5,15']);

// Protected API Profile Endpoint
$router->get('/api/auth/me', [AuthController::class, 'me'], ['throttle:120,1', AuthMiddleware::class]);

// Admin User Management Routes (Protected by perm:user.manage)
$router->get('/api/users', [\App\Controllers\UserController::class, 'index'], ['throttle:120,1', 'perm:user.manage']);
$router->post('/api/users', [\App\Controllers\UserController::class, 'store'], ['throttle:120,1', 'perm:user.manage']);
$router->put('/api/users/{id}', [\App\Controllers\UserController::class, 'update'], ['throttle:120,1', 'perm:user.manage']);
$router->delete('/api/users/{id}', [\App\Controllers\UserController::class, 'destroy'], ['throttle:120,1', 'perm:user.manage']);
$router->get('/api/roles', [\App\Controllers\UserController::class, 'roles'], ['throttle:120,1', 'perm:user.manage']);

// Client API Endpoints
$router->get('/api/clients/export', [\App\Controllers\ClientController::class, 'export'], ['throttle:120,1', 'throttle:10,60,export', AuthMiddleware::class, 'perm:client.export']);
$router->get('/api/clients', [\App\Controllers\ClientController::class, 'index'], ['throttle:120,1', AuthMiddleware::class, 'perm:client.view_all|client.view_own']);
$router->post('/api/clients', [\App\Controllers\ClientController::class, 'store'], ['throttle:120,1', 'throttle:20,60,client_create', AuthMiddleware::class, 'perm:client.create']);
$router->get('/api/clients/{id}', [\App\Controllers\ClientController::class, 'show'], ['throttle:120,1', AuthMiddleware::class, 'perm:client.view_all|client.view_own']);
$router->put('/api/clients/{id}', [\App\Controllers\ClientController::class, 'update'], ['throttle:120,1', AuthMiddleware::class, 'perm:client.edit']);
$router->delete('/api/clients/{id}', [\App\Controllers\ClientController::class, 'destroy'], ['throttle:120,1', AuthMiddleware::class, 'perm:client.delete']);
$router->post('/api/clients/{id}/anonymize', [\App\Controllers\ClientController::class, 'anonymize'], ['throttle:120,1', AuthMiddleware::class, 'perm:user.manage']);
$router->post('/api/clients/{id}/documents', [\App\Controllers\ClientController::class, 'uploadDocument'], ['throttle:120,1', AuthMiddleware::class, 'perm:client.edit']);
$router->get('/api/clients/{id}/documents/{docId}', [\App\Controllers\ClientController::class, 'downloadDocument'], ['throttle:120,1', AuthMiddleware::class, 'perm:client.view_all|client.view_own']);
$router->delete('/api/clients/{id}/documents/{docId}', [\App\Controllers\ClientController::class, 'destroyDocument'], ['throttle:120,1', AuthMiddleware::class, 'perm:client.edit']);
$router->get('/api/lookups', [\App\Controllers\ClientController::class, 'lookups'], ['throttle:120,1', AuthMiddleware::class]);

// Payment API Endpoints
$router->get('/api/payments', [\App\Controllers\PaymentController::class, 'index'], ['throttle:120,1', AuthMiddleware::class, 'perm:payment.view']);
$router->get('/api/payments/{id}', [\App\Controllers\PaymentController::class, 'show'], ['throttle:120,1', AuthMiddleware::class, 'perm:payment.view']);
$router->post('/api/payments', [\App\Controllers\PaymentController::class, 'store'], ['throttle:120,1', AuthMiddleware::class, 'perm:payment.record']);

// Lead API Endpoints
$router->get('/api/leads/lookups', [\App\Controllers\LeadController::class, 'lookups'], ['throttle:120,1', AuthMiddleware::class]);
$router->post('/api/leads/import', [\App\Controllers\LeadController::class, 'importCsv'], ['throttle:60,1', AuthMiddleware::class, 'perm:lead.manage']);
$router->get('/api/leads', [\App\Controllers\LeadController::class, 'index'], ['throttle:120,1', AuthMiddleware::class, 'perm:lead.view']);
$router->post('/api/leads', [\App\Controllers\LeadController::class, 'store'], ['throttle:120,1', AuthMiddleware::class, 'perm:lead.manage']);
$router->get('/api/leads/{id}', [\App\Controllers\LeadController::class, 'show'], ['throttle:120,1', AuthMiddleware::class, 'perm:lead.view']);
$router->put('/api/leads/{id}', [\App\Controllers\LeadController::class, 'update'], ['throttle:120,1', AuthMiddleware::class, 'perm:lead.manage']);
$router->post('/api/leads/{id}/status', [\App\Controllers\LeadController::class, 'updateStatus'], ['throttle:120,1', AuthMiddleware::class, 'perm:lead.manage']);
$router->post('/api/leads/{id}/convert', [\App\Controllers\LeadController::class, 'convert'], ['throttle:120,1', AuthMiddleware::class, 'perm:lead.convert']);
$router->get('/api/leads/{id}/followups', [\App\Controllers\FollowUpController::class, 'leadFollowups'], ['throttle:120,1', AuthMiddleware::class, 'perm:lead.view']);
$router->post('/api/leads/{id}/followups', [\App\Controllers\FollowUpController::class, 'store'], ['throttle:120,1', AuthMiddleware::class, 'perm:followup.manage']);

// Lead Source API Endpoints
$router->get('/api/lead-sources', [\App\Controllers\LeadSourceController::class, 'index'], ['throttle:120,1', AuthMiddleware::class]);
$router->post('/api/lead-sources', [\App\Controllers\LeadSourceController::class, 'store'], ['throttle:120,1', AuthMiddleware::class, 'perm:lead_source.manage']);
$router->put('/api/lead-sources/{id}', [\App\Controllers\LeadSourceController::class, 'update'], ['throttle:120,1', AuthMiddleware::class, 'perm:lead_source.manage']);

// Follow-up API Endpoints
$router->get('/api/followups', [\App\Controllers\FollowUpController::class, 'index'], ['throttle:120,1', AuthMiddleware::class]);
$router->post('/api/followups', [\App\Controllers\FollowUpController::class, 'store'], ['throttle:120,1', AuthMiddleware::class, 'perm:followup.manage']);
$router->get('/api/followups/{id}', [\App\Controllers\FollowUpController::class, 'show'], ['throttle:120,1', AuthMiddleware::class]);
$router->put('/api/followups/{id}', [\App\Controllers\FollowUpController::class, 'update'], ['throttle:120,1', AuthMiddleware::class, 'perm:followup.manage']);
$router->delete('/api/followups/{id}', [\App\Controllers\FollowUpController::class, 'destroy'], ['throttle:120,1', AuthMiddleware::class, 'perm:followup.manage']);
$router->get('/api/clients/{id}/followups', [\App\Controllers\FollowUpController::class, 'clientFollowups'], ['throttle:120,1', AuthMiddleware::class]);
$router->post('/api/clients/{id}/followups', [\App\Controllers\FollowUpController::class, 'store'], ['throttle:120,1', AuthMiddleware::class, 'perm:followup.manage']);

// Staff API Endpoint (for assigning clients)
$router->get('/api/staff', static function (): void {
    $userModel = new \App\Models\User();
    $users = $userModel->where(['is_active' => 1]);
    $data = array_map(static fn($u) => [
        'id' => (int)$u['id'],
        'name' => $u['name'],
        'email' => $u['email'],
    ], $users);
    Response::success($data);
}, ['throttle:120,1', AuthMiddleware::class]);

// Dashboard API & View
$router->get('/api/dashboard/stats', [\App\Controllers\DashboardController::class, 'stats'], ['throttle:120,1', AuthMiddleware::class]);
$router->get('/dashboard', [\App\Controllers\DashboardController::class, 'index'], [AuthMiddleware::class]);

// Leads Page
$router->get('/leads', static function (): void {
    $canManage = \App\Services\PermissionService::can('lead.manage');
    $canConvert = \App\Services\PermissionService::can('lead.convert');
    $canManageSources = \App\Services\PermissionService::can('lead_source.manage');
    $canViewAll = \App\Services\PermissionService::can('lead.view_all');
    $currentUserId = (int)\App\Core\Session::get('user_id');

    Response::view('leads/index', [
        'title' => 'Leads Pipeline — CRM Portal',
        'pageHeading' => 'Leads Pipeline',
        'canManage' => $canManage,
        'canConvert' => $canConvert,
        'canManageSources' => $canManageSources,
        'canViewAll' => $canViewAll,
        'currentUserId' => $currentUserId,
    ]);
}, [AuthMiddleware::class, 'perm:lead.view']);

$router->get('/clients', static function (): void {
    Response::view('clients/index', [
        'title' => 'Clients Directory — CRM Portal',
        'pageHeading' => 'Clients Directory',
    ]);
}, [AuthMiddleware::class, 'perm:client.view_all|client.view_own']);

$router->get('/clients/create', static function (): void {
    $isSales = !\App\Services\PermissionService::can('client.view_all');
    $currentUserId = \App\Core\Session::get('user_id');

    $userModel = new \App\Models\User();
    $staffUsers = $userModel->where(['is_active' => 1]);

    Response::view('clients/create', [
        'title' => 'Register Client — CRM Portal',
        'pageHeading' => 'New Client Registration',
        'isSales' => $isSales,
        'currentUserId' => $currentUserId,
        'staffUsers' => $staffUsers,
    ]);
}, [AuthMiddleware::class, 'perm:client.create']);

$router->get('/clients/{id}', static function (array $params): void {
    $rawId = $params['id'] ?? '';
    if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
        http_response_code(404);
        Response::view('errors/404', [
            'title' => 'Client Not Found — CRM Portal',
            'message' => 'The requested client could not be found.',
        ], null);
        return;
    }

    $id = (int)$rawId;
    $clientModel = new \App\Models\Client();
    $record = $clientModel->find($id);

    if (!$record) {
        http_response_code(404);
        Response::view('errors/404', [
            'title' => 'Client Not Found — CRM Portal',
            'message' => 'The requested client could not be found.',
        ], null);
        return;
    }

    $clientService = new \App\Services\ClientService();
    try {
        $client = $clientService->getClient($id);
        if (!$client) {
            http_response_code(403);
            Response::view('errors/403', [
                'title' => '403 Forbidden — CRM Portal',
                'message' => 'You do not have permission to access this client.'
            ], null);
            return;
        }
    } catch (\Throwable $e) {
        http_response_code(403);
        Response::view('errors/403', [
            'title' => '403 Forbidden — CRM Portal',
            'message' => 'You do not have permission to access this client.'
        ], null);
        return;
    }

    $isSales = !\App\Services\PermissionService::can('client.view_all');
    $currentUserId = (int)\App\Core\Session::get('user_id');

    Response::view('clients/show', [
        'title' => 'Client Profile — CRM Portal',
        'pageHeading' => 'Client Profile',
        'clientId' => $id,
        'isSales' => $isSales,
        'currentUserId' => $currentUserId,
    ]);
}, [AuthMiddleware::class, 'perm:client.view_all|client.view_own']);

$router->get('/clients/{id}/edit', static function (array $params): void {
    $rawId = $params['id'] ?? '';
    if (!ctype_digit((string)$rawId) || (int)$rawId <= 0) {
        http_response_code(404);
        Response::view('errors/404', [
            'title' => 'Client Not Found — CRM Portal',
            'message' => 'The requested client could not be found.',
        ], null);
        return;
    }

    $id = (int)$rawId;
    $clientModel = new \App\Models\Client();
    $record = $clientModel->find($id);

    if (!$record) {
        http_response_code(404);
        Response::view('errors/404', [
            'title' => 'Client Not Found — CRM Portal',
            'message' => 'The requested client could not be found.',
        ], null);
        return;
    }

    $clientService = new \App\Services\ClientService();
    try {
        $client = $clientService->getClient($id);
        if (!$client) {
            http_response_code(403);
            Response::view('errors/403', [
                'title' => '403 Forbidden — CRM Portal',
                'message' => 'You do not have permission to edit this client.'
            ], null);
            return;
        }
    } catch (\Throwable $e) {
        http_response_code(403);
        Response::view('errors/403', [
            'title' => '403 Forbidden — CRM Portal',
            'message' => 'You do not have permission to edit this client.'
        ], null);
        return;
    }

    $isSales = !\App\Services\PermissionService::can('client.view_all');
    $currentUserId = \App\Core\Session::get('user_id');

    $userModel = new \App\Models\User();
    $staffUsers = $userModel->where(['is_active' => 1]);

    Response::view('clients/edit', [
        'title' => 'Edit Client — CRM Portal',
        'pageHeading' => 'Edit Client',
        'clientId' => $id,
        'client' => $client,
        'isSales' => $isSales,
        'currentUserId' => $currentUserId,
        'staffUsers' => $staffUsers,
    ]);
}, [AuthMiddleware::class, 'perm:client.edit']);

$router->get('/follow-ups', static function (): void {
    Response::view('followups/index', [
        'title' => 'Follow-ups — CRM Portal',
        'pageHeading' => 'Follow-ups',
    ]);
}, [AuthMiddleware::class]);

$router->get('/followups', static function (): void {
    Response::view('followups/index', [
        'title' => 'Follow-ups — CRM Portal',
        'pageHeading' => 'Follow-ups',
    ]);
}, [AuthMiddleware::class]);

$router->get('/users', static function (): void {
    $userService = new \App\Services\UserService();
    Response::view('users/index', [
        'title' => 'Users Administration — CRM Portal',
        'pageHeading' => 'Users',
        'roles' => $userService->getRoles(),
        'currentUserId' => (int)\App\Core\Session::get('user_id'),
    ]);
}, [AuthMiddleware::class, 'perm:user.manage']);

// Admin Log Viewer
$router->get('/admin/logs', [\App\Controllers\LogController::class, 'index'], [AuthMiddleware::class, 'perm:user.manage']);
$router->get('/api/admin/logs', [\App\Controllers\LogController::class, 'api'], ['throttle:120,1', AuthMiddleware::class, 'perm:user.manage']);

// Roles & Permissions Matrix Admin Pages
$router->get('/admin/permissions', [\App\Controllers\PermissionController::class, 'index'], [AuthMiddleware::class, 'perm:user.manage']);
$router->get('/api/admin/permissions/matrix', [\App\Controllers\PermissionController::class, 'getMatrix'], ['throttle:120,1', AuthMiddleware::class, 'perm:user.manage']);
$router->post('/api/admin/permissions/toggle', [\App\Controllers\PermissionController::class, 'toggle'], ['throttle:120,1', AuthMiddleware::class, 'perm:user.manage']);

// Phase 3: Services Master Catalog (Admin/Manager)
$router->get('/services', [\App\Controllers\ServiceCatalogController::class, 'index'], [AuthMiddleware::class, 'perm:service.manage']);
$router->get('/api/services', [\App\Controllers\ServiceCatalogController::class, 'apiList'], ['throttle:120,1', AuthMiddleware::class]);
$router->post('/api/services', [\App\Controllers\ServiceCatalogController::class, 'apiStore'], ['throttle:120,1', AuthMiddleware::class, 'perm:service.manage']);
$router->put('/api/services/{id}', [\App\Controllers\ServiceCatalogController::class, 'apiUpdate'], ['throttle:120,1', AuthMiddleware::class, 'perm:service.manage']);
$router->post('/api/services/{id}', [\App\Controllers\ServiceCatalogController::class, 'apiUpdate'], ['throttle:120,1', AuthMiddleware::class, 'perm:service.manage']);
$router->delete('/api/services/{id}', [\App\Controllers\ServiceCatalogController::class, 'apiDelete'], ['throttle:120,1', AuthMiddleware::class, 'perm:service.manage']);
$router->post('/api/services/{id}/delete', [\App\Controllers\ServiceCatalogController::class, 'apiDelete'], ['throttle:120,1', AuthMiddleware::class, 'perm:service.manage']);

// Phase 3: Client Services Subscriptions
$router->get('/api/clients/{id}/services', [\App\Controllers\ClientComplianceController::class, 'listServices'], ['throttle:120,1', AuthMiddleware::class, 'perm:client.view_all|client.view_own']);
$router->post('/api/clients/{id}/services', [\App\Controllers\ClientComplianceController::class, 'storeService'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);
$router->put('/api/clients/{id}/services/{serviceId}', [\App\Controllers\ClientComplianceController::class, 'updateService'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);
$router->post('/api/clients/{id}/services/{serviceId}', [\App\Controllers\ClientComplianceController::class, 'updateService'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);
$router->delete('/api/clients/{id}/services/{serviceId}', [\App\Controllers\ClientComplianceController::class, 'deleteService'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);
$router->post('/api/clients/{id}/services/{serviceId}/delete', [\App\Controllers\ClientComplianceController::class, 'deleteService'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);

// Phase 3: Client Compliance Details & Encrypted Portal Credentials
$router->get('/api/clients/{id}/compliance', [\App\Controllers\ClientComplianceController::class, 'getCompliance'], ['throttle:120,1', AuthMiddleware::class, 'perm:client.view_all|client.view_own']);
$router->post('/api/clients/{id}/compliance', [\App\Controllers\ClientComplianceController::class, 'saveCompliance'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);
$router->post('/api/clients/{id}/compliance/reveal', [\App\Controllers\ClientComplianceController::class, 'revealCredentials'], ['throttle:60,1', AuthMiddleware::class, 'perm:client.view_tax']);

// Phase 3: Work Tracker per Service Period
$router->get('/api/clients/{id}/work-tracker', [\App\Controllers\ClientComplianceController::class, 'listWorkTracker'], ['throttle:120,1', AuthMiddleware::class, 'perm:client.view_all|client.view_own']);
$router->post('/api/clients/{id}/work-tracker', [\App\Controllers\ClientComplianceController::class, 'storeWorkTracker'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);
$router->put('/api/clients/{id}/work-tracker/{trackerId}', [\App\Controllers\ClientComplianceController::class, 'updateWorkTracker'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);
$router->post('/api/clients/{id}/work-tracker/{trackerId}', [\App\Controllers\ClientComplianceController::class, 'updateWorkTracker'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);
$router->delete('/api/clients/{id}/work-tracker/{trackerId}', [\App\Controllers\ClientComplianceController::class, 'deleteWorkTracker'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);
$router->post('/api/clients/{id}/work-tracker/{trackerId}/delete', [\App\Controllers\ClientComplianceController::class, 'deleteWorkTracker'], ['throttle:120,1', AuthMiddleware::class, 'perm:client_service.manage|client.edit']);

// Root redirect to dashboard or login
$router->get('/', static function (): void {
    Response::redirect('/login');
});
