-- ============================================================
-- 4M Change Management System — Migration 002
-- Consolidate roles to THREE (superadmin, admin, user) and add
-- a configurable permission matrix the superadmin can control.
--
-- Generated: 2026-07-30
--
-- HOW TO RUN
--   phpMyAdmin : open db_4m_change > SQL tab > paste this file > Go.
--   CLI        : mysql -u <user> -p db_4m_change < migration_002_three_roles_and_permissions.sql
--
-- SAFE TO RE-RUN: role_permissions uses INSERT IGNORE, the role
-- back-fill only touches legacy roles, and the ENUM change is
-- idempotent.
--
-- ALWAYS BACK UP THE DATABASE BEFORE RUNNING ON A LIVE SERVER:
--   mysqldump -u <user> -p db_4m_change > backup_before_migration_002.sql
-- ============================================================

USE `db_4m_change`;

-- ------------------------------------------------------------
-- 1. Fold the legacy approval roles into the new 'user' role.
--    manager / qc / qc_prod all become plain 'user'. Who does
--    the Manager vs QC approval step is now driven purely by the
--    department routing tables + the configurable permission
--    matrix, not by the role name.
--    (Temporarily widen the ENUM so the UPDATE is always valid.)
-- ------------------------------------------------------------
ALTER TABLE `users`
  MODIFY COLUMN `role`
  ENUM('superadmin','admin','user','qc_prod','manager','qc') NOT NULL DEFAULT 'user';

UPDATE `users` SET `role` = 'user'
  WHERE `role` IN ('manager', 'qc', 'qc_prod');

-- Now lock the ENUM down to the final three roles.
ALTER TABLE `users`
  MODIFY COLUMN `role`
  ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user';

-- ------------------------------------------------------------
-- 2. Permission matrix table.
--    One row per (role, permission) that is GRANTED. Absence of
--    a row means the role does NOT have that permission.
--    The superadmin is never stored here — it bypasses the table.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `role`       VARCHAR(50)  NOT NULL,
  `permission` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permission` (`role`, `permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. Seed sensible default permissions (safe to re-run).
--    admin : full operational + system screens.
--    user  : operational only (create / edit / approve / export).
--    The superadmin can change all of this in Settings > Roles.
-- ------------------------------------------------------------
INSERT IGNORE INTO `role_permissions` (`role`, `permission`) VALUES
  ('admin', 'changes.view'),
  ('admin', 'changes.create'),
  ('admin', 'changes.edit'),
  ('admin', 'changes.approve_manager'),
  ('admin', 'changes.approve_qc'),
  ('admin', 'changes.export'),
  ('admin', 'users.manage'),
  ('admin', 'routing.manage'),
  ('admin', 'audit.view'),
  ('user',  'changes.view'),
  ('user',  'changes.create'),
  ('user',  'changes.edit'),
  ('user',  'changes.approve_manager'),
  ('user',  'changes.approve_qc'),
  ('user',  'changes.export');

-- ============================================================
-- DONE. Make sure at least one account has role 'superadmin':
--   UPDATE `users` SET `role` = 'superadmin' WHERE `username` = 'admin';
-- ============================================================
