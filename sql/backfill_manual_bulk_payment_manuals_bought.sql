-- Backfill missing manuals_bought rows for successful bulk material payment students.
--
-- Safe to re-run.
--
-- This repair script:
-- 1. targets successful bulk-payment student rows whose manuals_bought link is missing or broken
-- 2. skips rejected student claims
-- 3. reuses any existing manuals_bought row for the same student ref/manual/buyer
-- 4. reassigns old placeholder purchases to the confirmed student when needed
-- 5. inserts only the remaining missing manuals_bought rows
-- 6. updates manual_bulk_payment_students.manuals_bought_id to the resolved purchase row
-- 7. fixes student-level transactions.user_id so each bulk student ref points at the beneficiary account
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

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_student_transaction_targets`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_student_transaction_targets` AS
SELECT
  s.`id` AS `student_row_id`,
  s.`ref_id`,
  s.`payer_user_id`,
  COALESCE(s.`placeholder_user_id`, 0) AS `placeholder_user_id`,
  COALESCE(s.`matched_user_id`, 0) AS `matched_user_id`,
  LOWER(TRIM(COALESCE(s.`claim_status`, ''))) AS `claim_status`,
  CASE
    WHEN LOWER(TRIM(COALESCE(s.`claim_status`, ''))) = 'confirmed' AND COALESCE(s.`matched_user_id`, 0) > 0
      THEN s.`matched_user_id`
    WHEN LOWER(TRIM(COALESCE(s.`claim_status`, ''))) = 'awaiting_student_confirmation' AND COALESCE(s.`matched_user_id`, 0) > 0
      THEN s.`matched_user_id`
    WHEN LOWER(TRIM(COALESCE(s.`claim_status`, ''))) = 'awaiting_claim_confirmation' AND COALESCE(s.`matched_user_id`, 0) > 0
      THEN s.`matched_user_id`
    WHEN COALESCE(s.`placeholder_user_id`, 0) > 0
      THEN s.`placeholder_user_id`
    WHEN COALESCE(s.`matched_user_id`, 0) > 0
      THEN s.`matched_user_id`
    ELSE s.`payer_user_id`
  END AS `target_user_id`
FROM `manual_bulk_payment_students` AS s
INNER JOIN `manual_bulk_payment_batches` AS b
  ON b.`id` = s.`batch_id`
WHERE b.`payment_status` = 'successful'
  AND LOWER(TRIM(COALESCE(s.`claim_status`, ''))) <> 'student_rejected';

UPDATE `transactions` AS t
INNER JOIN `tmp_manual_bulk_student_transaction_targets` AS src
  ON src.`ref_id` = t.`ref_id`
SET t.`user_id` = src.`target_user_id`
WHERE src.`target_user_id` > 0
  AND t.`user_id` <> src.`target_user_id`;

SELECT ROW_COUNT() AS `bulk_student_transactions_fixed`;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_backfill_candidates`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_bought_backfill_candidates` AS
SELECT
  s.`id` AS `student_row_id`,
  s.`batch_id`,
  s.`ref_id`,
  s.`manual_id`,
  s.`school_id`,
  s.`payer_user_id`,
  COALESCE(s.`placeholder_user_id`, 0) AS `placeholder_user_id`,
  COALESCE(s.`matched_user_id`, 0) AS `matched_user_id`,
  LOWER(TRIM(COALESCE(s.`claim_status`, ''))) AS `claim_status`,
  COALESCE(NULLIF(b.`manual_seller_id`, 0), m.`user_id`) AS `seller_user_id`,
  CASE
    WHEN COALESCE(b.`student_count`, 0) > 0 THEN ROUND(COALESCE(b.`subtotal`, 0) / b.`student_count`)
    ELSE 0
  END AS `unit_price`,
  CASE
    WHEN LOWER(TRIM(COALESCE(s.`claim_status`, ''))) = 'confirmed' AND COALESCE(s.`matched_user_id`, 0) > 0
      THEN s.`matched_user_id`
    WHEN LOWER(TRIM(COALESCE(s.`claim_status`, ''))) IN ('pending', 'awaiting_claim_confirmation')
         AND COALESCE(s.`placeholder_user_id`, 0) > 0
      THEN s.`placeholder_user_id`
    WHEN COALESCE(s.`matched_user_id`, 0) > 0
      THEN s.`matched_user_id`
    WHEN COALESCE(s.`placeholder_user_id`, 0) > 0
      THEN s.`placeholder_user_id`
    ELSE 0
  END AS `target_buyer_user_id`
