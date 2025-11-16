CREATE TABLE `manual_export_audits` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(25) NOT NULL,                  -- public verification code printed on export
  `manual_id` INT(11) NOT NULL,                 -- manuals.id
  `hoc_user_id` INT(11) NOT NULL,               -- users.id of HOC/admin who exported
  `students_count` INT(11) NOT NULL,            -- number of unique students in the export
  `total_amount` INT(11) NOT NULL,              -- total amount paid across all rows (in kobo-free integer, same as manuals_bought.price)
  `downloaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_manual_export_code` (`code`),
  KEY `idx_manual_export_manual` (`manual_id`),
  KEY `idx_manual_export_hoc` (`hoc_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

