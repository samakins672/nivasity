-- Incremental migration: settlement cron processing support

CREATE TABLE IF NOT EXISTS `settlement_batch_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `settlement_batch_id` INT(11) NOT NULL,
  `school_payable_ledger_id` INT(11) NOT NULL,
  `source_ref_id` VARCHAR(100) NOT NULL,
  `allocated_amount` INT(11) NOT NULL DEFAULT 0,
  `status` ENUM('pending','settled','failed') NOT NULL DEFAULT 'pending',
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_settlement_batch_items_batch` (`settlement_batch_id`),
  KEY `idx_settlement_batch_items_ledger` (`school_payable_ledger_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_settlement_batches_batch_reference := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'settlement_batches'
    AND column_name = 'batch_reference'
);
SET @add_settlement_batches_batch_reference_sql := IF(
  @has_settlement_batches_batch_reference = 0,
  "ALTER TABLE `settlement_batches` ADD COLUMN `batch_reference` VARCHAR(100) DEFAULT NULL AFTER `scheduled_for`",
  'SELECT 1'
);
PREPARE stmt_add_settlement_batches_batch_reference FROM @add_settlement_batches_batch_reference_sql;
EXECUTE stmt_add_settlement_batches_batch_reference;
DEALLOCATE PREPARE stmt_add_settlement_batches_batch_reference;

SET @has_settlement_batches_provider_reference := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'settlement_batches'
    AND column_name = 'provider_reference'
);
SET @add_settlement_batches_provider_reference_sql := IF(
  @has_settlement_batches_provider_reference = 0,
  "ALTER TABLE `settlement_batches` ADD COLUMN `provider_reference` VARCHAR(100) DEFAULT NULL AFTER `transfer_provider`",
  'SELECT 1'
);
PREPARE stmt_add_settlement_batches_provider_reference FROM @add_settlement_batches_provider_reference_sql;
EXECUTE stmt_add_settlement_batches_provider_reference;
DEALLOCATE PREPARE stmt_add_settlement_batches_provider_reference;

SET @has_settlement_batches_provider_response := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'settlement_batches'
    AND column_name = 'provider_response'
);
SET @add_settlement_batches_provider_response_sql := IF(
  @has_settlement_batches_provider_response = 0,
  "ALTER TABLE `settlement_batches` ADD COLUMN `provider_response` LONGTEXT DEFAULT NULL AFTER `provider_reference`",
  'SELECT 1'
);
PREPARE stmt_add_settlement_batches_provider_response FROM @add_settlement_batches_provider_response_sql;
EXECUTE stmt_add_settlement_batches_provider_response;
DEALLOCATE PREPARE stmt_add_settlement_batches_provider_response;

SET @has_settlement_batches_started_at := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'settlement_batches'
    AND column_name = 'started_at'
);
SET @add_settlement_batches_started_at_sql := IF(
  @has_settlement_batches_started_at = 0,
  "ALTER TABLE `settlement_batches` ADD COLUMN `started_at` DATETIME DEFAULT NULL AFTER `provider_response`",
  'SELECT 1'
);
PREPARE stmt_add_settlement_batches_started_at FROM @add_settlement_batches_started_at_sql;
EXECUTE stmt_add_settlement_batches_started_at;
DEALLOCATE PREPARE stmt_add_settlement_batches_started_at;

SET @has_settlement_batches_completed_at := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'settlement_batches'
    AND column_name = 'completed_at'
);
SET @add_settlement_batches_completed_at_sql := IF(
  @has_settlement_batches_completed_at = 0,
  "ALTER TABLE `settlement_batches` ADD COLUMN `completed_at` DATETIME DEFAULT NULL AFTER `started_at`",
  'SELECT 1'
);
PREPARE stmt_add_settlement_batches_completed_at FROM @add_settlement_batches_completed_at_sql;
EXECUTE stmt_add_settlement_batches_completed_at;
DEALLOCATE PREPARE stmt_add_settlement_batches_completed_at;

SET @has_settlement_batches_failed_at := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'settlement_batches'
    AND column_name = 'failed_at'
);
SET @add_settlement_batches_failed_at_sql := IF(
  @has_settlement_batches_failed_at = 0,
  "ALTER TABLE `settlement_batches` ADD COLUMN `failed_at` DATETIME DEFAULT NULL AFTER `completed_at`",
  'SELECT 1'
);
PREPARE stmt_add_settlement_batches_failed_at FROM @add_settlement_batches_failed_at_sql;
EXECUTE stmt_add_settlement_batches_failed_at;
DEALLOCATE PREPARE stmt_add_settlement_batches_failed_at;

SET @has_settlement_batches_last_error := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'settlement_batches'
    AND column_name = 'last_error'
);
SET @add_settlement_batches_last_error_sql := IF(
  @has_settlement_batches_last_error = 0,
  "ALTER TABLE `settlement_batches` ADD COLUMN `last_error` TEXT DEFAULT NULL AFTER `failed_at`",
  'SELECT 1'
);
PREPARE stmt_add_settlement_batches_last_error FROM @add_settlement_batches_last_error_sql;
EXECUTE stmt_add_settlement_batches_last_error;
DEALLOCATE PREPARE stmt_add_settlement_batches_last_error;