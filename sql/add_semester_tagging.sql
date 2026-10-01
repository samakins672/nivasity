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
