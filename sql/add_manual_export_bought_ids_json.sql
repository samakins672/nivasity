-- Add explicit bought-row list for export audits.
-- Stores the exact manuals_bought.id values included in an export.

ALTER TABLE `manual_export_audits`
ADD COLUMN `bought_ids_json` LONGTEXT DEFAULT NULL COMMENT 'JSON array of manuals_bought.id included in this export';
