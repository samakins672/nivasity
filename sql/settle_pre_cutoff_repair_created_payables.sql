-- Historically settle repair-created school payable rows whose original purchases
-- predate the ledger cutoff but were backfilled later.
--
-- Use this only when you have confirmed that purchases before the cutoff were
-- already paid to schools outside the current school_payable_ledger flow.
--
-- Strategy:
-- 1. identify repair-created ledger rows tied to successful purchases before the cutoff
-- 2. require them to still be fully unsettled and absent from settlement_batch_items
-- 3. guard that school_internal_wallets can absorb the settlement reduction
-- 4. create one completed historical settlement batch per school for audit trail
-- 5. create settled settlement_batch_items for each affected ledger row
-- 6. mark the ledger rows as settled
-- 7. subtract the settled amount from school_internal_wallets
--
-- Recommended workflow:
-- 1. run the review query first or keep the preview SELECTs below
-- 2. replace COMMIT with ROLLBACK for a dry-run if needed
-- 3. inspect the post-run verification queries before keeping the changes

START TRANSACTION;

SET @historical_purchase_cutoff := '2026-04-18 21:00:00';
SET @repair_window_start := '2026-04-18 21:00:00';
SET @historical_settlement_note := CONCAT(
  'Historical settlement alignment for repair-created payables. ',
  'Original purchase before ', @historical_purchase_cutoff,
  ', ledger repair on or after ', @repair_window_start,
  '. Applied because pre-cutoff purchases were already settled outside current ledger flow.'
);

