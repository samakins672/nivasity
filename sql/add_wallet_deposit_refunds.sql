-- Refunds of wallet deposits back to the student's bank, started from the command center
-- (Student Wallets > Deposits > Refund) and sent to Paystack's Refund API.
--   status: processing (sent, waiting for Paystack) -> refunded | failed
--           needs_attention: Paystack wants action in its dashboard; the money stays held
-- The amount is taken off the wallet when the refund starts (debit_ledger_id) and put back if
-- the refund fails (reversal_ledger_id). Safe to run more than once.

CREATE TABLE IF NOT EXISTS `wallet_deposit_refunds` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `reference` VARCHAR(64) NOT NULL COMMENT 'Our refund reference',
  `funding_id` INT(11) NOT NULL COMMENT 'wallet_funding_transactions.id being refunded',
  `wallet_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `amount` INT(11) NOT NULL COMMENT 'Naira',
  `reason` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('processing','refunded','failed','needs_attention') NOT NULL DEFAULT 'processing',
  `provider` VARCHAR(30) NOT NULL DEFAULT 'paystack',
  `provider_transaction_reference` VARCHAR(100) NOT NULL COMMENT 'Deposit reference sent to Paystack',
  `provider_refund_id` VARCHAR(64) DEFAULT NULL,
  `provider_status` VARCHAR(40) DEFAULT NULL,
  `provider_response` LONGTEXT DEFAULT NULL,
  `failure_reason` VARCHAR(255) DEFAULT NULL,
  `debit_ledger_id` INT(11) DEFAULT NULL,
  `reversal_ledger_id` INT(11) DEFAULT NULL,
  `created_by` INT(11) NOT NULL COMMENT 'admins.id',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reference` (`reference`),
  KEY `idx_funding` (`funding_id`),
  KEY `idx_wallet` (`wallet_id`),
  KEY `idx_provider_refund` (`provider_refund_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
