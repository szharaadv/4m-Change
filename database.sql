-- ============================================================
-- 4M Change Management System
-- Database Setup untuk XAMPP / phpMyAdmin
-- Jalankan file ini di phpMyAdmin > SQL tab
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_4m_change`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `db_4m_change`;

-- ============================================================
-- TABEL USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `username`   VARCHAR(50)  NOT NULL,
  `email`      VARCHAR(100) NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','qc_prod','manager','qc') NOT NULL DEFAULT 'qc_prod',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL CHANGE REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `change_requests` (
  `id`                  INT NOT NULL AUTO_INCREMENT,
  `change_no`           VARCHAR(30)  NOT NULL,
  `category_4m`         ENUM('Man','Material','Method','Machine') NOT NULL,
  `pic_id`              INT NOT NULL,
  `part_no`             VARCHAR(100) NULL,
  `implement_location`  VARCHAR(200) NULL,
  `part_name`           VARCHAR(200) NOT NULL,
  `model`               VARCHAR(200) NULL,
  `start_lot_serial`    VARCHAR(200) NULL,
  `change_item`         TEXT NULL,
  `action_plan`         TEXT NULL,
  `before_desc`         TEXT NULL,
  `after_desc`          TEXT NULL,
  `judge_status`        ENUM('OK','NG','Pending') NOT NULL DEFAULT 'Pending',
  `confirm_customer`    ENUM('Need','Not Need') NOT NULL DEFAULT 'Not Need',
  `evidence_note`       TEXT NULL,
  `workflow_status`     ENUM('Draft','Submitted','Manager Approved','QC Approved','Closed','Rejected') NOT NULL DEFAULT 'Draft',
  `rejection_note`      TEXT NULL,
  `rejected_by`         INT NULL,
  `rejected_at`         DATETIME NULL,
  `created_by`          INT NOT NULL,
  `updated_by`          INT NOT NULL,
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_change_no` (`change_no`),
  KEY `idx_pic` (`pic_id`),
  KEY `idx_status` (`workflow_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL CHANGE PHOTOS (before/after)
-- ============================================================
CREATE TABLE IF NOT EXISTS `change_photos` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `change_request_id` INT NOT NULL,
  `photo_type`        ENUM('before','after') NOT NULL,
  `file_name`         VARCHAR(255) NOT NULL,
  `file_path`         VARCHAR(500) NOT NULL,
  `uploaded_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr` (`change_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL CHANGE ATTACHMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `change_attachments` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `change_request_id` INT NOT NULL,
  `file_name`         VARCHAR(255) NOT NULL,
  `file_path`         VARCHAR(500) NOT NULL,
  `file_type`         VARCHAR(20)  NULL,
  `uploaded_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr` (`change_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABEL CHANGE APPROVALS
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
-- TABEL CHANGE HISTORIES
-- ============================================================
CREATE TABLE IF NOT EXISTS `change_histories` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `change_request_id` INT NOT NULL,
  `action_type`       ENUM('CREATE','UPDATE','SUBMIT','RESUBMIT','APPROVAL','REJECTION','CLOSED') NOT NULL DEFAULT 'CREATE',
  `action_note`       TEXT NULL,
  `action_by`         INT NOT NULL,
  `action_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr` (`change_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED: DEFAULT USERS
-- Password plain text (untuk development) — ganti dengan
-- password_hash() di production
-- ============================================================
INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`) VALUES
  ('Administrator',  'admin',    'admin@example.com',   'admin123',   'admin'),
  ('Budi Wijaya',    'manager',  'manager@example.com', 'admin123',   'manager'),
  ('Andi Saputra',   'qc_prod',  'qcprod@example.com',  'admin123',   'qc_prod'),
  ('Sari QC',        'qc',       'qc@example.com',      'admin123',   'qc');

-- ============================================================
-- SEED: DEMO DATA
-- ============================================================
INSERT INTO `change_requests`
  (`change_no`,`category_4m`,`pic_id`,`part_no`,`implement_location`,`part_name`,`model`,
   `start_lot_serial`,`change_item`,`action_plan`,`before_desc`,`after_desc`,
   `judge_status`,`confirm_customer`,`evidence_note`,`workflow_status`,`created_by`,`updated_by`,
   `created_at`,`updated_at`)
VALUES
  ('4M-001','Man',3,'SHF-001','Line A','Shaft Assy R/H','TYPE-A','LOT2026001',
   'Perubahan standar torsi operator line A','Update SOP dan training ulang operator',
   'Torsi standar 25 Nm','Torsi standar 28 Nm berdasarkan revisi spec engineering',
   'OK','Need','Revisi spec disetujui engineering dept',
   'Submitted',3,3,'2026-04-14 08:30:00','2026-04-14 08:42:00'),

  ('4M-002','Material',3,'CPL-008','Line B','Cover Plate FRT','TYPE-B','LOT2026002',
   'Penggantian material cover plate ke grade lebih tinggi','Evaluasi dan approval supplier baru',
   'Material AL6061','Material AL7075 — lebih ringan dan kuat',
   'Pending','Not Need','',
   'Submitted',3,3,'2026-04-13 09:00:00','2026-04-13 09:00:00'),

  ('4M-003','Machine',3,'PJA-022','Press Line','Press Jig Assy','TYPE-C','LOT2026003',
   'Upgrade press jig tooling untuk presisi lebih tinggi','Replace worn tooling dengan spec baru dari engineering',
   'Jig lama — toleransi ±0.3mm','Jig baru — toleransi ±0.1mm',
   'OK','Not Need','Approval engineering sudah ada — ref doc ENG-2026-042',
   'Manager Approved',3,2,'2026-04-10 10:00:00','2026-04-11 14:00:00'),

  ('4M-004','Material',3,'RSB-003','Assembly','Rubber Seal Type B','TYPE-A','LOT2026004',
   'Material rubber seal diubah untuk tahan suhu lebih tinggi','Trial material baru selama 2 minggu di line assembly',
   'Rubber NBR-70 (max 120°C)','Rubber EPDM-80 (max 150°C)',
   'NG','Need','Trial gagal — sample dikembalikan ke supplier untuk revisi',
   'Rejected',3,2,'2026-04-08 08:00:00','2026-04-09 11:00:00'),

  ('4M-005','Man',3,'WOP-001','Welding Dept','Welding Operator Standard','ALL','LOT2026005',
   'Standar kompetensi welder diperbarui ke level 2','Sertifikasi ulang semua welder Line A-C',
   'Sertifikasi level 1 — visual only','Sertifikasi level 2 + visual inspection + RT test',
   'OK','Not Need','Sudah disetujui QA Manager dan HR Director',
   'Closed',3,4,'2026-04-01 08:00:00','2026-04-03 16:00:00');

-- Update rejection info untuk record ke-4
UPDATE `change_requests`
SET `rejection_note` = 'Material tidak memenuhi spek ketahanan suhu setelah uji thermal cycling',
    `rejected_by` = 2,
    `rejected_at` = '2026-04-09 11:00:00'
WHERE `change_no` = '4M-004';

-- Seed approvals
INSERT INTO `change_approvals` (`change_request_id`,`step`,`approver_id`,`status`,`note`,`acted_at`) VALUES
  (3, 'manager',  2, 'Approved', '',                                                                  '2026-04-11 14:00:00'),
  (4, 'manager',  2, 'Rejected', 'Material tidak memenuhi spek ketahanan suhu setelah uji thermal cycling', '2026-04-09 11:00:00'),
  (5, 'manager',  2, 'Approved', '',                                                                  '2026-04-02 10:00:00'),
  (5, 'qc',       4, 'Approved', '',                                                                  '2026-04-02 14:00:00'),
  (5, 'qc_final', 4, 'Approved', '',                                                                  '2026-04-03 16:00:00');

-- Seed histories
INSERT INTO `change_histories` (`change_request_id`,`action_type`,`action_note`,`action_by`,`action_at`) VALUES
  (1,'CREATE','Change request dibuat — status: Draft',3,'2026-04-14 08:30:00'),
  (1,'SUBMIT','Submitted untuk approval',3,'2026-04-14 08:42:00'),
  (2,'CREATE','Change request dibuat — status: Draft',3,'2026-04-13 09:00:00'),
  (2,'SUBMIT','Submitted untuk approval',3,'2026-04-13 09:00:00'),
  (3,'CREATE','Change request dibuat',3,'2026-04-10 10:00:00'),
  (3,'SUBMIT','Submitted untuk approval',3,'2026-04-10 10:30:00'),
  (3,'APPROVAL','Disetujui oleh Manager Department',2,'2026-04-11 14:00:00'),
  (4,'CREATE','Change request dibuat',3,'2026-04-08 08:00:00'),
  (4,'SUBMIT','Submitted untuk approval',3,'2026-04-08 08:30:00'),
  (4,'REJECTION','Ditolak oleh Manager | Alasan: Material tidak memenuhi spek ketahanan suhu',2,'2026-04-09 11:00:00'),
  (5,'CREATE','Change request dibuat',3,'2026-04-01 08:00:00'),
  (5,'SUBMIT','Submitted untuk approval',3,'2026-04-01 09:00:00'),
  (5,'APPROVAL','Disetujui oleh Manager Department',2,'2026-04-02 10:00:00'),
  (5,'APPROVAL','Disetujui oleh QC',4,'2026-04-02 14:00:00'),
  (5,'CLOSED','Final submit oleh QC — permohonan ditutup',4,'2026-04-03 16:00:00');

-- ============================================================
-- SELESAI
-- ============================================================