FROM `manual_bulk_payment_students` AS s
INNER JOIN `manual_bulk_payment_batches` AS b
  ON b.`id` = s.`batch_id`
INNER JOIN `manuals` AS m
  ON m.`id` = s.`manual_id`
LEFT JOIN `manuals_bought` AS linked
  ON linked.`id` = s.`manuals_bought_id`
WHERE b.`payment_status` = 'successful'
  AND LOWER(TRIM(COALESCE(s.`claim_status`, ''))) <> 'student_rejected'
  AND (
    s.`manuals_bought_id` IS NULL
    OR linked.`id` IS NULL
  )
  AND COALESCE(NULLIF(b.`manual_seller_id`, 0), m.`user_id`, 0) > 0
  AND (
    CASE
      WHEN LOWER(TRIM(COALESCE(s.`claim_status`, ''))) = 'confirmed' AND COALESCE(s.`matched_user_id`, 0) > 0
        THEN s.`matched_user_id`
      WHEN LOWER(TRIM(COALESCE(s.`claim_status`, ''))) IN ('pending', 'awaiting_claim_confirmation')
           AND COALESCE(s.`placeholder_user_id`, 0) > 0
        THEN s.`placeholder_user_id`
      WHEN COALESCE(s.`matched_user_id`, 0) > 0
        THEN s.`matched_user_id`
      WHEN COALESCE(s.`placeholder_user_id`, 0) > 0
        THEN s.`placeholder_user_id`
      ELSE 0
    END
  ) > 0;

SELECT COUNT(*) AS `bulk_manuals_bought_candidates`
FROM `tmp_manual_bulk_bought_backfill_candidates`;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_target_matches`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_bought_target_matches` AS
SELECT
  c.`student_row_id`,
  MIN(mb.`id`) AS `manuals_bought_id`
FROM `tmp_manual_bulk_bought_backfill_candidates` AS c
INNER JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = c.`ref_id`
 AND mb.`manual_id` = c.`manual_id`
 AND mb.`buyer` = c.`target_buyer_user_id`
 AND LOWER(TRIM(COALESCE(mb.`status`, 'successful'))) = 'successful'
GROUP BY c.`student_row_id`;

UPDATE `manual_bulk_payment_students` AS s
INNER JOIN `tmp_manual_bulk_bought_target_matches` AS tm
  ON tm.`student_row_id` = s.`id`
SET s.`manuals_bought_id` = tm.`manuals_bought_id`
WHERE s.`manuals_bought_id` IS NULL
   OR s.`manuals_bought_id` <> tm.`manuals_bought_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_placeholder_reassign`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_bought_placeholder_reassign` AS
SELECT
  c.`student_row_id`,
  MIN(mb.`id`) AS `manuals_bought_id`,
  c.`target_buyer_user_id`,
  c.`payer_user_id`
FROM `tmp_manual_bulk_bought_backfill_candidates` AS c
LEFT JOIN `tmp_manual_bulk_bought_target_matches` AS tm
  ON tm.`student_row_id` = c.`student_row_id`
INNER JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = c.`ref_id`
 AND mb.`manual_id` = c.`manual_id`
 AND mb.`buyer` = c.`placeholder_user_id`
 AND LOWER(TRIM(COALESCE(mb.`status`, 'successful'))) = 'successful'
WHERE tm.`manuals_bought_id` IS NULL
  AND c.`claim_status` = 'confirmed'
  AND c.`placeholder_user_id` > 0
  AND c.`matched_user_id` > 0
  AND c.`target_buyer_user_id` = c.`matched_user_id`
GROUP BY
  c.`student_row_id`,
  c.`target_buyer_user_id`,
  c.`payer_user_id`;

UPDATE `manuals_bought` AS mb
INNER JOIN `tmp_manual_bulk_bought_placeholder_reassign` AS src
  ON src.`manuals_bought_id` = mb.`id`
SET mb.`buyer` = src.`target_buyer_user_id`,
    mb.`payer_user_id` = src.`payer_user_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_target_matches`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_bought_target_matches` AS
