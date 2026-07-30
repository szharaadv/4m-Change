-- ============================================================
-- 4M Change Management System
-- Fresh-install schema — matches the live application schema
-- as of migration_001 (2026-07-13). If you already have a
-- database from an older install, DO NOT re-run this file —
-- use migrations/migration_001_schema_sync_and_i18n.sql instead.
-- Run this file in phpMyAdmin > SQL tab (fresh installs only).
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_4m_change`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `db_4m_change`;

-- ============================================================
-- TABLE: USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`               INT NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(100) NOT NULL,
  `email`            VARCHAR(100) NULL,
  `username`         VARCHAR(50)  NOT NULL,
  `password`         VARCHAR(255) NOT NULL,
  `role`             ENUM('superadmin','admin','user') NOT NULL DEFAULT 'user',
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password_token`   VARCHAR(100) NULL,
  `token_expires_at` DATETIME NULL,
  `is_active`        TINYINT(1) NOT NULL DEFAULT 1,
  `department`       VARCHAR(100) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: CHANGE REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `change_requests` (
  `id`                  INT NOT NULL AUTO_INCREMENT,
  `change_no`           VARCHAR(30)  NOT NULL,
  `category_4m`         ENUM('Man','Material','Method','Machine') NOT NULL,
  `pic_id`              INT NOT NULL,
  `part_no`             VARCHAR(100) NULL,
  `implement_location`  VARCHAR(150) NOT NULL,
  `part_name`           VARCHAR(150) NOT NULL,
  `model`               VARCHAR(150) NOT NULL,
  `start_lot_serial`    VARCHAR(100) NOT NULL,
  `change_date`         DATE NULL,
  `change_item`         TEXT NOT NULL,
  `action_plan`         TEXT NOT NULL,
  `before_desc`         TEXT NULL,
  `after_desc`          TEXT NULL,
  `judge_status`        ENUM('OK','NG','Pending') NOT NULL DEFAULT 'Pending',
  `confirm_customer`    ENUM('Need','Not Need') NOT NULL DEFAULT 'Not Need',
  `evidence_note`       TEXT NULL,
  `rejection_note`      TEXT NULL,
  `rejected_by`         INT NULL,
  `rejected_at`         DATETIME NULL,
  `workflow_status`     ENUM('Draft','Submitted','Manager Approved','QC Approved','Closed','Rejected') NOT NULL DEFAULT 'Draft',
  `created_by`          INT NOT NULL,
  `updated_by`          INT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_no` (`change_no`),
  KEY `idx_pic` (`pic_id`),
  KEY `idx_status` (`workflow_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: CHANGE PHOTOS (before/after)
-- ============================================================
CREATE TABLE IF NOT EXISTS `change_photos` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `change_request_id` INT NOT NULL,
  `photo_type`        ENUM('before','after') NOT NULL,
  `file_name`         VARCHAR(255) NOT NULL,
  `file_path`         VARCHAR(255) NOT NULL,
  `uploaded_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr` (`change_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: CHANGE ATTACHMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `change_attachments` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `change_request_id` INT NOT NULL,
  `file_name`         VARCHAR(255) NOT NULL,
  `file_path`         VARCHAR(255) NOT NULL,
  `file_type`         VARCHAR(50)  NULL,
  `uploaded_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr` (`change_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: CHANGE APPROVALS
-- ============================================================
CREATE TABLE IF NOT EXISTS `change_approvals` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `change_request_id` INT NOT NULL,
  `step`              ENUM('manager','qc','qc_final') NOT NULL,
  `approver_id`       INT NOT NULL,
  `status`            ENUM('Approved','Rejected') NOT NULL,
  `note`              TEXT NULL,
  `acted_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr` (`change_request_id`),
  KEY `idx_approver` (`approver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: CHANGE HISTORIES
-- ============================================================
CREATE TABLE IF NOT EXISTS `change_histories` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `change_request_id` INT NOT NULL,
  `action_type`       ENUM('CREATE','UPDATE','SUBMIT','RESUBMIT','APPROVAL','REJECTION','CLOSED') NOT NULL DEFAULT 'CREATE',
  `action_note`       TEXT NULL,
  `action_by`         INT NOT NULL,
  `action_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr` (`change_request_id`),
  KEY `idx_user` (`action_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: DEPARTMENT → MANAGER ROUTING
-- ============================================================
CREATE TABLE IF NOT EXISTS `department_managers` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `department` VARCHAR(100) NOT NULL,
  `manager_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dept_user` (`department`,`manager_id`),
  KEY `user_id` (`manager_id`),
  CONSTRAINT `department_managers_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: DEPARTMENT → QC ROUTING
-- ============================================================
CREATE TABLE IF NOT EXISTS `department_qc` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `department` VARCHAR(100) NOT NULL,
  `qc_id`      INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dept_qc` (`department`,`qc_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: ROLE PERMISSIONS (configurable RBAC matrix)
-- One row per GRANTED (role, permission). The superadmin role
-- is never stored here — it always has every permission.
-- ============================================================
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `role`       VARCHAR(50)  NOT NULL,
  `permission` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permission` (`role`, `permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE: AUDIT LOG
-- ============================================================
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

-- ============================================================
-- SEED: DEFAULT USERS
-- Passwords are hashed with password_hash() — all four demo
-- accounts below use the password: admin123
-- ============================================================
INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`, `is_active`) VALUES
  ('Super Admin',  'superadmin', 'superadmin@example.com', '$2y$10$Ek6eo2.HcrkP088fbV42GOqJ6i67aNJKJiH33mj7ixIsmF6NOTo/i', 'superadmin', 1),
  ('Admin Demo',   'admin',      'admin@example.com',      '$2y$10$Ek6eo2.HcrkP088fbV42GOqJ6i67aNJKJiH33mj7ixIsmF6NOTo/i', 'admin',      1),
  ('User Demo',    'user',       'user@example.com',       '$2y$10$Ek6eo2.HcrkP088fbV42GOqJ6i67aNJKJiH33mj7ixIsmF6NOTo/i', 'user',       1);

-- ============================================================
-- SEED: DEFAULT ROLE PERMISSIONS
-- admin = full operational + system screens.
-- user  = operational only. The superadmin (not listed) always
-- has every permission and manages this matrix in the app.
-- ============================================================
INSERT INTO `role_permissions` (`role`, `permission`) VALUES
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
-- DONE — no demo change-request data seeded on fresh installs.
-- Log in as 'superadmin' (password: admin123) to manage roles.
-- ============================================================
