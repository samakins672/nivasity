-- Add last_student_id and status columns to manual_export_audits table
-- This allows tracking which student was the last to purchase when export was made
-- and whether the export was 'given' (new) or 'granted' (historical)

ALTER TABLE `manual_export_audits` 
ADD COLUMN `last_student_id` INT(11) DEFAULT NULL COMMENT 'ID of the last student who bought this material at time of export',
ADD COLUMN `status` VARCHAR(20) DEFAULT 'given' COMMENT 'Status of the export: given or granted',
ADD KEY `idx_manual_export_last_student` (`last_student_id`);
