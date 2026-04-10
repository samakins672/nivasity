CREATE TABLE IF NOT EXISTS `wallet_fee_thresholds` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `label` VARCHAR(100) DEFAULT NULL,
  `min_subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `max_subtotal` DECIMAL(12,2) DEFAULT NULL,
  `fee_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_fee_thresholds_status` (`status`),
  KEY `idx_wallet_fee_thresholds_range` (`min_subtotal`, `max_subtotal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `wallet_fee_thresholds` (`label`, `min_subtotal`, `max_subtotal`, `fee_amount`, `status`)
SELECT * FROM (
    SELECT 'Starter band', 0.00, 4999.99, 25.00, 'active'
    UNION ALL
    SELECT 'Mid band', 5000.00, 19999.99, 50.00, 'active'
    UNION ALL
    SELECT 'Upper band', 20000.00, 49999.99, 100.00, 'active'
    UNION ALL
    SELECT 'High band', 50000.00, NULL, 150.00, 'active'
) AS seed_rows
WHERE NOT EXISTS (SELECT 1 FROM `wallet_fee_thresholds` LIMIT 1);