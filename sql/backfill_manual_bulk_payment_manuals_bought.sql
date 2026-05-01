-- Backfill missing manuals_bought rows for successful bulk material payment students.
--
-- Safe to re-run.
--
-- This repair script:
-- 1. targets successful bulk-payment student rows that do not have any manuals_bought row
--    for the same ref_id, manual_id, and school_id
-- 2. skips rejected student claims
-- 3. resolves the beneficiary buyer from stored ids or current users table data
-- 4. inserts missing manuals_bought rows
-- 5. updates manual_bulk_payment_students.manuals_bought_id to the resolved purchase row
-- 6. prints skipped and unresolved rows for follow-up
--
-- Prerequisite: sql/add_manual_bulk_payments.sql must already be applied.
-- manuals_bought is MyISAM in the current dump, so this script is written to be idempotent
-- instead of relying on transactional rollback.

START TRANSACTION;

SET @has_payer_user_id := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'manuals_bought'
    AND COLUMN_NAME = 'payer_user_id'
);

SET @add_payer_user_id_sql := IF(
  @has_payer_user_id = 0,
  'ALTER TABLE `manuals_bought` ADD COLUMN `payer_user_id` int(11) DEFAULT NULL AFTER `buyer`',
  'SELECT ''manuals_bought.payer_user_id already exists'' AS note'
);

PREPARE stmt_add_payer_user_id FROM @add_payer_user_id_sql;
EXECUTE stmt_add_payer_user_id;
DEALLOCATE PREPARE stmt_add_payer_user_id;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_scan`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_bought_scan` AS
SELECT
  s.`id` AS `student_row_id`,
  s.`batch_id`,
  s.`ref_id`,
  s.`manual_id`,
  s.`school_id`,
  s.`payer_user_id`,
  s.`payer_dept_id`,
  s.`first_name`,
  s.`last_name`,
  s.`raw_matric_no`,
  s.`normalized_first_name`,
  s.`normalized_last_name`,
  s.`normalized_matric_no`,
  s.`pending_lookup_matric_no`,
  COALESCE(s.`manuals_bought_id`, 0) AS `current_manuals_bought_id`,
  COALESCE(s.`placeholder_user_id`, 0) AS `placeholder_user_id`,
  COALESCE(s.`matched_user_id`, 0) AS `matched_user_id`,
  COALESCE(
    s.`placeholder_user_id`,
    (
      SELECT u.`id`
      FROM `users` AS u
      WHERE u.`school` = s.`school_id`
        AND u.`dept` = s.`payer_dept_id`
        AND LOWER(TRIM(COALESCE(u.`status`, ''))) = 'pending_bulk_claim'
        AND LOWER(TRIM(COALESCE(u.`matric_no`, ''))) = LOWER(TRIM(COALESCE(s.`pending_lookup_matric_no`, '')))
        AND LOWER(TRIM(COALESCE(u.`first_name`, ''))) = LOWER(TRIM(COALESCE(s.`normalized_first_name`, '')))
        AND LOWER(TRIM(COALESCE(u.`last_name`, ''))) = LOWER(TRIM(COALESCE(s.`normalized_last_name`, '')))
      ORDER BY u.`id` DESC
      LIMIT 1
    ),
    0
  ) AS `resolved_placeholder_user_id`,
  COALESCE(
    s.`matched_user_id`,
    (
      SELECT u.`id`
      FROM `users` AS u
      WHERE u.`school` = s.`school_id`
        AND LOWER(TRIM(COALESCE(u.`status`, ''))) <> 'pending_bulk_claim'
        AND LOWER(TRIM(COALESCE(u.`matric_no`, ''))) = LOWER(TRIM(COALESCE(s.`normalized_matric_no`, '')))
      ORDER BY
        CASE WHEN COALESCE(u.`dept`, 0) = s.`payer_dept_id` THEN 0 ELSE 1 END,
        CASE
          WHEN LOWER(TRIM(COALESCE(u.`first_name`, ''))) = LOWER(TRIM(COALESCE(s.`normalized_first_name`, '')))
            OR LOWER(TRIM(COALESCE(u.`first_name`, ''))) = LOWER(TRIM(COALESCE(s.`normalized_last_name`, '')))
            OR LOWER(TRIM(COALESCE(u.`last_name`, ''))) = LOWER(TRIM(COALESCE(s.`normalized_first_name`, '')))
            OR LOWER(TRIM(COALESCE(u.`last_name`, ''))) = LOWER(TRIM(COALESCE(s.`normalized_last_name`, '')))
          THEN 0 ELSE 1 END,
        CASE WHEN LOWER(TRIM(COALESCE(u.`status`, ''))) = 'verified' THEN 0 ELSE 1 END,
        u.`id` DESC
      LIMIT 1
    ),
    0
  ) AS `resolved_matched_user_id`,
  LOWER(TRIM(COALESCE(s.`claim_status`, ''))) AS `claim_status`,
  COALESCE(NULLIF(b.`manual_seller_id`, 0), m.`user_id`, 0) AS `seller_user_id`,
  CASE
    WHEN COALESCE(b.`student_count`, 0) > 0 THEN ROUND(COALESCE(b.`subtotal`, 0) / b.`student_count`)
    ELSE 0
  END AS `unit_price`
