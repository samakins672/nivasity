-- ============================================================================
-- White label transition: production migration (run once, safe to re-run).
-- Contains: sql/add_wallet_pin_lockout.sql + sql/add_semester_tagging.sql
-- Take a backup first. manuals is MyISAM, so run off-peak (brief table lock).
-- No other schema changes are needed for white-label-parity (receipts, bulk
-- history, wallet filters, duplicate-account fixes and claims use existing tables).
-- ============================================================================

-- 1) Wallet PIN lockout
-- Wallet PIN lockout: after NIVASITY_WALLET_PIN_MAX_ATTEMPTS (5) consecutive wrong PINs,
-- PIN-protected wallet actions are blocked for NIVASITY_WALLET_PIN_LOCK_MINUTES (30).
-- Until this runs, the code skips the lockout and behaves as before.

SET @users_wallet_pin_failed_attempts_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'wallet_pin_failed_attempts'
);

SET @users_wallet_pin_failed_attempts_sql := IF(
    @users_wallet_pin_failed_attempts_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `wallet_pin_failed_attempts` INT(11) NOT NULL DEFAULT 0 AFTER `wallet_pin_updated_at`',
    'SELECT 1'
);

PREPARE users_wallet_pin_failed_attempts_stmt FROM @users_wallet_pin_failed_attempts_sql;
EXECUTE users_wallet_pin_failed_attempts_stmt;
DEALLOCATE PREPARE users_wallet_pin_failed_attempts_stmt;

SET @users_wallet_pin_locked_until_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'wallet_pin_locked_until'
);

SET @users_wallet_pin_locked_until_sql := IF(
    @users_wallet_pin_locked_until_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `wallet_pin_locked_until` DATETIME DEFAULT NULL AFTER `wallet_pin_failed_attempts`',
    'SELECT 1'
);

PREPARE users_wallet_pin_locked_until_stmt FROM @users_wallet_pin_locked_until_sql;
EXECUTE users_wallet_pin_locked_until_stmt;
DEALLOCATE PREPARE users_wallet_pin_locked_until_stmt;

-- 2) Semester tagging
-- Semester tagging for course materials (see docs/WHITE_LABEL_TRANSITION_PLAN.md, section 4).
--   schools.current_semester      : 1 = First, 2 = Second (switched from cc_dashboard)
--   manuals.semester              : 1 or 2; NULL = legacy material not yet tagged
--   manuals.confirmed_at/_by      : last carry-over confirmation (admins.id)
--   manuals.status gains the value 'awaiting_confirmation' (column widened to varchar(32))
-- Safe to run more than once. manuals is MyISAM: run off-peak (table lock).

-- schools.current_semester
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'current_semester');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `schools` ADD COLUMN `current_semester` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = First Semester, 2 = Second Semester'' AFTER `status`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- schools.current_semester_updated_at
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schools' AND COLUMN_NAME = 'current_semester_updated_at');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `schools` ADD COLUMN `current_semester_updated_at` DATETIME DEFAULT NULL AFTER `current_semester`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- manuals.semester
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'manuals' AND COLUMN_NAME = 'semester');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `manuals` ADD COLUMN `semester` TINYINT(1) DEFAULT NULL COMMENT ''1 = First, 2 = Second. NULL = legacy, not yet tagged''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- manuals.confirmed_at
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'manuals' AND COLUMN_NAME = 'confirmed_at');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `manuals` ADD COLUMN `confirmed_at` DATETIME DEFAULT NULL COMMENT ''Last carry-over confirmation'' AFTER `status`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- manuals.confirmed_by
SET @col_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'manuals' AND COLUMN_NAME = 'confirmed_by');
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `manuals` ADD COLUMN `confirmed_by` INT(11) DEFAULT NULL COMMENT ''admins.id'' AFTER `confirmed_at`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- manuals.status must hold 'awaiting_confirmation' (21 chars); it was varchar(20).
SET @status_len := (SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'manuals' AND COLUMN_NAME = 'status');
SET @sql := IF(@status_len IS NOT NULL AND @status_len < 32,
  'ALTER TABLE `manuals` MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT ''open''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Repair values truncated by a semester switch run before the column was widened.
UPDATE `manuals` SET `status` = 'awaiting_confirmation' WHERE `status` = 'awaiting_confirmatio';

-- Index for store queries
SET @idx_exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'manuals' AND INDEX_NAME = 'idx_manuals_school_status_semester');
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE `manuals` ADD KEY `idx_manuals_school_status_semester` (`school_id`, `status`, `semester`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Check (expect: 2 users columns, 2 schools columns, 3 manuals columns, status length 32)
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME = 'users' AND COLUMN_NAME IN ('wallet_pin_failed_attempts', 'wallet_pin_locked_until'))
    OR (TABLE_NAME = 'schools' AND COLUMN_NAME IN ('current_semester', 'current_semester_updated_at'))
    OR (TABLE_NAME = 'manuals' AND COLUMN_NAME IN ('semester', 'confirmed_at', 'confirmed_by', 'status')))
ORDER BY TABLE_NAME, COLUMN_NAME;
