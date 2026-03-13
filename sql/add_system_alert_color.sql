ALTER TABLE `system_alerts`
  ADD COLUMN IF NOT EXISTS `alert_color` enum('red','green') NOT NULL DEFAULT 'red' AFTER `message`;
