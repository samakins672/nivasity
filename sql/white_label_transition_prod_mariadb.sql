-- ============================================================================
-- White label transition: production migration, cPanel / phpMyAdmin version.
-- Same changes as white_label_transition_prod.sql, without INFORMATION_SCHEMA
-- (cPanel database users often cannot read it). Needs MariaDB 10.0.2+ for
-- "IF NOT EXISTS" (check with: SELECT VERSION();). Safe to run more than once.
-- Take a backup first. manuals is MyISAM: run off-peak (brief table lock).
-- ============================================================================

-- 1) Wallet PIN lockout (5 wrong PINs -> 30 minute lock)
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `wallet_pin_failed_attempts` INT(11) NOT NULL DEFAULT 0 AFTER `wallet_pin_updated_at`,
  ADD COLUMN IF NOT EXISTS `wallet_pin_locked_until` DATETIME DEFAULT NULL AFTER `wallet_pin_failed_attempts`;

-- 2) Semester tagging
ALTER TABLE `schools`
  ADD COLUMN IF NOT EXISTS `current_semester` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = First Semester, 2 = Second Semester' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `current_semester_updated_at` DATETIME DEFAULT NULL AFTER `current_semester`;

ALTER TABLE `manuals`
  ADD COLUMN IF NOT EXISTS `semester` TINYINT(1) DEFAULT NULL COMMENT '1 = First, 2 = Second. NULL = legacy, not yet tagged',
  ADD COLUMN IF NOT EXISTS `confirmed_at` DATETIME DEFAULT NULL COMMENT 'Last carry-over confirmation' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `confirmed_by` INT(11) DEFAULT NULL COMMENT 'admins.id' AFTER `confirmed_at`;

-- manuals.status must hold 'awaiting_confirmation' (21 chars); it was varchar(20).
ALTER TABLE `manuals` MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'open';

-- Repair values truncated by a semester switch run before the column was widened.
UPDATE `manuals` SET `status` = 'awaiting_confirmation' WHERE `status` = 'awaiting_confirmatio';

-- Index for store queries
ALTER TABLE `manuals` ADD INDEX IF NOT EXISTS `idx_manuals_school_status_semester` (`school_id`, `status`, `semester`);

-- 3) Check: the new columns should be listed
SHOW COLUMNS FROM `users` LIKE 'wallet_pin_%';
SHOW COLUMNS FROM `schools` LIKE 'current_semester%';
SHOW COLUMNS FROM `manuals` WHERE Field IN ('semester', 'confirmed_at', 'confirmed_by', 'status');
