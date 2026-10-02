-- Migration 016: Roles and Permissions Matrix Setup & Sales to Counselor Migration

-- 1. Ensure Roles
INSERT IGNORE INTO `roles` (`name`, `label`) VALUES
('admin', 'Administrator'),
('manager', 'Manager'),
('counselor', 'Counselor'),
('accountant', 'Accountant'),
('trainer', 'Trainer');

-- 2. Migrate existing "sales" users to "counselor"
UPDATE `users`
SET `role_id` = (SELECT `id` FROM `roles` WHERE `name` = 'counselor' LIMIT 1)
WHERE `role_id` = (SELECT `id` FROM `roles` WHERE `name` = 'sales' LIMIT 1);

-- 3. Insert Permissions
INSERT IGNORE INTO `permissions` (`name`, `label`) VALUES
-- Clients & Tax
('client.create', 'Create Client'),
('client.view_all', 'View All Clients'),
('client.view_own', 'View Assigned Clients'),
('client.edit', 'Edit Client'),
('client.delete', 'Delete Client'),
('client.export', 'Export Clients'),
('client.view_tax', 'View Client Tax Data (PAN/GST)'),

-- Leads & Sources
('lead.view', 'View Leads'),
('lead.view_all', 'View All Leads'),
('lead.manage', 'Manage Leads'),
('lead.convert', 'Convert Leads'),
('lead_source.manage', 'Manage Lead Sources'),

-- Follow-ups
('followup.manage', 'Manage Follow-ups'),

-- Services
('service.manage', 'Manage Services Catalog'),
('client_service.manage', 'Manage Client Service Subscriptions'),

-- Billing & Payments
('invoice.manage', 'Manage Invoices'),
('payment.view', 'View Payments & Fees'),
('payment.record', 'Record Payments'),
('payment.manage', 'Manage/Edit Payments'),

-- Training & Academic
('student.manage', 'Manage Students'),
('student.view', 'View All Students'),
('student.view_assigned', 'View Assigned Batch Students'),
('batch.manage', 'Manage Batches'),
('batch.view_assigned', 'View Assigned Batches'),
('attendance.manage', 'Mark Attendance'),
('attendance.view', 'View Attendance'),
('progress.manage', 'Manage Student Progress'),

-- Reports
('report.view_financial', 'View Financial Reports'),
('report.view_leads', 'View Lead Reports'),
('report.view_academic', 'View Academic & Batch Reports'),

-- Reminders & Administration
('reminder.manage', 'Manage Reminders'),
('user.manage', 'Manage Users & Permissions'),
('system.settings', 'Manage System Settings');

-- 4. Map Permissions for Admin (All permissions)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`name` = 'admin';

-- 5. Map Permissions for Manager (All except user.manage and system.settings)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`name` = 'manager'
  AND p.`name` NOT IN ('user.manage', 'system.settings');

-- 6. Map Permissions for Counselor (Leads, follow-ups, own students/clients, convert, no payments edit)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`name` IN (
    'lead.view',
    'lead.manage',
    'lead.convert',
    'lead_source.manage',
    'followup.manage',
    'client.create',
    'client.view_own',
    'student.view',
    'student.manage',
    'batch.manage',
    'attendance.view',
    'report.view_leads'
)
WHERE r.`name` = 'counselor';

-- 7. Map Permissions for Accountant (Clients, services, payments, invoices, reports, reminders; no user management)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`name` IN (
    'client.view_all',
    'client.view_own',
    'client.edit',
    'client.export',
    'client.view_tax',
    'service.manage',
    'client_service.manage',
    'invoice.manage',
    'payment.view',
    'payment.record',
    'payment.manage',
    'student.view',
    'report.view_financial',
    'reminder.manage',
    'followup.manage'
)
WHERE r.`name` = 'accountant';

-- 8. Map Permissions for Trainer (Only assigned batches: students list, attendance, progress; no fees, no client data)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id`
FROM `roles` r
JOIN `permissions` p ON p.`name` IN (
    'batch.view_assigned',
    'student.view_assigned',
    'attendance.manage',
    'attendance.view',
    'progress.manage',
    'report.view_academic'
)
WHERE r.`name` = 'trainer';
