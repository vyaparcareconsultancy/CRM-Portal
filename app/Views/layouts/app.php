<?php
$userName = \App\Core\Session::get('user_name') ?? 'User';
$userEmail = \App\Core\Session::get('user_email') ?? '';
$userRole = \App\Services\PermissionService::getRole() ?? 'Staff';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(\App\Helpers\Csrf::getToken()) ?>">
    <title><?= e($title ?? 'CRM Portal') ?></title>

    <!-- Local Vendor CSS (No CDN dependency) -->
    <link href="<?= asset('/assets/vendor/bootstrap/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="<?= asset('/assets/vendor/datatables/datatables.min.css') ?>" rel="stylesheet">

    <!-- Custom App CSS -->
    <link href="<?= asset('/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>
    <div id="topProgressBar"></div>

    <div class="app-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="app-sidebar" id="appSidebar">
            <div class="sidebar-header">
                <a href="/dashboard" class="sidebar-brand">
                    <span>Tech-Tians</span>
                    <span class="badge-crm">CRM</span>
                </a>
            </div>

            <ul class="sidebar-menu">
                <li class="menu-label">Main</li>
                <li>
                    <a href="/dashboard" class="sidebar-link <?= $currentPath === '/dashboard' ? 'active' : '' ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        <span>Dashboard</span>
                    </a>
                </li>

                <?php if (can('lead.view')): ?>
                <li class="menu-label">Leads</li>
                <li>
                    <a href="/leads" class="sidebar-link <?= $currentPath === '/leads' ? 'active' : '' ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        <span>Leads Pipeline</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (can('client.view_all') || can('client.view_own')): ?>
                <li class="menu-label">Clients</li>
                <li>
                    <a href="/clients" class="sidebar-link <?= $currentPath === '/clients' ? 'active' : '' ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Clients</span>
                    </a>
                </li>
                <?php if (can('client.create')): ?>
                <li>
                    <a href="/clients/create" class="sidebar-link <?= $currentPath === '/clients/create' ? 'active' : '' ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        <span>Add Client</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>

                <?php if (can('followup.manage')): ?>
                <li class="menu-label">Activity</li>
                <li>
                    <a href="/follow-ups" class="sidebar-link <?= $currentPath === '/follow-ups' ? 'active' : '' ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Follow-ups</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (can('service.manage')): ?>
                <li class="menu-label">Services & Catalog</li>
                <li>
                    <a href="/services" class="sidebar-link <?= ($currentPath ?? '') === '/services' ? 'active' : '' ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        <span>Services Master</span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (can('user.manage')): ?>
                <li class="menu-label">Administration</li>
                <li>
                    <a href="/users" class="sidebar-link <?= ($currentPath ?? '') === '/users' ? 'active' : '' ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                        <span>Users</span>
                    </a>
                </li>
                <li>
                    <a href="/admin/permissions" class="sidebar-link <?= ($currentPath ?? '') === '/admin/permissions' ? 'active' : '' ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span>Permissions Matrix</span>
                    </a>
                </li>
                <li>
                    <a href="/admin/logs" class="sidebar-link <?= ($currentPath ?? '') === '/admin/logs' ? 'active' : '' ?>">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        <span>System Logs</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="sidebar-footer">
                <button type="button" class="btn btn-outline-light w-100 btn-sm d-flex align-items-center justify-content-center gap-2" id="sidebarLogoutBtn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    <span>Sign Out</span>
                </button>
            </div>
        </aside>

        <!-- Mobile Backdrop -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <!-- Main Content Column -->
        <div class="app-main">
            <!-- Top Navigation Bar -->
            <header class="app-topbar">
                <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggleBtn" aria-label="Toggle sidebar">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <div class="d-none d-lg-block fw-semibold text-secondary">
                    <?= e($pageHeading ?? ($title ?? 'Portal')) ?>
                </div>

                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr($userName, 0, 1)) ?>
                    </div>
                    <div class="user-info d-none d-sm-block">
                        <div class="user-name"><?= e($userName) ?></div>
                        <div class="user-role badge bg-secondary-subtle text-secondary border"><?= e($userRole) ?></div>
                    </div>
                </div>
            </header>

            <!-- Dynamic Page Content -->
            <main class="app-content">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>

    <!-- Local Vendor JS (No CDN dependency) -->
    <script src="<?= asset('/assets/vendor/jquery/jquery.min.js') ?>"></script>
    <script src="<?= asset('/assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset('/assets/vendor/datatables/datatables.min.js') ?>"></script>
    <script src="<?= asset('/assets/vendor/chartjs/chart.umd.min.js') ?>"></script>

    <!-- App Core Scripts -->
    <script src="<?= asset('/assets/js/api.js') ?>"></script>
    <script src="<?= asset('/assets/js/ui.js') ?>"></script>

    <!-- Page Specific Scripts -->
    <?php if ($currentPath === '/dashboard' || $currentPath === '/'): ?>
    <script src="<?= asset('/assets/js/dashboard.js') ?>"></script>
    <?php elseif ($currentPath === '/leads'): ?>
    <script src="<?= asset('/assets/js/leads.js') ?>"></script>
    <?php elseif ($currentPath === '/clients'): ?>
    <script src="<?= asset('/assets/js/clients.js') ?>"></script>
    <?php elseif (preg_match('#^/clients/\d+$#', $currentPath)): ?>
    <script src="<?= asset('/assets/js/client-profile.js') ?>"></script>
    <?php elseif (preg_match('#^/clients/\d+/edit$#', $currentPath)): ?>
    <script src="<?= asset('/assets/js/client-edit.js') ?>"></script>
    <?php elseif ($currentPath === '/clients/create'): ?>
    <script src="<?= asset('/assets/js/client-form.js') ?>"></script>
    <?php elseif ($currentPath === '/users'): ?>
    <script src="<?= asset('/assets/js/users.js') ?>"></script>
    <?php endif; ?>
    <?php if (!empty($pageScript)): ?>
    <script src="<?= asset($pageScript) ?>"></script>
    <?php endif; ?>
    <?= $pageScripts ?? '' ?>

    <?= \App\Services\SentryService::renderBrowserScript() ?>

</body>
</html>
