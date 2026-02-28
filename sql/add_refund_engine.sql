-- Refund reservation engine migration

-- 1) Transactions additions
SET @has_transactions_refund_column := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'transactions'
    AND column_name = 'refund'
);
SET @add_transactions_refund_column_sql := IF(
  @has_transactions_refund_column = 0,
  'ALTER TABLE `transactions` ADD COLUMN `refund` INT NOT NULL DEFAULT 0 AFTER `profit`',
  'SELECT 1'
);
PREPARE stmt_add_transactions_refund_column FROM @add_transactions_refund_column_sql;
EXECUTE stmt_add_transactions_refund_column;
DEALLOCATE PREPARE stmt_add_transactions_refund_column;

SET @has_idx_ref_id := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'transactions'
    AND index_name = 'idx_ref_id'
);
SET @add_idx_ref_id_sql := IF(
  @has_idx_ref_id = 0,
  'ALTER TABLE `transactions` ADD INDEX `idx_ref_id` (`ref_id`)',
  'SELECT 1'
);
PREPARE stmt_add_idx_ref_id FROM @add_idx_ref_id_sql;
EXECUTE stmt_add_idx_ref_id;
DEALLOCATE PREPARE stmt_add_idx_ref_id;

SET @has_idx_refund := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'transactions'
    AND index_name = 'idx_refund'
);
SET @add_idx_refund_sql := IF(
  @has_idx_refund = 0,
  'ALTER TABLE `transactions` ADD INDEX `idx_refund` (`refund`)',
  'SELECT 1'
);
PREPARE stmt_add_idx_refund FROM @add_idx_refund_sql;
EXECUTE stmt_add_idx_refund;
DEALLOCATE PREPARE stmt_add_idx_refund;

-- 2) Refund source ledger
CREATE TABLE IF NOT EXISTS `refunds` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) NOT NULL,
  `student_id` INT(11) DEFAULT NULL,
  `ref_id` VARCHAR(100) DEFAULT NULL,
  `amount` INT(11) NOT NULL,
  `remaining_amount` INT(11) NOT NULL,
  `status` ENUM('pending','partially_applied','applied','cancelled') NOT NULL DEFAULT 'pending',
  `reason` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_refunds_ref_id` (`ref_id`),
  INDEX `idx_refunds_school_status_created` (`school_id`, `status`, `created_at`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) Reservation + audit ledger
CREATE TABLE IF NOT EXISTS `refund_reservations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `refund_id` INT(11) NOT NULL,
  `ref_id` VARCHAR(100) NOT NULL,
  `split_sequence` INT(11) NOT NULL DEFAULT 1,
  `school_id` INT(11) NOT NULL,
  `payer_user_id` INT(11) NOT NULL,
  `gateway` VARCHAR(50) NOT NULL,
  `amount` INT(11) NOT NULL,
  `channel` ENUM('web','api') NOT NULL,
  `status` ENUM('reserved','consumed','released') NOT NULL DEFAULT 'reserved',
  `reserved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `consumed_at` DATETIME DEFAULT NULL,
  `released_at` DATETIME DEFAULT NULL,
  `release_reason` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_ref_id_status` (`ref_id`, `status`),
  INDEX `idx_school_status` (`school_id`, `status`),
  INDEX `idx_refund_ref_status` (`refund_id`, `ref_id`, `status`),
  CONSTRAINT `fk_refund_reservations_refund`
    FOREIGN KEY (`refund_id`) REFERENCES `refunds` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3b) Reconcile existing installations:
-- - drop old strict unique key
-- - ensure split_sequence and lookup index exist
SET @has_uniq_refund_ref_id := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'refund_reservations'
    AND index_name = 'uniq_refund_ref_id'
);
SET @drop_uniq_sql := IF(
  @has_uniq_refund_ref_id > 0,
  'ALTER TABLE `refund_reservations` DROP INDEX `uniq_refund_ref_id`',
  'SELECT 1'
);
PREPARE stmt_drop_uniq FROM @drop_uniq_sql;
EXECUTE stmt_drop_uniq;
DEALLOCATE PREPARE stmt_drop_uniq;

SET @has_split_sequence := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'refund_reservations'
    AND column_name = 'split_sequence'
);
SET @add_split_sequence_sql := IF(
  @has_split_sequence = 0,
  'ALTER TABLE `refund_reservations` ADD COLUMN `split_sequence` INT(11) NOT NULL DEFAULT 1 AFTER `ref_id`',
  'SELECT 1'
);
PREPARE stmt_add_split_sequence FROM @add_split_sequence_sql;
EXECUTE stmt_add_split_sequence;
DEALLOCATE PREPARE stmt_add_split_sequence;

SET @has_idx_refund_ref_status := (
  SELECT COUNT(1)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'refund_reservations'
    AND index_name = 'idx_refund_ref_status'
);
SET @add_idx_refund_ref_status_sql := IF(
  @has_idx_refund_ref_status = 0,
  'ALTER TABLE `refund_reservations` ADD INDEX `idx_refund_ref_status` (`refund_id`, `ref_id`, `status`)',
  'SELECT 1'
);
PREPARE stmt_add_idx_refund_ref_status FROM @add_idx_refund_ref_status_sql;
EXECUTE stmt_add_idx_refund_ref_status;
DEALLOCATE PREPARE stmt_add_idx_refund_ref_status;

-- 4) Backfill rule for old rows
UPDATE `transactions` SET `refund` = 0 WHERE `refund` IS NULL;
