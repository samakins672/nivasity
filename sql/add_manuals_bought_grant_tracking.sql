-- Add grant tracking columns to manuals_bought.
-- grant_status: 0 = pending/not granted, 1 = granted
-- export_id: manual_export_audits.id that granted the row; NULL means single/manual grant flow

ALTER TABLE `manuals_bought`
ADD COLUMN `grant_status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = pending, 1 = granted',
ADD COLUMN `export_id` INT(11) DEFAULT NULL COMMENT 'manual_export_audits.id used to grant this row; NULL for single grant',
ADD KEY `idx_manuals_bought_grant_status` (`grant_status`),
ADD KEY `idx_manuals_bought_export_id` (`export_id`),
ADD KEY `idx_manuals_bought_manual_buyer_grant` (`manual_id`, `buyer`, `grant_status`);
