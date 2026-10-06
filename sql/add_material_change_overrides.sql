-- Admin overrides of material changes ("swaps"), done in the command center
-- (Bella Chats > Change material). Students change materials themselves within 72 hours of
-- purchase, once per purchase; admins can go past those two limits with a reason.
-- Every other rule (lost/granted copies, same price, visibility) still applies.
-- Safe to run more than once.

CREATE TABLE IF NOT EXISTS `manual_change_overrides` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `admin_id` INT(11) NOT NULL COMMENT 'admins.id',
  `buyer_id` INT(11) NOT NULL,
  `school_id` INT(11) NOT NULL,
  `manuals_bought_id` INT(11) DEFAULT NULL,
  `ref_id` VARCHAR(50) NOT NULL,
  `old_manual_id` INT(11) NOT NULL,
  `new_manual_id` INT(11) NOT NULL,
  `price` INT(11) NOT NULL DEFAULT 0,
  `ignored_window` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Changed after the 72-hour window',
  `ignored_once` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Purchase had already been changed once',
  `reason` VARCHAR(255) NOT NULL,
  `bella_conversation_id` INT(11) DEFAULT NULL COMMENT 'Bella chat it came from, if any',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_buyer` (`buyer_id`, `created_at`),
  KEY `idx_bought` (`manuals_bought_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
