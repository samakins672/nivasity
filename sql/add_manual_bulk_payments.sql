CREATE TABLE IF NOT EXISTS `manual_bulk_payment_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ref_id` varchar(64) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `payer_user_id` int(11) NOT NULL,
  `payer_dept_id` int(11) NOT NULL DEFAULT 0,
  `manual_seller_id` int(11) DEFAULT NULL,
  `student_count` int(11) NOT NULL DEFAULT 0,
  `subtotal` int(11) NOT NULL DEFAULT 0,
  `fee_percent` decimal(5,2) NOT NULL DEFAULT 5.00,
  `fee_amount` int(11) NOT NULL DEFAULT 0,
  `total_amount` int(11) NOT NULL DEFAULT 0,
  `payment_status` varchar(32) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_manual_bulk_payment_ref` (`ref_id`),
  KEY `idx_manual_bulk_payment_payer_created` (`payer_user_id`, `created_at`),
  KEY `idx_manual_bulk_payment_manual_status` (`manual_id`, `payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `manual_bulk_payment_students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `ref_id` varchar(64) NOT NULL,
  `manual_id` int(11) NOT NULL,
  `school_id` int(11) NOT NULL DEFAULT 1,
  `payer_user_id` int(11) NOT NULL,
  `payer_dept_id` int(11) NOT NULL DEFAULT 0,
  `placeholder_user_id` int(11) DEFAULT NULL,
  `matched_user_id` int(11) DEFAULT NULL,
  `manuals_bought_id` int(11) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `normalized_first_name` varchar(255) NOT NULL,
  `normalized_last_name` varchar(255) NOT NULL,
  `raw_matric_no` varchar(100) NOT NULL,
  `normalized_matric_no` varchar(100) NOT NULL,
  `pending_lookup_matric_no` varchar(100) NOT NULL,
  `claim_status` varchar(32) NOT NULL DEFAULT 'pending',
  `claimed_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_manual_bulk_payment_student` (`manual_id`, `school_id`, `normalized_matric_no`, `normalized_first_name`, `normalized_last_name`, `claim_status`),
  KEY `idx_manual_bulk_payment_ref` (`ref_id`),
  KEY `idx_manual_bulk_payment_batch` (`batch_id`),
  KEY `idx_manual_bulk_payment_match` (`school_id`, `payer_dept_id`, `normalized_matric_no`, `normalized_first_name`, `normalized_last_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `manuals_bought`
  ADD COLUMN `payer_user_id` int(11) DEFAULT NULL AFTER `buyer`;
