-- Add grant tracking columns to manual_export_audits table
-- This allows tracking:
-- 1) the last student covered by an export cut-off
-- 2) export grant workflow state and metadata

ALTER TABLE `manual_export_audits` 
ADD COLUMN `last_student_id` INT(11) DEFAULT NULL COMMENT 'ID of the last student who bought this material at time of export',
ADD COLUMN `from_bought_id` INT(11) DEFAULT NULL COMMENT 'Start manuals_bought.id used for this export',
ADD COLUMN `to_bought_id` INT(11) DEFAULT NULL COMMENT 'End manuals_bought.id used for this export',
ADD COLUMN `grant_status` VARCHAR(20) DEFAULT 'pending' COMMENT 'Status of the grant: pending or granted',
ADD COLUMN `granted_by` INT(11) DEFAULT NULL COMMENT 'Admin ID who granted the export',
ADD COLUMN `granted_at` DATETIME DEFAULT NULL COMMENT 'Timestamp when the export was granted',
ADD KEY `idx_manual_export_last_student` (`last_student_id`),
ADD KEY `idx_manual_export_bought_range` (`from_bought_id`, `to_bought_id`),
ADD KEY `idx_manual_export_grant_status` (`grant_status`);
