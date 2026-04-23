-- Track whether a purchased material copy is still active or has been reported lost.
ALTER TABLE `manuals_bought`
ADD COLUMN `copy_status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active or lost' AFTER `status`,
ADD COLUMN `lost_at` DATETIME DEFAULT NULL COMMENT 'When the copy was marked lost' AFTER `copy_status`,
ADD KEY `idx_manuals_bought_copy_status` (`copy_status`),
ADD KEY `idx_manuals_bought_manual_buyer_copy_status` (`manual_id`, `buyer`, `copy_status`);