CREATE TABLE IF NOT EXISTS `app_update_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `android_latest_version` varchar(50) NOT NULL,
  `android_minimum_version` varchar(50) NOT NULL,
  `android_store_url` varchar(500) NOT NULL,
  `android_title` varchar(255) NOT NULL,
  `android_message` text NOT NULL,
  `android_required` tinyint(1) NOT NULL DEFAULT 0,
  `ios_latest_version` varchar(50) NOT NULL,
  `ios_minimum_version` varchar(50) NOT NULL,
  `ios_store_url` varchar(500) NOT NULL,
  `ios_title` varchar(255) NOT NULL,
  `ios_message` text NOT NULL,
  `ios_required` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Example seed (optional)
-- INSERT INTO `app_update_configs` (
--   `android_latest_version`, `android_minimum_version`, `android_store_url`, `android_title`, `android_message`, `android_required`,
--   `ios_latest_version`, `ios_minimum_version`, `ios_store_url`, `ios_title`, `ios_message`, `ios_required`
-- ) VALUES (
--   '1.0.1', '1.0.0', 'https://play.google.com/store/apps/details?id=com.nivasity.app', 'Update available', 'A newer version is available.', 0,
--   '1.0.1', '1.0.0', 'https://apps.apple.com/app/id1234567890', 'Update available', 'A newer version is available.', 0
-- );