SELECT
  c.`student_row_id`,
  MIN(mb.`id`) AS `manuals_bought_id`
FROM `tmp_manual_bulk_bought_backfill_candidates` AS c
INNER JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = c.`ref_id`
 AND mb.`manual_id` = c.`manual_id`
 AND mb.`buyer` = c.`target_buyer_user_id`
 AND LOWER(TRIM(COALESCE(mb.`status`, 'successful'))) = 'successful'
GROUP BY c.`student_row_id`;

UPDATE `manual_bulk_payment_students` AS s
INNER JOIN `tmp_manual_bulk_bought_target_matches` AS tm
  ON tm.`student_row_id` = s.`id`
SET s.`manuals_bought_id` = tm.`manuals_bought_id`
WHERE s.`manuals_bought_id` IS NULL
   OR s.`manuals_bought_id` <> tm.`manuals_bought_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_needing_insert`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_bought_needing_insert` AS
SELECT c.*
FROM `tmp_manual_bulk_bought_backfill_candidates` AS c
LEFT JOIN `tmp_manual_bulk_bought_target_matches` AS tm
  ON tm.`student_row_id` = c.`student_row_id`
WHERE tm.`manuals_bought_id` IS NULL;

SELECT COUNT(*) AS `bulk_manuals_bought_rows_to_insert`
FROM `tmp_manual_bulk_bought_needing_insert`;

INSERT INTO `manuals_bought` (
  `manual_id`, `price`, `seller`, `buyer`, `payer_user_id`, `ref_id`, `status`, `school_id`
)
SELECT
  src.`manual_id`,
  src.`unit_price`,
  src.`seller_user_id`,
  src.`target_buyer_user_id`,
  src.`payer_user_id`,
  src.`ref_id`,
  'successful',
  src.`school_id`
FROM `tmp_manual_bulk_bought_needing_insert` AS src;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_target_matches`;
CREATE TEMPORARY TABLE `tmp_manual_bulk_bought_target_matches` AS
SELECT
  c.`student_row_id`,
  MIN(mb.`id`) AS `manuals_bought_id`
FROM `tmp_manual_bulk_bought_backfill_candidates` AS c
INNER JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = c.`ref_id`
 AND mb.`manual_id` = c.`manual_id`
 AND mb.`buyer` = c.`target_buyer_user_id`
 AND LOWER(TRIM(COALESCE(mb.`status`, 'successful'))) = 'successful'
GROUP BY c.`student_row_id`;

UPDATE `manual_bulk_payment_students` AS s
INNER JOIN `tmp_manual_bulk_bought_target_matches` AS tm
  ON tm.`student_row_id` = s.`id`
SET s.`manuals_bought_id` = tm.`manuals_bought_id`
WHERE s.`manuals_bought_id` IS NULL
   OR s.`manuals_bought_id` <> tm.`manuals_bought_id`;

SELECT COUNT(*) AS `bulk_manuals_bought_unresolved_after_backfill`
FROM `tmp_manual_bulk_bought_backfill_candidates` AS c
INNER JOIN `manual_bulk_payment_students` AS s
  ON s.`id` = c.`student_row_id`
LEFT JOIN `manuals_bought` AS mb
  ON mb.`id` = s.`manuals_bought_id`
WHERE s.`manuals_bought_id` IS NULL
   OR mb.`id` IS NULL;

DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_needing_insert`;
DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_placeholder_reassign`;
DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_target_matches`;
DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_bought_backfill_candidates`;
DROP TEMPORARY TABLE IF EXISTS `tmp_manual_bulk_student_transaction_targets`;

-- Keep COMMIT to finalize this repair.
-- Replace COMMIT with ROLLBACK before running if you only want a dry run.
-- Note: manuals_bought is MyISAM in the current dump, so ROLLBACK will not undo
-- inserts or updates already applied to that table.
COMMIT;