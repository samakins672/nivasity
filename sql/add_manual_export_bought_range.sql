-- Add bought-row range tracking to manual_export_audits.
-- from_bought_id: first pending successful manuals_bought.id at export time
-- to_bought_id: latest successful manuals_bought.id at export time

ALTER TABLE `manual_export_audits`
ADD COLUMN `from_bought_id` INT(11) DEFAULT NULL COMMENT 'Start manuals_bought.id used for this export',
ADD COLUMN `to_bought_id` INT(11) DEFAULT NULL COMMENT 'End manuals_bought.id used for this export',
ADD KEY `idx_manual_export_bought_range` (`from_bought_id`, `to_bought_id`);
