ALTER TABLE `system_alerts`
  ADD COLUMN IF NOT EXISTS `alert_color` enum('red','green','info') NOT NULL DEFAULT 'red' AFTER `message`;

ALTER TABLE `system_alerts`
  MODIFY COLUMN `alert_color` enum('red','green','info') NOT NULL DEFAULT 'red';