FROM `manual_bulk_payment_students` AS s
INNER JOIN `manual_bulk_payment_batches` AS b
  ON b.`id` = s.`batch_id`
INNER JOIN `manuals` AS m
  ON m.`id` = s.`manual_id`
WHERE b.`payment_status` = 'successful'
  AND LOWER(TRIM(COALESCE(s.`claim_status`, ''))) <> 'student_rejected';

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_target_scan`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_bought_target_scan` AS
SELECT
  c.*,
  CASE
    WHEN c.`claim_status` = 'confirmed' AND c.`resolved_matched_user_id` > 0
      THEN c.`resolved_matched_user_id`
    WHEN c.`claim_status` = 'awaiting_student_confirmation' AND c.`resolved_matched_user_id` > 0
      THEN c.`resolved_matched_user_id`
    WHEN c.`claim_status` IN ('pending', 'awaiting_claim_confirmation') AND c.`resolved_placeholder_user_id` > 0
      THEN c.`resolved_placeholder_user_id`
    WHEN c.`resolved_matched_user_id` > 0 AND c.`claim_status` NOT IN ('pending', 'awaiting_claim_confirmation')
      THEN c.`resolved_matched_user_id`
    WHEN c.`resolved_placeholder_user_id` > 0
      THEN c.`resolved_placeholder_user_id`
    ELSE 0
  END AS `target_buyer_user_id`
FROM `tmp_manual_bulk_bought_scan` AS c;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_missing_rows`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_bought_missing_rows` AS
SELECT c.*
FROM `tmp_manual_bulk_bought_target_scan` AS c
LEFT JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = c.`ref_id`
 AND mb.`manual_id` = c.`manual_id`
 AND mb.`school_id` = c.`school_id`
WHERE mb.`id` IS NULL;

SELECT COUNT(*) AS `bulk_manuals_bought_scan_rows`
FROM `tmp_manual_bulk_bought_target_scan`;

SELECT COUNT(*) AS `bulk_manuals_bought_missing_rows`
FROM `tmp_manual_bulk_bought_missing_rows`;

SELECT COUNT(*) AS `bulk_manuals_bought_skipped_no_target_buyer`
FROM `tmp_manual_bulk_bought_missing_rows`
WHERE `target_buyer_user_id` <= 0;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_backfill_candidates`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_bought_backfill_candidates` AS
SELECT *
FROM `tmp_manual_bulk_bought_missing_rows`
WHERE `target_buyer_user_id` > 0;

SELECT COUNT(*) AS `bulk_manuals_bought_candidates`
FROM `tmp_manual_bulk_bought_backfill_candidates`;

