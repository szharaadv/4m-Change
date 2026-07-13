-- ============================================================
-- 4M Change Management System — Migration 001
-- Sync production schema with current codebase + translate
-- legacy Indonesian text (history/audit log) to English.
--
-- Generated: 2026-07-13
--
-- HOW TO RUN
--   phpMyAdmin : open your db_4m_change database > SQL tab > paste
--                this whole file > Go.
--   CLI        : mysql -u <user> -p db_4m_change < migration_001_schema_sync_and_i18n.sql
--
-- SAFE TO RE-RUN: every statement is guarded (IF NOT EXISTS / REPLACE),
-- so running this twice on the same database does nothing the second time.
--
-- REQUIRES: MariaDB 10.0.2+ or MySQL 8.0.29+ (for "ADD COLUMN IF NOT EXISTS").
-- If your server runs an older plain MySQL, remove the "IF NOT EXISTS"
-- clauses below and run only the ALTERs that actually apply — check first
-- with: SHOW COLUMNS FROM users; / SHOW COLUMNS FROM change_requests;
--
-- ALWAYS BACK UP THE DATABASE BEFORE RUNNING THIS ON A LIVE SERVER:
--   mysqldump -u <user> -p db_4m_change > backup_before_migration_001.sql
-- ============================================================

USE `db_4m_change`;

-- ------------------------------------------------------------
-- 1. USERS — add columns needed by login/reset-password/routing
-- ------------------------------------------------------------
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `email` VARCHAR(100) NULL AFTER `name`,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
  ADD COLUMN IF NOT EXISTS `password_token` VARCHAR(100) NULL AFTER `updated_at`,
  ADD COLUMN IF NOT EXISTS `token_expires_at` DATETIME NULL AFTER `password_token`,
  ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `token_expires_at`,
  ADD COLUMN IF NOT EXISTS `department` VARCHAR(100) NULL AFTER `is_active`;

-- Add the 'superadmin' role (Users/Routing/Audit Log are now superadmin-only)
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('superadmin','admin','qc_prod','manager','qc') NOT NULL DEFAULT 'admin';

-- ------------------------------------------------------------
-- 2. CHANGE_REQUESTS — historical-upload feature needs change_date
-- ------------------------------------------------------------
ALTER TABLE `change_requests`
  ADD COLUMN IF NOT EXISTS `change_date` DATE NULL AFTER `start_lot_serial`;

-- ------------------------------------------------------------
-- 3. DEPARTMENT ROUTING TABLES — manager/QC approval routing
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `department_managers` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `department` VARCHAR(100) NOT NULL,
  `manager_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dept_user` (`department`,`manager_id`),
  KEY `user_id` (`manager_id`),
  CONSTRAINT `department_managers_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `department_qc` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `department` VARCHAR(100) NOT NULL,
  `qc_id`      INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dept_qc` (`department`,`qc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. AUDIT LOG TABLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT NULL,
  `username`    VARCHAR(100) NULL,
  `role`        VARCHAR(50) NULL,
  `action`      VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `ip_address`  VARCHAR(45) NULL,
  `user_agent`  TEXT NULL,
  `pc_name`     VARCHAR(255) NULL,
  `url`         VARCHAR(500) NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. TRANSLATE LEGACY INDONESIAN TEXT TO ENGLISH
--    (safe to re-run: REPLACE on already-translated text is a no-op)
-- ------------------------------------------------------------

-- change_histories.action_note
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Change request dibuat — status:', 'Change request created — status:');
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Change request dibuat', 'Change request created');
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Submitted untuk approval', 'Submitted for approval');
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Disetujui oleh Manager Department', 'Approved by Manager Department');
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Ditolak oleh Manager Department', 'Rejected by Manager Department');
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Disetujui oleh QC', 'Approved by QC');
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Ditolak oleh QC', 'Rejected by QC');
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Ditolak oleh Manager | Alasan:', 'Rejected by Manager | Reason:');
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Resubmit setelah revisi', 'Resubmitted after revision');
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Data diperbarui setelah rejection', 'Data updated after rejection');
UPDATE `change_histories` SET action_note = REPLACE(action_note, 'Final submit oleh QC — permohonan ditutup', 'Final submit by QC — request closed');

-- audit_logs.description
UPDATE `audit_logs` SET description = REPLACE(description, 'Buat draft change request', 'Created draft change request');
UPDATE `audit_logs` SET description = REPLACE(description, 'Tambah user baru:', 'Added new user:');
UPDATE `audit_logs` SET description = REPLACE(description, 'Hapus user:', 'Deleted user:');
UPDATE `audit_logs` SET description = REPLACE(description, 'Login berhasil', 'Login successful');
UPDATE `audit_logs` SET description = REPLACE(description, 'Login gagal', 'Login failed');
UPDATE `audit_logs` SET description = REPLACE(description, 'Update kategori manager', 'Updated manager category');
UPDATE `audit_logs` SET description = REPLACE(description, 'set password pertama kali & akun aktif', 'set password for the first time & account activated');
UPDATE `audit_logs` SET description = REPLACE(description, '— termasuk reset password', '— including password reset');
UPDATE `audit_logs` SET description = REPLACE(description, 'berhasil, ', 'succeeded, ');
UPDATE `audit_logs` SET description = REPLACE(description, ' gagal', ' failed');

-- ------------------------------------------------------------
-- 6. NOT included on purpose — do this manually, per server:
--    - Promoting a user to 'superadmin' (pick who owns Users/Routing/Audit):
--        UPDATE `users` SET `role` = 'superadmin' WHERE `username` = 'your_admin_username';
--    - Assigning department routing (managers/QC per department) —
--      use the Routing screen in the app after this migration runs.
-- ============================================================
