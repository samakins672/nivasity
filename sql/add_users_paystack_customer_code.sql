SET @users_paystack_customer_code_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'paystack_customer_code'
);

SET @users_paystack_customer_code_sql := IF(
    @users_paystack_customer_code_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `paystack_customer_code` VARCHAR(100) DEFAULT NULL AFTER `phone`',
    'SELECT 1'
);

PREPARE users_paystack_customer_code_stmt FROM @users_paystack_customer_code_sql;
EXECUTE users_paystack_customer_code_stmt;
DEALLOCATE PREPARE users_paystack_customer_code_stmt;

SET @users_paystack_customer_id_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'paystack_customer_id'
);

SET @users_paystack_customer_id_sql := IF(
    @users_paystack_customer_id_exists = 0,
    'ALTER TABLE `users` ADD COLUMN `paystack_customer_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `paystack_customer_code`',
    'SELECT 1'
);

PREPARE users_paystack_customer_id_stmt FROM @users_paystack_customer_id_sql;
EXECUTE users_paystack_customer_id_stmt;
DEALLOCATE PREPARE users_paystack_customer_id_stmt;