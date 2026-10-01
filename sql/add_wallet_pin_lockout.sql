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
