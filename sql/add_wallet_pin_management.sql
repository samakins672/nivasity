SET @users_wallet_pin_hash_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'wallet_pin_hash'
);

SET @users_wallet_pin_hash_sql := IF(
    @users_wallet_pin_hash_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `wallet_pin_hash` VARCHAR(255) DEFAULT NULL AFTER `paystack_customer_id`',
    'SELECT 1'
);

PREPARE users_wallet_pin_hash_stmt FROM @users_wallet_pin_hash_sql;
EXECUTE users_wallet_pin_hash_stmt;
DEALLOCATE PREPARE users_wallet_pin_hash_stmt;

SET @users_wallet_pin_updated_at_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'wallet_pin_updated_at'
);

SET @users_wallet_pin_updated_at_sql := IF(
    @users_wallet_pin_updated_at_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `wallet_pin_updated_at` DATETIME DEFAULT NULL AFTER `wallet_pin_hash`',
    'SELECT 1'
);

PREPARE users_wallet_pin_updated_at_stmt FROM @users_wallet_pin_updated_at_sql;
EXECUTE users_wallet_pin_updated_at_stmt;
DEALLOCATE PREPARE users_wallet_pin_updated_at_stmt;

CREATE TABLE IF NOT EXISTS `wallet_pin_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `purpose` ENUM('create','update') NOT NULL DEFAULT 'create',
  `expires_at` DATETIME NOT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `verification_token_hash` VARCHAR(255) DEFAULT NULL,
  `verification_token_expires_at` DATETIME DEFAULT NULL,
  `consumed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_pin_tokens_user` (`user_id`),
  KEY `idx_wallet_pin_tokens_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @wallet_pin_verified_at_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wallet_pin_tokens'
      AND COLUMN_NAME = 'verified_at'
);

SET @wallet_pin_verified_at_sql := IF(
    @wallet_pin_verified_at_exists = 0,
    'ALTER TABLE `wallet_pin_tokens` ADD COLUMN `verified_at` DATETIME DEFAULT NULL AFTER `expires_at`',
    'SELECT 1'
);

PREPARE wallet_pin_verified_at_stmt FROM @wallet_pin_verified_at_sql;
EXECUTE wallet_pin_verified_at_stmt;
DEALLOCATE PREPARE wallet_pin_verified_at_stmt;

SET @wallet_pin_verification_token_hash_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wallet_pin_tokens'
      AND COLUMN_NAME = 'verification_token_hash'
);

SET @wallet_pin_verification_token_hash_sql := IF(
    @wallet_pin_verification_token_hash_exists = 0,
    'ALTER TABLE `wallet_pin_tokens` ADD COLUMN `verification_token_hash` VARCHAR(255) DEFAULT NULL AFTER `verified_at`',
    'SELECT 1'
);

PREPARE wallet_pin_verification_token_hash_stmt FROM @wallet_pin_verification_token_hash_sql;
EXECUTE wallet_pin_verification_token_hash_stmt;
DEALLOCATE PREPARE wallet_pin_verification_token_hash_stmt;

SET @wallet_pin_verification_token_expires_at_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wallet_pin_tokens'
      AND COLUMN_NAME = 'verification_token_expires_at'
);

SET @wallet_pin_verification_token_expires_at_sql := IF(
    @wallet_pin_verification_token_expires_at_exists = 0,
    'ALTER TABLE `wallet_pin_tokens` ADD COLUMN `verification_token_expires_at` DATETIME DEFAULT NULL AFTER `verification_token_hash`',
    'SELECT 1'
);

PREPARE wallet_pin_verification_token_expires_at_stmt FROM @wallet_pin_verification_token_expires_at_sql;
EXECUTE wallet_pin_verification_token_expires_at_stmt;
DEALLOCATE PREPARE wallet_pin_verification_token_expires_at_stmt;