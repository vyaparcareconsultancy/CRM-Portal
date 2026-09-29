-- Migration 013: Add missing performance indexes for client list, filtering, and dashboard queries
-- Based on query EXPLAIN analysis of searchClients(), getCountsByStatus(), getCountsByLeadSource()

-- 1. Index for state filtering
CREATE INDEX `idx_clients_state` ON `clients` (`state`);

-- 2. Composite index for general client list pagination (deleted_at + created_at)
-- Eliminates filesort for admin/manager listing queries: WHERE deleted_at IS NULL ORDER BY created_at DESC
CREATE INDEX `idx_clients_del_created` ON `clients` (`deleted_at`, `created_at`);

-- 3. Composite index for role-scoped client list pagination (deleted_at + assigned_to + created_at)
-- Eliminates filesort for sales-scoped queries: WHERE deleted_at IS NULL AND assigned_to = ? ORDER BY created_at DESC
CREATE INDEX `idx_clients_del_assigned_created` ON `clients` (`deleted_at`, `assigned_to`, `created_at`);

-- 4. Composite index for status aggregation & filtering (deleted_at + status)
-- Speeds up dashboard status breakdown and status dropdown filtering
CREATE INDEX `idx_clients_del_status` ON `clients` (`deleted_at`, `status`);

-- 5. Composite index for lead source aggregation & filtering (deleted_at + lead_source)
-- Speeds up dashboard lead source breakdown and lead source dropdown filtering
CREATE INDEX `idx_clients_del_lead_source` ON `clients` (`deleted_at`, `lead_source`);

-- 6. Composite index for follow-ups dashboard and tab queries
CREATE INDEX `idx_followups_del_user_status_due` ON `follow_ups` (`deleted_at`, `user_id`, `status`, `due_at`);
