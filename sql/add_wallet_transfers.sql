CREATE TABLE IF NOT EXISTS `wallet_transfers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `transfer_reference` VARCHAR(100) NOT NULL,
  `request_token` VARCHAR(100) NOT NULL,
  `sender_wallet_id` INT(11) NOT NULL,
  `recipient_wallet_id` INT(11) NOT NULL,
  `sender_user_id` INT(11) NOT NULL,
  `recipient_user_id` INT(11) NOT NULL,
  `recipient_lookup_value` VARCHAR(255) DEFAULT NULL,
  `recipient_name` VARCHAR(255) DEFAULT NULL,
  `recipient_email` VARCHAR(255) DEFAULT NULL,
  `recipient_matric_no` VARCHAR(100) DEFAULT NULL,
  `amount` INT(11) NOT NULL DEFAULT 0,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'NGN',
  `sender_balance_before` INT(11) NOT NULL DEFAULT 0,
  `sender_balance_after` INT(11) NOT NULL DEFAULT 0,
  `recipient_balance_before` INT(11) NOT NULL DEFAULT 0,
  `recipient_balance_after` INT(11) NOT NULL DEFAULT 0,
  `status` ENUM('pending','completed','failed','reversed') NOT NULL DEFAULT 'pending',
  `initiated_via` VARCHAR(20) NOT NULL DEFAULT 'web',
  `description` VARCHAR(255) DEFAULT NULL,
  `failure_reason` VARCHAR(255) DEFAULT NULL,
  `metadata` LONGTEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_wallet_transfers_reference` (`transfer_reference`),
  UNIQUE KEY `uniq_wallet_transfers_request_token` (`request_token`),
  KEY `idx_wallet_transfers_sender_wallet_created` (`sender_wallet_id`, `created_at`),
  KEY `idx_wallet_transfers_recipient_wallet_created` (`recipient_wallet_id`, `created_at`),
  KEY `idx_wallet_transfers_status_created` (`status`, `created_at`),
  CONSTRAINT `fk_wallet_transfers_sender_wallet`
    FOREIGN KEY (`sender_wallet_id`) REFERENCES `user_wallets` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_wallet_transfers_recipient_wallet`
    FOREIGN KEY (`recipient_wallet_id`) REFERENCES `user_wallets` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;