INSERT INTO `manuals_bought` (
  `manual_id`, `price`, `seller`, `buyer`, `payer_user_id`, `ref_id`, `status`, `school_id`
)
SELECT
  c.`manual_id`,
  c.`unit_price`,
  c.`seller_user_id`,
  c.`target_buyer_user_id`,
  c.`payer_user_id`,
  c.`ref_id`,
  'successful',
  c.`school_id`
FROM `tmp_manual_bulk_bought_backfill_candidates` AS c;

SELECT ROW_COUNT() AS `bulk_manuals_bought_inserted`;

UPDATE `manual_bulk_payment_students` AS s
INNER JOIN `tmp_manual_bulk_bought_backfill_candidates` AS c
  ON c.`student_row_id` = s.`id`
INNER JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = c.`ref_id`
 AND mb.`manual_id` = c.`manual_id`
 AND mb.`school_id` = c.`school_id`
 AND mb.`buyer` = c.`target_buyer_user_id`
SET s.`manuals_bought_id` = mb.`id`
WHERE s.`manuals_bought_id` IS NULL
   OR s.`manuals_bought_id` <> mb.`id`;

SELECT COUNT(*) AS `bulk_manuals_bought_unresolved_after_backfill`
FROM `tmp_manual_bulk_bought_backfill_candidates` AS c
LEFT JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = c.`ref_id`
 AND mb.`manual_id` = c.`manual_id`
 AND mb.`school_id` = c.`school_id`
 AND mb.`buyer` = c.`target_buyer_user_id`
LEFT JOIN `manual_bulk_payment_students` AS s
  ON s.`id` = c.`student_row_id`
WHERE mb.`id` IS NULL
   OR COALESCE(s.`manuals_bought_id`, 0) <> mb.`id`;

SELECT
  c.`student_row_id`,
  c.`batch_id`,
  c.`ref_id`,
  c.`manual_id`,
  c.`school_id`,
  c.`first_name`,
  c.`last_name`,
  c.`raw_matric_no`,
  c.`claim_status`,
  c.`payer_user_id`,
  c.`placeholder_user_id`,
  c.`matched_user_id`,
  c.`resolved_placeholder_user_id`,
  c.`resolved_matched_user_id`,
  c.`target_buyer_user_id`,
  c.`seller_user_id`,
  c.`unit_price`,
  s.`manuals_bought_id`,
  mb.`id` AS `resolved_manuals_bought_id`
FROM `tmp_manual_bulk_bought_backfill_candidates` AS c
LEFT JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = c.`ref_id`
 AND mb.`manual_id` = c.`manual_id`
 AND mb.`school_id` = c.`school_id`
 AND mb.`buyer` = c.`target_buyer_user_id`
LEFT JOIN `manual_bulk_payment_students` AS s
  ON s.`id` = c.`student_row_id`
WHERE mb.`id` IS NULL
   OR COALESCE(s.`manuals_bought_id`, 0) <> mb.`id`
ORDER BY c.`batch_id` ASC, c.`student_row_id` ASC;

SELECT
  c.`student_row_id`,
  c.`batch_id`,
  c.`ref_id`,
  c.`manual_id`,
  c.`school_id`,
  c.`first_name`,
  c.`last_name`,
  c.`raw_matric_no`,
  c.`claim_status`,
  c.`payer_user_id`,
  c.`placeholder_user_id`,
  c.`matched_user_id`,
  c.`resolved_placeholder_user_id`,
  c.`resolved_matched_user_id`,
  c.`target_buyer_user_id`,
  c.`seller_user_id`,
  c.`unit_price`,
  'target_buyer_missing' AS `skip_reason`
FROM `tmp_manual_bulk_bought_missing_rows` AS c
WHERE c.`target_buyer_user_id` <= 0
ORDER BY c.`batch_id` ASC, c.`student_row_id` ASC;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_backfill_candidates`;
DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_missing_rows`;
DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_target_scan`;
DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_scan`;

-- Keep COMMIT to finalize this repair.
-- Replace COMMIT with ROLLBACK before running if you only want a dry run.
-- Note: manuals_bought is MyISAM in the current dump, so ROLLBACK will not undo
-- inserts or updates already applied to that table.
COMMIT;