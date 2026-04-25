-- Normalize existing school_payable_ledger rows so the refund fields mean:
-- 1. refund_amount = refunds against the ledger row's own source_ref_id.
-- 2. refund_consumed_amount = foreign or legacy refund consumed into this row.
-- 3. refund_consumption_source_ref_id = the single bound foreign refund source ref, or NULL.
-- 4. payable_amount = item_subtotal - refund_amount - refund_consumed_amount.
--
-- This script also resyncs school_internal_wallets for affected schools.
--
-- Usage:
-- 1. Optional: insert specific refs into tmp_target_normalize_refs to limit the run.
-- 2. Run inside a maintenance window.
-- 3. Review the preview/result sets.
-- 4. Keep COMMIT to apply changes, or replace COMMIT with ROLLBACK to dry-run.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS `tmp_target_normalize_refs`;
CREATE TEMPORARY TABLE `tmp_target_normalize_refs` (
  `ref_id` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ref_id`)
);

-- Optional targeting. Leave empty to normalize every eligible ledger row.
-- INSERT INTO `tmp_target_normalize_refs` (`ref_id`) VALUES
--   ('nivas_example_ref_1'),
--   ('nivas_example_ref_2');

DROP TEMPORARY TABLE IF EXISTS `tmp_native_refund_by_source_ref`;
CREATE TEMPORARY TABLE `tmp_native_refund_by_source_ref` AS
SELECT
  CONVERT(r.`ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci AS `ref_id`,
  ROUND(COALESCE(SUM(r.`amount`), 0)) AS `refund_total`,
  COUNT(DISTINCT r.`id`) AS `refund_row_count`
FROM `refunds` AS r
WHERE r.`ref_id` IS NOT NULL
  AND r.`ref_id` <> ''
  AND COALESCE(r.`status`, '') <> 'cancelled'
GROUP BY CONVERT(r.`ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci;

DROP TEMPORARY TABLE IF EXISTS `tmp_consumed_refund_by_ledger`;
CREATE TEMPORARY TABLE `tmp_consumed_refund_by_ledger` AS
SELECT
  COALESCE(rr.`school_payable_ledger_id`, spl.`id`) AS `school_payable_ledger_id`,
  ROUND(COALESCE(SUM(rr.`amount`), 0)) AS `consumed_total`,
  COUNT(*) AS `consumed_row_count`,
  COUNT(DISTINCT CONVERT(r.`ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci) AS `source_ref_count`,
  MAX(CONVERT(r.`ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci) AS `single_source_ref_id`
FROM `refund_reservations` AS rr
INNER JOIN `refunds` AS r
  ON r.`id` = rr.`refund_id`
LEFT JOIN `school_payable_ledger` AS spl
  ON CONVERT(spl.`source_ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(rr.`ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci
WHERE (
    (rr.`school_payable_ledger_id` IS NOT NULL AND rr.`school_payable_ledger_id` > 0)
    OR spl.`id` IS NOT NULL
  )
  AND rr.`status` = 'consumed'
GROUP BY COALESCE(rr.`school_payable_ledger_id`, spl.`id`);

DROP TEMPORARY TABLE IF EXISTS `tmp_school_payable_refund_normalization_base`;
CREATE TEMPORARY TABLE `tmp_school_payable_refund_normalization_base` AS
SELECT
  spl.`id` AS `ledger_id`,
  spl.`school_id`,
  CONVERT(spl.`source_ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci AS `source_ref_id`,
  COALESCE(spl.`item_subtotal`, 0) AS `item_subtotal`,
  COALESCE(spl.`refund_amount`, 0) AS `current_refund_amount`,
  COALESCE(spl.`refund_consumed_amount`, 0) AS `current_refund_consumed_amount`,
  COALESCE(NULLIF(CONVERT(spl.`refund_consumption_source_ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), '') AS `current_refund_consumption_source_ref_id`,
  COALESCE(spl.`payable_amount`, 0) AS `current_payable_amount`,
  COALESCE(spl.`settled_amount`, 0) AS `settled_amount`,
  COALESCE(spl.`carry_forward_amount`, 0) AS `current_carry_forward_amount`,
  CONVERT(COALESCE(spl.`status`, 'pending') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS `current_status`,
  COALESCE(native_rf.`refund_total`, 0) AS `expected_refund_amount`,
  COALESCE(consumed_rf.`consumed_total`, 0) AS `expected_refund_consumed_amount`,
  (CASE
    WHEN COALESCE(consumed_rf.`source_ref_count`, 0) = 1 THEN COALESCE(consumed_rf.`single_source_ref_id`, '')
    ELSE ''
  END) COLLATE utf8mb4_unicode_ci AS `expected_refund_consumption_source_ref_id`,
  COALESCE(consumed_rf.`source_ref_count`, 0) AS `expected_consumed_source_ref_count`,
  GREATEST(
    0,
    ROUND(
      COALESCE(spl.`item_subtotal`, 0)
      - COALESCE(native_rf.`refund_total`, 0)
      - COALESCE(consumed_rf.`consumed_total`, 0)
    )
  ) AS `expected_payable_amount`
FROM `school_payable_ledger` AS spl
LEFT JOIN `tmp_native_refund_by_source_ref` AS native_rf
  ON native_rf.`ref_id` = CONVERT(spl.`source_ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci
LEFT JOIN `tmp_consumed_refund_by_ledger` AS consumed_rf
  ON consumed_rf.`school_payable_ledger_id` = spl.`id`
WHERE COALESCE(spl.`status`, '') <> 'reversed'
  AND (
    (SELECT COUNT(*) FROM `tmp_target_normalize_refs`) = 0
    OR EXISTS (
      SELECT 1
      FROM `tmp_target_normalize_refs` AS trg
      WHERE trg.`ref_id` = CONVERT(spl.`source_ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci
    )
  );

DROP TEMPORARY TABLE IF EXISTS `tmp_school_payable_refund_normalization_candidates`;
CREATE TEMPORARY TABLE `tmp_school_payable_refund_normalization_candidates` AS
SELECT
  base.*,
  GREATEST(0, ROUND(base.`settled_amount` - base.`expected_payable_amount`)) AS `expected_carry_forward_amount`,
  CASE
    WHEN base.`expected_payable_amount` <= 0
      AND GREATEST(0, ROUND(base.`settled_amount` - base.`expected_payable_amount`)) > 0
      THEN 'carry_forward'
    WHEN base.`settled_amount` > 0
      AND base.`settled_amount` < base.`expected_payable_amount`
      THEN 'partially_settled'
    WHEN base.`settled_amount` >= base.`expected_payable_amount`
      AND base.`expected_payable_amount` > 0
      THEN 'settled'
    ELSE 'pending'
  END AS `expected_status`,
  GREATEST(
    0,
    ROUND(
      base.`expected_refund_amount`
      + base.`expected_refund_consumed_amount`
      - base.`item_subtotal`
    )
  ) AS `over_deducted_amount`
FROM `tmp_school_payable_refund_normalization_base` AS base
WHERE ROUND(COALESCE(base.`current_refund_amount`, 0)) <> ROUND(COALESCE(base.`expected_refund_amount`, 0))
   OR ROUND(COALESCE(base.`current_refund_consumed_amount`, 0)) <> ROUND(COALESCE(base.`expected_refund_consumed_amount`, 0))
   OR COALESCE(base.`current_refund_consumption_source_ref_id`, '') COLLATE utf8mb4_unicode_ci <> COALESCE(base.`expected_refund_consumption_source_ref_id`, '') COLLATE utf8mb4_unicode_ci
   OR ROUND(COALESCE(base.`current_payable_amount`, 0)) <> ROUND(COALESCE(base.`expected_payable_amount`, 0))
   OR ROUND(COALESCE(base.`current_carry_forward_amount`, 0)) <> ROUND(GREATEST(0, ROUND(base.`settled_amount` - base.`expected_payable_amount`)))
   OR COALESCE(base.`current_status`, 'pending') COLLATE utf8mb4_unicode_ci <> (CASE
     WHEN base.`expected_payable_amount` <= 0
       AND GREATEST(0, ROUND(base.`settled_amount` - base.`expected_payable_amount`)) > 0
       THEN 'carry_forward'
     WHEN base.`settled_amount` > 0
       AND base.`settled_amount` < base.`expected_payable_amount`
       THEN 'partially_settled'
     WHEN base.`settled_amount` >= base.`expected_payable_amount`
       AND base.`expected_payable_amount` > 0
       THEN 'settled'
     ELSE 'pending'
   END) COLLATE utf8mb4_unicode_ci;

SELECT
  'ledger_refund_normalization_candidates' AS `result_type`,
  COUNT(*) AS `candidate_rows`,
  COALESCE(SUM(`current_refund_amount`), 0) AS `current_refund_total`,
  COALESCE(SUM(`expected_refund_amount`), 0) AS `expected_refund_total`,
  COALESCE(SUM(`current_refund_consumed_amount`), 0) AS `current_refund_consumed_total`,
  COALESCE(SUM(`expected_refund_consumed_amount`), 0) AS `expected_refund_consumed_total`,
  COALESCE(SUM(`current_payable_amount`), 0) AS `current_payable_total`,
  COALESCE(SUM(`expected_payable_amount`), 0) AS `expected_payable_total`
FROM `tmp_school_payable_refund_normalization_candidates`;

SELECT *
FROM `tmp_school_payable_refund_normalization_candidates`
ORDER BY ABS(`expected_payable_amount` - `current_payable_amount`) DESC, `ledger_id` DESC;

SELECT
  'multi_source_consumption_rows' AS `result_type`,
  `ledger_id`,
  `school_id`,
  `source_ref_id`,
  `current_refund_amount`,
  `expected_refund_amount`,
  `current_refund_consumed_amount`,
  `expected_refund_consumed_amount`,
  `expected_consumed_source_ref_count`,
  `current_refund_consumption_source_ref_id`
FROM `tmp_school_payable_refund_normalization_candidates`
WHERE `expected_consumed_source_ref_count` > 1
ORDER BY `expected_refund_consumed_amount` DESC, `ledger_id` DESC;

SELECT
  'over_deducted_rows' AS `result_type`,
  `ledger_id`,
  `school_id`,
  `source_ref_id`,
  `item_subtotal`,
  `expected_refund_amount`,
  `expected_refund_consumed_amount`,
  `over_deducted_amount`
FROM `tmp_school_payable_refund_normalization_candidates`
WHERE `over_deducted_amount` > 0
ORDER BY `over_deducted_amount` DESC, `ledger_id` DESC;

UPDATE `school_payable_ledger` AS spl
INNER JOIN `tmp_school_payable_refund_normalization_candidates` AS c
  ON c.`ledger_id` = spl.`id`
SET spl.`refund_amount` = c.`expected_refund_amount`,
    spl.`refund_consumed_amount` = c.`expected_refund_consumed_amount`,
    spl.`refund_consumption_source_ref_id` = NULLIF(c.`expected_refund_consumption_source_ref_id`, ''),
    spl.`payable_amount` = c.`expected_payable_amount`,
    spl.`carry_forward_amount` = c.`expected_carry_forward_amount`,
    spl.`status` = c.`expected_status`,
    spl.`updated_at` = NOW();

SELECT ROW_COUNT() AS `updated_ledger_rows`;

SELECT
  COUNT(*) AS `remaining_ledger_mismatch_rows`
FROM `school_payable_ledger` AS spl
INNER JOIN `tmp_school_payable_refund_normalization_candidates` AS c
  ON c.`ledger_id` = spl.`id`
WHERE ROUND(COALESCE(spl.`refund_amount`, 0)) <> ROUND(COALESCE(c.`expected_refund_amount`, 0))
   OR ROUND(COALESCE(spl.`refund_consumed_amount`, 0)) <> ROUND(COALESCE(c.`expected_refund_consumed_amount`, 0))
  OR COALESCE(NULLIF(CONVERT(spl.`refund_consumption_source_ref_id` USING utf8mb4) COLLATE utf8mb4_unicode_ci, ''), '') <> COALESCE(c.`expected_refund_consumption_source_ref_id`, '') COLLATE utf8mb4_unicode_ci
   OR ROUND(COALESCE(spl.`payable_amount`, 0)) <> ROUND(COALESCE(c.`expected_payable_amount`, 0))
   OR ROUND(COALESCE(spl.`carry_forward_amount`, 0)) <> ROUND(COALESCE(c.`expected_carry_forward_amount`, 0))
  OR CONVERT(COALESCE(spl.`status`, 'pending') USING utf8mb4) COLLATE utf8mb4_unicode_ci <> COALESCE(c.`expected_status`, 'pending') COLLATE utf8mb4_unicode_ci;

DROP TEMPORARY TABLE IF EXISTS `tmp_affected_school_wallets`;
CREATE TEMPORARY TABLE `tmp_affected_school_wallets` AS
SELECT DISTINCT `school_id`
FROM `tmp_school_payable_refund_normalization_candidates`
WHERE `school_id` IS NOT NULL
  AND `school_id` > 0;

INSERT INTO `school_internal_wallets` (
  `school_id`, `current_balance`, `pending_payout_balance`, `carry_forward_balance`, `currency`, `status`
)
SELECT
  scope.`school_id`,
  0,
  0,
  0,
  'NGN',
  'active'
FROM `tmp_affected_school_wallets` AS scope
LEFT JOIN `school_internal_wallets` AS siw
  ON siw.`school_id` = scope.`school_id`
WHERE siw.`id` IS NULL;

DROP TEMPORARY TABLE IF EXISTS `tmp_expected_school_wallets`;
CREATE TEMPORARY TABLE `tmp_expected_school_wallets` AS
SELECT
  spl.`school_id`,
  SUM(GREATEST(COALESCE(spl.`payable_amount`, 0) - COALESCE(spl.`settled_amount`, 0), 0)) AS `expected_current_balance`,
  SUM(GREATEST(COALESCE(spl.`payable_amount`, 0) - COALESCE(spl.`settled_amount`, 0), 0)) AS `expected_pending_payout_balance`,
  SUM(COALESCE(spl.`carry_forward_amount`, 0)) AS `expected_carry_forward_balance`,
  COUNT(*) AS `ledger_row_count`
FROM `school_payable_ledger` AS spl
INNER JOIN `tmp_affected_school_wallets` AS scope
  ON scope.`school_id` = spl.`school_id`
GROUP BY spl.`school_id`;

SELECT
  'wallet_adjustments_after_normalization' AS `result_type`,
  scope.`school_id`,
  s.`name` AS `school_name`,
  COALESCE(siw.`current_balance`, 0) AS `actual_current_balance`,
  COALESCE(exp.`expected_current_balance`, 0) AS `expected_current_balance`,
  COALESCE(siw.`pending_payout_balance`, 0) AS `actual_pending_payout_balance`,
  COALESCE(exp.`expected_pending_payout_balance`, 0) AS `expected_pending_payout_balance`,
  COALESCE(siw.`carry_forward_balance`, 0) AS `actual_carry_forward_balance`,
  COALESCE(exp.`expected_carry_forward_balance`, 0) AS `expected_carry_forward_balance`,
  COALESCE(exp.`ledger_row_count`, 0) AS `ledger_row_count`
FROM `tmp_affected_school_wallets` AS scope
LEFT JOIN `school_internal_wallets` AS siw
  ON siw.`school_id` = scope.`school_id`
LEFT JOIN `tmp_expected_school_wallets` AS exp
  ON exp.`school_id` = scope.`school_id`
LEFT JOIN `schools` AS s
  ON s.`id` = scope.`school_id`
WHERE COALESCE(siw.`current_balance`, 0) <> COALESCE(exp.`expected_current_balance`, 0)
   OR COALESCE(siw.`pending_payout_balance`, 0) <> COALESCE(exp.`expected_pending_payout_balance`, 0)
   OR COALESCE(siw.`carry_forward_balance`, 0) <> COALESCE(exp.`expected_carry_forward_balance`, 0)
ORDER BY scope.`school_id` ASC;

UPDATE `school_internal_wallets` AS siw
INNER JOIN `tmp_expected_school_wallets` AS exp
  ON exp.`school_id` = siw.`school_id`
SET siw.`current_balance` = COALESCE(exp.`expected_current_balance`, 0),
    siw.`pending_payout_balance` = COALESCE(exp.`expected_pending_payout_balance`, 0),
    siw.`carry_forward_balance` = COALESCE(exp.`expected_carry_forward_balance`, 0),
    siw.`updated_at` = NOW();

SELECT ROW_COUNT() AS `updated_school_wallet_rows`;

SELECT
  COUNT(*) AS `remaining_wallet_mismatch_rows`
FROM `tmp_affected_school_wallets` AS scope
LEFT JOIN `school_internal_wallets` AS siw
  ON siw.`school_id` = scope.`school_id`
LEFT JOIN `tmp_expected_school_wallets` AS exp
  ON exp.`school_id` = scope.`school_id`
WHERE COALESCE(siw.`current_balance`, 0) <> COALESCE(exp.`expected_current_balance`, 0)
   OR COALESCE(siw.`pending_payout_balance`, 0) <> COALESCE(exp.`expected_pending_payout_balance`, 0)
   OR COALESCE(siw.`carry_forward_balance`, 0) <> COALESCE(exp.`expected_carry_forward_balance`, 0);

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `tmp_expected_school_wallets`;
DROP TEMPORARY TABLE IF EXISTS `tmp_affected_school_wallets`;
DROP TEMPORARY TABLE IF EXISTS `tmp_school_payable_refund_normalization_candidates`;
DROP TEMPORARY TABLE IF EXISTS `tmp_school_payable_refund_normalization_base`;
DROP TEMPORARY TABLE IF EXISTS `tmp_consumed_refund_by_ledger`;
DROP TEMPORARY TABLE IF EXISTS `tmp_native_refund_by_source_ref`;
DROP TEMPORARY TABLE IF EXISTS `tmp_target_normalize_refs`;
