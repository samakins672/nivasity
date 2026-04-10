CREATE TABLE IF NOT EXISTS `material_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `school_id` INT(11) NOT NULL,
  `requester_user_id` INT(11) NOT NULL,
  `requester_dept_id` INT(11) NOT NULL DEFAULT 0,
  `requester_faculty_id` INT(11) NOT NULL DEFAULT 0,
  `material_code` VARCHAR(100) NOT NULL,
  `material_title` VARCHAR(255) NOT NULL,
  `material_code_normalized` VARCHAR(100) NOT NULL,
  `material_title_normalized` VARCHAR(255) NOT NULL,
  `scope` ENUM('school','faculty','selected_faculties','selected_departments','my_department') NOT NULL DEFAULT 'my_department',
  `target_faculty_id` INT(11) NOT NULL DEFAULT 0,
  `target_department_id` INT(11) NOT NULL DEFAULT 0,
  `target_faculty_ids_json` LONGTEXT DEFAULT NULL,
  `target_dept_ids_json` LONGTEXT DEFAULT NULL,
  `expected_buyers_count` INT(11) NOT NULL DEFAULT 0,
  `share_token` VARCHAR(40) NOT NULL,
  `status` ENUM('open','under_review','resolved') NOT NULL DEFAULT 'open',
  `threshold_percent` DECIMAL(5,2) NOT NULL DEFAULT 40.00,
  `resolved_manual_id` INT(11) DEFAULT NULL,
  `resolution_note` TEXT DEFAULT NULL,
  `resolved_by_admin_id` INT(11) DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_material_requests_share_token` (`share_token`),
  KEY `idx_material_requests_school_status` (`school_id`, `status`),
  KEY `idx_material_requests_lookup_code` (`school_id`, `material_code_normalized`),
  KEY `idx_material_requests_lookup_title` (`school_id`, `material_title_normalized`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `material_request_votes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `request_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_material_request_vote` (`request_id`, `user_id`),
  KEY `idx_material_request_votes_request` (`request_id`),
  KEY `idx_material_request_votes_user` (`user_id`),
  CONSTRAINT `fk_material_request_votes_request`
    FOREIGN KEY (`request_id`) REFERENCES `material_requests` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;