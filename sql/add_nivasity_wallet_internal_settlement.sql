-- Nivasity wallet and internal settlement foundation

CREATE TABLE IF NOT EXISTS `user_wallets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `school_id` INT(11) NOT NULL,
  `status` ENUM('active','suspended','closed') NOT NULL DEFAULT 'active',
  `balance` INT(11) NOT NULL DEFAULT 0,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'NGN',
  `requested_via` VARCHAR(20) NOT NULL DEFAULT 'web',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_wallet_user_id` (`user_id`),
  KEY `idx_user_wallet_school_status` (`school_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wallet_virtual_accounts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` INT(11) NOT NULL,
  `provider` VARCHAR(30) NOT NULL DEFAULT 'paystack',
  `provider_account_id` VARCHAR(100) DEFAULT NULL,
  `provider_customer_code` VARCHAR(100) DEFAULT NULL,
  `account_name` VARCHAR(255) NOT NULL,
  `account_number` VARCHAR(30) NOT NULL,
  `bank_name` VARCHAR(100) DEFAULT NULL,
  `bank_slug` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `raw_response` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wallet_virtual_account_wallet` (`wallet_id`),
  UNIQUE KEY `uniq_wallet_virtual_account_number` (`account_number`),
  CONSTRAINT `fk_wallet_virtual_accounts_wallet`
    FOREIGN KEY (`wallet_id`) REFERENCES `user_wallets` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wallet_ledger_entries` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` INT(11) NOT NULL,
  `entry_type` ENUM('credit','debit','refund','fee','adjustment') NOT NULL,
  `amount` INT(11) NOT NULL,
  `balance_before` INT(11) NOT NULL DEFAULT 0,
  `balance_after` INT(11) NOT NULL DEFAULT 0,
  `status` ENUM('pending','posted','reversed','failed') NOT NULL DEFAULT 'posted',
  `reference` VARCHAR(100) NOT NULL,
  `provider_reference` VARCHAR(100) DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `metadata` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_ledger_wallet_created` (`wallet_id`, `created_at`),
  KEY `idx_wallet_ledger_reference` (`reference`),
  KEY `idx_wallet_ledger_provider_reference` (`provider_reference`),
  CONSTRAINT `fk_wallet_ledger_entries_wallet`
    FOREIGN KEY (`wallet_id`) REFERENCES `user_wallets` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `wallet_funding_transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `wallet_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `provider` VARCHAR(30) NOT NULL DEFAULT 'paystack',
  `provider_reference` VARCHAR(100) NOT NULL,
  `provider_event` VARCHAR(100) DEFAULT NULL,
  `provider_transaction_id` VARCHAR(100) DEFAULT NULL,
  `provider_account_id` VARCHAR(100) DEFAULT NULL,
  `account_number` VARCHAR(30) DEFAULT NULL,
  `amount` INT(11) NOT NULL DEFAULT 0,
  `status` ENUM('pending','posted','ignored','failed','reversed') NOT NULL DEFAULT 'posted',
  `source` VARCHAR(20) NOT NULL DEFAULT 'webhook',
  `description` VARCHAR(255) DEFAULT NULL,
  `raw_payload` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `posted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wallet_funding_provider_reference` (`provider_reference`),
  KEY `idx_wallet_funding_wallet_created` (`wallet_id`, `created_at`),
  KEY `idx_wallet_funding_status` (`status`),
  CONSTRAINT `fk_wallet_funding_transactions_wallet`
    FOREIGN KEY (`wallet_id`) REFERENCES `user_wallets` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `school_internal_wallets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) NOT NULL,
  `current_balance` INT(11) NOT NULL DEFAULT 0,
  `pending_payout_balance` INT(11) NOT NULL DEFAULT 0,
  `carry_forward_balance` INT(11) NOT NULL DEFAULT 0,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'NGN',
  `status` ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_school_internal_wallet_school` (`school_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `school_payable_ledger` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) NOT NULL,
  `source_ref_id` VARCHAR(100) NOT NULL,
  `payer_user_id` INT(11) NOT NULL,
  `source_medium` VARCHAR(50) NOT NULL,
  `source_channel` VARCHAR(20) NOT NULL DEFAULT 'web',
  `item_subtotal` INT(11) NOT NULL DEFAULT 0,
  `collected_total` INT(11) NOT NULL DEFAULT 0,
  `charge_amount` INT(11) NOT NULL DEFAULT 0,
  `refund_amount` INT(11) NOT NULL DEFAULT 0,
  `payable_amount` INT(11) NOT NULL DEFAULT 0,
  `settled_amount` INT(11) NOT NULL DEFAULT 0,
  `carry_forward_amount` INT(11) NOT NULL DEFAULT 0,
  `status` ENUM('pending','partially_settled','settled','reversed','carry_forward') NOT NULL DEFAULT 'pending',
  `metadata` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_school_payable_source_ref` (`source_ref_id`),
  KEY `idx_school_payable_school_status_created` (`school_id`, `status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `settlement_batches` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) NOT NULL,
  `scheduled_for` DATE NOT NULL,
  `batch_reference` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `total_amount` INT(11) NOT NULL DEFAULT 0,
  `total_records` INT(11) NOT NULL DEFAULT 0,
  `transfer_provider` VARCHAR(50) DEFAULT NULL,
  `provider_reference` VARCHAR(100) DEFAULT NULL,
  `provider_response` LONGTEXT DEFAULT NULL,
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `failed_at` DATETIME DEFAULT NULL,
  `last_error` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_settlement_batch_reference` (`batch_reference`),
  KEY `idx_settlement_batches_school_date` (`school_id`, `scheduled_for`),
  KEY `idx_settlement_batches_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  KEY `idx_settlement_batch_items_ledger` (`school_payable_ledger_id`),
  CONSTRAINT `fk_settlement_batch_items_batch`
    FOREIGN KEY (`settlement_batch_id`) REFERENCES `settlement_batches` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_settlement_batch_items_ledger`
    FOREIGN KEY (`school_payable_ledger_id`) REFERENCES `school_payable_ledger` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET @has_cart_payment_channel := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'cart'
    AND column_name = 'payment_channel'
);
SET @add_cart_payment_channel_sql := IF(
  @has_cart_payment_channel = 0,
  "ALTER TABLE `cart` ADD COLUMN `payment_channel` VARCHAR(20) NOT NULL DEFAULT 'gateway' AFTER `gateway`",
  'SELECT 1'
);
PREPARE stmt_add_cart_payment_channel FROM @add_cart_payment_channel_sql;
EXECUTE stmt_add_cart_payment_channel;
DEALLOCATE PREPARE stmt_add_cart_payment_channel;

SET @has_transactions_payment_channel := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'transactions'
    AND column_name = 'payment_channel'
);
SET @add_transactions_payment_channel_sql := IF(
  @has_transactions_payment_channel = 0,
  "ALTER TABLE `transactions` ADD COLUMN `payment_channel` VARCHAR(20) NOT NULL DEFAULT 'gateway' AFTER `medium`",
  'SELECT 1'
);
PREPARE stmt_add_transactions_payment_channel FROM @add_transactions_payment_channel_sql;
EXECUTE stmt_add_transactions_payment_channel;
DEALLOCATE PREPARE stmt_add_transactions_payment_channel;

SET @has_transactions_transaction_context := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'transactions'
    AND column_name = 'transaction_context'
);
SET @add_transactions_transaction_context_sql := IF(
  @has_transactions_transaction_context = 0,
  "ALTER TABLE `transactions` ADD COLUMN `transaction_context` VARCHAR(30) NOT NULL DEFAULT 'purchase' AFTER `payment_channel`",
  'SELECT 1'
);
PREPARE stmt_add_transactions_transaction_context FROM @add_transactions_transaction_context_sql;
EXECUTE stmt_add_transactions_transaction_context;
DEALLOCATE PREPARE stmt_add_transactions_transaction_context;

UPDATE `cart` SET `payment_channel` = 'gateway' WHERE `payment_channel` IS NULL OR `payment_channel` = '';
UPDATE `transactions` SET `payment_channel` = 'gateway' WHERE `payment_channel` IS NULL OR `payment_channel` = '';
UPDATE `transactions` SET `transaction_context` = 'purchase' WHERE `transaction_context` IS NULL OR `transaction_context` = '';
