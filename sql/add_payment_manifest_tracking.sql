-- Add signed payment manifests and repair audits for payment verification.

CREATE TABLE IF NOT EXISTS `payment_manifests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ref_id` VARCHAR(255) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `school_id` INT(11) NOT NULL,
  `gateway` VARCHAR(20) NOT NULL,
  `subtotal` INT(11) NOT NULL DEFAULT 0,
  `charge` INT(11) NOT NULL DEFAULT 0,
  `total_amount` INT(11) NOT NULL DEFAULT 0,
  `items_json` LONGTEXT NOT NULL,
  `manifest_hash` VARCHAR(64) NOT NULL,
  `signature` VARCHAR(64) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_payment_manifests_ref_id` (`ref_id`),
  KEY `idx_payment_manifests_user_id` (`user_id`),
  KEY `idx_payment_manifests_gateway` (`gateway`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payment_repair_audits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ref_id` VARCHAR(255) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `gateway` VARCHAR(20) NOT NULL,
  `reason` VARCHAR(100) NOT NULL,
  `cart_snapshot_json` LONGTEXT DEFAULT NULL,
  `manifest_snapshot_json` LONGTEXT DEFAULT NULL,
  `action_taken` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
  PRIMARY KEY (`id`),
  KEY `idx_payment_repair_audits_ref_id` (`ref_id`),
  KEY `idx_payment_repair_audits_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SELECT 'Payment manifest tracking tables created successfully!' AS message;