DROP TEMPORARY TABLE IF EXISTS `tmp_target_historical_repair_refs`;
CREATE TEMPORARY TABLE `tmp_target_historical_repair_refs` (
  `ref_id` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Optional targeting. Leave empty to settle every qualifying repair-created row.
-- INSERT INTO `tmp_target_historical_repair_refs` (`ref_id`) VALUES
--   ('nivas_10469_1776177552995'),
--   ('nivas_7077_1775512519747');

DROP TEMPORARY TABLE IF EXISTS `tmp_pre_cutoff_repair_settlement_candidates`;
CREATE TEMPORARY TABLE `tmp_pre_cutoff_repair_settlement_candidates` AS
SELECT
  spl.`id` AS `school_payable_ledger_id`,
  spl.`school_id`,
  spl.`source_ref_id`,
  spl.`payer_user_id`,
  spl.`source_medium`,
  spl.`source_channel`,
  spl.`item_subtotal`,
  spl.`collected_total`,
  spl.`charge_amount`,
  spl.`refund_consumption_source_ref_id`,
  spl.`refund_consumed_amount`,
  spl.`refund_amount`,
  spl.`payable_amount`,
  spl.`settled_amount`,
  spl.`carry_forward_amount`,
  spl.`status` AS `ledger_status`,
  spl.`created_at` AS `ledger_created_at`,
  spl.`updated_at` AS `ledger_updated_at`,
  spl.`metadata`,
  tx.`id` AS `transaction_id`,
  tx.`created_at` AS `original_purchase_at`,
  tx.`payment_channel`,
  tx.`transaction_context`
FROM `school_payable_ledger` AS spl
INNER JOIN `transactions` AS tx
  ON tx.`ref_id` = spl.`source_ref_id`
 AND tx.`transaction_context` = 'purchase'
LEFT JOIN `settlement_batch_items` AS sbi
  ON sbi.`school_payable_ledger_id` = spl.`id`
 AND sbi.`status` <> 'failed'
WHERE tx.`status` = 'successful'
  AND tx.`created_at` < @historical_purchase_cutoff
  AND spl.`created_at` >= @repair_window_start
  AND spl.`payable_amount` > 0
  AND spl.`settled_amount` = 0
  AND spl.`status` = 'pending'
  AND spl.`metadata` IS NOT NULL
  AND spl.`metadata` LIKE '%"repair_source":"nivasityEnsureSchoolPayableForPurchase"%'
  AND sbi.`id` IS NULL
  AND (
    (SELECT COUNT(*) FROM `tmp_target_historical_repair_refs`) = 0
    OR EXISTS (
      SELECT 1
      FROM `tmp_target_historical_repair_refs` AS trg
      WHERE trg.`ref_id` = spl.`source_ref_id`
    )
  );


-- Preview the candidate set.
SELECT
  COUNT(*) AS `candidate_rows`,
  COALESCE(SUM(`payable_amount`), 0) AS `candidate_payable_total`
FROM `tmp_pre_cutoff_repair_settlement_candidates`;

SELECT
  c.`school_id`,
  s.`name` AS `school_name`,
  COUNT(*) AS `candidate_rows`,
  COALESCE(SUM(c.`payable_amount`), 0) AS `candidate_payable_total`,
  MIN(c.`original_purchase_at`) AS `earliest_original_purchase_at`,
  MAX(c.`original_purchase_at`) AS `latest_original_purchase_at`,
  MIN(c.`ledger_created_at`) AS `earliest_ledger_created_at`,
  MAX(c.`ledger_created_at`) AS `latest_ledger_created_at`
FROM `tmp_pre_cutoff_repair_settlement_candidates` AS c
LEFT JOIN `schools` AS s
  ON s.`id` = c.`school_id`
GROUP BY c.`school_id`, s.`name`
ORDER BY c.`school_id` ASC;

SELECT
  c.`school_id`,
  c.`source_ref_id`,
  c.`transaction_id`,
  c.`original_purchase_at`,
  c.`ledger_created_at`,
  c.`item_subtotal`,
  c.`refund_consumption_source_ref_id`,
  c.`refund_consumed_amount`,
  c.`refund_amount`,
  c.`payable_amount`,
  c.`ledger_status`
FROM `tmp_pre_cutoff_repair_settlement_candidates` AS c
ORDER BY c.`school_id` ASC, c.`original_purchase_at` ASC, c.`source_ref_id` ASC;


-- Guard: ensure school wallets can absorb the settlement amount.
DROP TEMPORARY TABLE IF EXISTS `tmp_pre_cutoff_repair_settlement_issues`;
CREATE TEMPORARY TABLE `tmp_pre_cutoff_repair_settlement_issues` AS
SELECT
  needed.`school_id`,
  needed.`candidate_payable_total`,
  siw.`id` AS `school_wallet_id`,
  siw.`current_balance`,
  siw.`pending_payout_balance`
FROM (
  SELECT
    `school_id`,
    SUM(`payable_amount`) AS `candidate_payable_total`
  FROM `tmp_pre_cutoff_repair_settlement_candidates`
  GROUP BY `school_id`
) AS needed
LEFT JOIN `school_internal_wallets` AS siw
  ON siw.`school_id` = needed.`school_id`
WHERE siw.`id` IS NULL
   OR siw.`current_balance` < needed.`candidate_payable_total`
   OR siw.`pending_payout_balance` < needed.`candidate_payable_total`;

SELECT *
FROM `tmp_pre_cutoff_repair_settlement_issues`;


-- Build one historical settlement batch per school.
DROP TEMPORARY TABLE IF EXISTS `tmp_pre_cutoff_repair_settlement_batches`;
CREATE TEMPORARY TABLE `tmp_pre_cutoff_repair_settlement_batches` AS
SELECT
  c.`school_id`,
  COUNT(*) AS `total_records`,
  SUM(c.`payable_amount`) AS `total_amount`,
  CONCAT(
    'hist_repair_',
    c.`school_id`,
    '_',
    DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')
  ) COLLATE utf8mb4_general_ci AS `batch_reference`
FROM `tmp_pre_cutoff_repair_settlement_candidates` AS c
GROUP BY c.`school_id`;

INSERT INTO `settlement_batches` (
  `school_id`,
  `scheduled_for`,
  `batch_reference`,
  `status`,
  `total_amount`,
  `total_records`,
  `transfer_provider`,
  `provider_reference`,
  `provider_response`,
  `started_at`,
  `completed_at`,
  `notes`
)
SELECT
  b.`school_id`,
  DATE(@historical_purchase_cutoff),
  b.`batch_reference`,
  'completed',
  b.`total_amount`,
  b.`total_records`,
  'historical_sql_alignment',
  b.`batch_reference`,
  JSON_OBJECT(
    'script', 'sql/settle_pre_cutoff_repair_created_payables.sql',
    'historical_purchase_cutoff', @historical_purchase_cutoff,
    'repair_window_start', @repair_window_start,
    'reason', 'pre_cutoff_purchases_already_paid_outside_current_ledger_flow'
  ),
  NOW(),
  NOW(),
  @historical_settlement_note
FROM `tmp_pre_cutoff_repair_settlement_batches` AS b
INNER JOIN (
  SELECT COUNT(*) AS `issue_count`
  FROM `tmp_pre_cutoff_repair_settlement_issues`
) AS guard
WHERE guard.`issue_count` = 0;

SELECT ROW_COUNT() AS `settlement_batches_inserted`;


-- Attach every targeted ledger row to its school's historical settlement batch.
INSERT INTO `settlement_batch_items` (
  `settlement_batch_id`,
  `school_payable_ledger_id`,
  `source_ref_id`,
  `allocated_amount`,
  `status`,
  `notes`
)
SELECT
  sb.`id` AS `settlement_batch_id`,
  c.`school_payable_ledger_id`,
  c.`source_ref_id`,
  c.`payable_amount`,
  'settled',
  @historical_settlement_note
FROM `tmp_pre_cutoff_repair_settlement_candidates` AS c
INNER JOIN `tmp_pre_cutoff_repair_settlement_batches` AS b
  ON b.`school_id` = c.`school_id`
INNER JOIN `settlement_batches` AS sb
  ON sb.`batch_reference` COLLATE utf8mb4_general_ci = b.`batch_reference` COLLATE utf8mb4_general_ci
INNER JOIN (
  SELECT COUNT(*) AS `issue_count`
  FROM `tmp_pre_cutoff_repair_settlement_issues`
) AS guard
WHERE guard.`issue_count` = 0;

SELECT ROW_COUNT() AS `settlement_batch_items_inserted`;


-- Mark the ledger rows as historically settled.
UPDATE `school_payable_ledger` AS spl
INNER JOIN `tmp_pre_cutoff_repair_settlement_candidates` AS c
  ON c.`school_payable_ledger_id` = spl.`id`
INNER JOIN (
  SELECT COUNT(*) AS `issue_count`
  FROM `tmp_pre_cutoff_repair_settlement_issues`
) AS guard
SET spl.`settled_amount` = spl.`payable_amount`,
    spl.`status` = 'settled',
    spl.`updated_at` = NOW()
WHERE guard.`issue_count` = 0;

SELECT ROW_COUNT() AS `school_payable_rows_settled`;


-- Remove the now-settled amount from school_internal_wallets.
UPDATE `school_internal_wallets` AS siw
INNER JOIN (
  SELECT
    `school_id`,
    SUM(`payable_amount`) AS `settled_payable_total`
  FROM `tmp_pre_cutoff_repair_settlement_candidates`
  GROUP BY `school_id`
) AS src
  ON src.`school_id` = siw.`school_id`
INNER JOIN (
  SELECT COUNT(*) AS `issue_count`
  FROM `tmp_pre_cutoff_repair_settlement_issues`
) AS guard
SET siw.`current_balance` = siw.`current_balance` - src.`settled_payable_total`,
    siw.`pending_payout_balance` = siw.`pending_payout_balance` - src.`settled_payable_total`,
    siw.`updated_at` = NOW()
WHERE guard.`issue_count` = 0;

SELECT ROW_COUNT() AS `school_wallet_rows_adjusted`;


-- Post-fix verification.
SELECT
  COUNT(*) AS `remaining_candidate_rows`,
  COALESCE(SUM(spl.`payable_amount` - spl.`settled_amount`), 0) AS `remaining_outstanding_total`
FROM `school_payable_ledger` AS spl
INNER JOIN `tmp_pre_cutoff_repair_settlement_candidates` AS c
  ON c.`school_payable_ledger_id` = spl.`id`
WHERE spl.`status` <> 'settled'
   OR spl.`settled_amount` <> spl.`payable_amount`;

SELECT
  sb.`id` AS `settlement_batch_id`,
  sb.`school_id`,
  sb.`batch_reference`,
  sb.`status`,
  sb.`total_amount`,
  sb.`total_records`,
  sb.`completed_at`
FROM `settlement_batches` AS sb
INNER JOIN `tmp_pre_cutoff_repair_settlement_batches` AS b
  ON b.`batch_reference` COLLATE utf8mb4_general_ci = sb.`batch_reference` COLLATE utf8mb4_general_ci
ORDER BY sb.`school_id` ASC, sb.`id` ASC;

SELECT
  siw.`school_id`,
  siw.`current_balance`,
  siw.`pending_payout_balance`,
  siw.`carry_forward_balance`
FROM `school_internal_wallets` AS siw
WHERE siw.`school_id` IN (
  SELECT DISTINCT `school_id`
  FROM `tmp_pre_cutoff_repair_settlement_candidates`
)
ORDER BY siw.`school_id` ASC;

COMMIT;