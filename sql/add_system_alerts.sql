-- Add system_alerts table for displaying informational alerts to users
-- Alerts can have an expiry date and can be shown in a carousel if multiple exist

CREATE TABLE IF NOT EXISTS `system_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `expiry_date` datetime NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `active` (`active`),
  KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Example alerts (optional - remove if not needed)
-- INSERT INTO `system_alerts` (`message`, `expiry_date`, `active`) VALUES
-- ('Welcome to Nivasity! Check out our new features.', DATE_ADD(NOW(), INTERVAL 7 DAY), 1),
-- ('System maintenance scheduled for next week.', DATE_ADD(NOW(), INTERVAL 14 DAY), 1);
