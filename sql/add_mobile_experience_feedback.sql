-- Store one-click sentiment responses from the Store page mobile app prompt

CREATE TABLE IF NOT EXISTS `mobile_experience_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_choice` enum('android','iphone') NOT NULL,
  `comfort_level` enum('love_it','its_cool','its_okay','kinda_stressful','not_good_experience') NOT NULL,
  `comfort_label` varchar(64) NOT NULL,
  `source_page` varchar(64) NOT NULL DEFAULT 'store',
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mef_user_id` (`user_id`),
  KEY `idx_mef_device_choice` (`device_choice`),
  KEY `idx_mef_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

