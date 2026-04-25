-- Backfill successful purchase transactions that are missing from school_payable_ledger
-- and verify whether school_internal_wallets still matches ledger state.
--
-- Usage:
-- 1. Optional: insert specific refs into tmp_target_backfill_refs to limit the run.
-- 2. Run the script inside a transaction.
-- 3. Review the preview/result sets.
-- 4. Keep COMMIT to apply changes, or replace COMMIT with ROLLBACK to dry-run.

START TRANSACTION;

-- Fixed cutoff in GMT+1 / Africa-Lagos time.
SET @backfill_cutoff := '2026-04-18 21:00:00';

DROP TEMPORARY TABLE IF EXISTS `tmp_target_backfill_refs`;
CREATE TEMPORARY TABLE `tmp_target_backfill_refs` (
  `ref_id` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`ref_id`)
);

-- Optional targeting. Leave empty to backfill every missing purchase ledger.
-- INSERT INTO `tmp_target_backfill_refs` (`ref_id`) VALUES
--   ('nivas_14129_1776699799150'),
--   ('nivas_14126_1776698067302');

DROP TEMPORARY TABLE IF EXISTS `tmp_latest_success_transactions`;
CREATE TEMPORARY TABLE `tmp_latest_success_transactions` AS
SELECT t.*
FROM `transactions` AS t
INNER JOIN (
  SELECT `ref_id`, MAX(`id`) AS `latest_id`
  FROM `transactions`
  WHERE `status` = 'successful'
    AND `transaction_context` = 'purchase'
    AND `created_at` >= @backfill_cutoff
  GROUP BY `ref_id`
) AS src
  ON src.`latest_id` = t.`id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_manuals_by_ref`;
CREATE TEMPORARY TABLE `tmp_manuals_by_ref` AS
SELECT
  `ref_id`,
  MAX(`buyer`) AS `buyer_user_id`,
  MAX(CASE WHEN `school_id` IS NOT NULL AND `school_id` > 0 THEN `school_id` ELSE NULL END) AS `school_id`
FROM `manuals_bought`
GROUP BY `ref_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_events_by_ref`;
CREATE TEMPORARY TABLE `tmp_events_by_ref` AS
SELECT
  `ref_id`,
  MAX(`buyer`) AS `buyer_user_id`
FROM `event_tickets`
GROUP BY `ref_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_cart_by_ref`;
CREATE TEMPORARY TABLE `tmp_cart_by_ref` AS
SELECT
  c.`ref_id`,
  MAX(c.`user_id`) AS `cart_user_id`,
  MAX(CASE WHEN u.`school` IS NOT NULL AND u.`school` > 0 THEN u.`school` ELSE NULL END) AS `school_id`
FROM `cart` AS c
LEFT JOIN `users` AS u
  ON u.`id` = c.`user_id`
GROUP BY c.`ref_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_refund_consumption_by_ref`;
CREATE TEMPORARY TABLE `tmp_refund_consumption_by_ref` AS
SELECT
  rr.`ref_id`,
  ROUND(COALESCE(SUM(rr.`amount`), 0)) AS `refund_consumed_amount`,
  COUNT(DISTINCT r.`ref_id`) AS `refund_source_count`,
  MAX(r.`ref_id`) AS `refund_consumption_source_ref_id`
FROM `refund_reservations` AS rr
INNER JOIN `refunds` AS r
  ON r.`id` = rr.`refund_id`
WHERE rr.`status` = 'consumed'
GROUP BY rr.`ref_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_missing_school_payable_candidates`;
CREATE TEMPORARY TABLE `tmp_missing_school_payable_candidates` AS
SELECT
  tx.`id` AS `transaction_id`,
  tx.`ref_id`,
  COALESCE(NULLIF(tx.`user_id`, 0), mb.`buyer_user_id`, et.`buyer_user_id`, cb.`cart_user_id`) AS `payer_user_id`,
  COALESCE(mb.`school_id`, u.`school`, cb.`school_id`) AS `school_id`,
  UPPER(COALESCE(NULLIF(tx.`medium`, ''), 'NIVASITY')) AS `source_medium`,
  'repair' AS `source_channel`,
  GREATEST(0, ROUND(COALESCE(tx.`amount`, 0) - COALESCE(tx.`charge`, 0))) AS `item_subtotal`,
  ROUND(COALESCE(tx.`amount`, 0)) AS `collected_total`,
  ROUND(COALESCE(tx.`charge`, 0)) AS `charge_amount`,
  COALESCE(trc.`refund_consumption_source_ref_id`, '') AS `refund_consumption_source_ref_id`,
  ROUND(COALESCE(trc.`refund_consumed_amount`, 0)) AS `refund_consumed_amount`,
  ROUND(COALESCE(tx.`refund`, 0)) AS `refund_amount`,
  GREATEST(0, ROUND(COALESCE(tx.`amount`, 0) - COALESCE(tx.`charge`, 0) - COALESCE(tx.`refund`, 0) - COALESCE(trc.`refund_consumed_amount`, 0))) AS `payable_amount`,
  COALESCE(trc.`refund_source_count`, 0) AS `refund_source_count`,
  COALESCE(tx.`payment_channel`, '') AS `payment_channel`,
  COALESCE(tx.`transaction_context`, '') AS `transaction_context`,
  tx.`created_at`
FROM `tmp_latest_success_transactions` AS tx
LEFT JOIN `school_payable_ledger` AS spl
  ON spl.`source_ref_id` = tx.`ref_id`
LEFT JOIN `tmp_manuals_by_ref` AS mb
  ON mb.`ref_id` = tx.`ref_id`
LEFT JOIN `tmp_events_by_ref` AS et
  ON et.`ref_id` = tx.`ref_id`
LEFT JOIN `tmp_cart_by_ref` AS cb
  ON cb.`ref_id` = tx.`ref_id`
LEFT JOIN `tmp_refund_consumption_by_ref` AS trc
  ON trc.`ref_id` = tx.`ref_id`
LEFT JOIN `users` AS u
  ON u.`id` = tx.`user_id`
WHERE spl.`id` IS NULL
  AND (
    (SELECT COUNT(*) FROM `tmp_target_backfill_refs`) = 0
    OR EXISTS (
      SELECT 1
      FROM `tmp_target_backfill_refs` AS trg
      WHERE trg.`ref_id` = tx.`ref_id`
    )
  );

DROP TEMPORARY TABLE IF EXISTS `tmp_missing_school_payable_unresolved`;
CREATE TEMPORARY TABLE `tmp_missing_school_payable_unresolved` AS
SELECT *
FROM `tmp_missing_school_payable_candidates`
WHERE COALESCE(`payer_user_id`, 0) <= 0
   OR COALESCE(`school_id`, 0) <= 0
   OR COALESCE(`item_subtotal`, 0) <= 0;

DROP TEMPORARY TABLE IF EXISTS `tmp_missing_school_payable_ready`;
CREATE TEMPORARY TABLE `tmp_missing_school_payable_ready` AS
SELECT *
FROM `tmp_missing_school_payable_candidates`
WHERE `ref_id` NOT IN (
  SELECT `ref_id`
  FROM `tmp_missing_school_payable_unresolved`
);

SELECT
  'backfill_candidates' AS `result_type`,
  @backfill_cutoff AS `cutoff_datetime`,
  COUNT(*) AS `row_count`,
  COALESCE(SUM(`item_subtotal`), 0) AS `item_subtotal_total`,
  COALESCE(SUM(`refund_amount`), 0) AS `refund_total`,
  COALESCE(SUM(`payable_amount`), 0) AS `payable_total`
FROM `tmp_missing_school_payable_candidates`;

SELECT
  'backfill_ready_by_school' AS `result_type`,
  r.`school_id`,
  s.`name` AS `school_name`,
  COUNT(*) AS `missing_rows`,
  SUM(r.`item_subtotal`) AS `item_subtotal_total`,
  SUM(r.`payable_amount`) AS `payable_total`
FROM `tmp_missing_school_payable_ready` AS r
LEFT JOIN `schools` AS s
  ON s.`id` = r.`school_id`
GROUP BY r.`school_id`, s.`name`
ORDER BY `payable_total` DESC, r.`school_id` ASC;

SELECT
  'unresolved_candidates' AS `result_type`,
  u.`ref_id`,
  u.`transaction_id`,
  u.`payer_user_id`,
  u.`school_id`,
  u.`item_subtotal`,
  u.`source_medium`,
  u.`payment_channel`,
  u.`transaction_context`,
  u.`created_at`
FROM `tmp_missing_school_payable_unresolved` AS u
ORDER BY u.`created_at` ASC, u.`ref_id` ASC;

INSERT INTO `school_internal_wallets` (`school_id`)
SELECT DISTINCT r.`school_id`
FROM `tmp_missing_school_payable_ready` AS r
LEFT JOIN `school_internal_wallets` AS siw
  ON siw.`school_id` = r.`school_id`
WHERE r.`school_id` > 0
  AND siw.`id` IS NULL;

INSERT INTO `school_payable_ledger` (
  `school_id`, `source_ref_id`, `payer_user_id`, `source_medium`, `source_channel`,
  `item_subtotal`, `collected_total`, `charge_amount`, `refund_consumption_source_ref_id`,
  `refund_consumed_amount`, `refund_amount`, `payable_amount`, `metadata`
)
SELECT
  r.`school_id`,
  r.`ref_id`,
  r.`payer_user_id`,
  r.`source_medium`,
  r.`source_channel`,
  r.`item_subtotal`,
  r.`collected_total`,
  r.`charge_amount`,
  NULLIF(r.`refund_consumption_source_ref_id`, ''),
  r.`refund_consumed_amount`,
  r.`refund_amount`,
  r.`payable_amount`,
  JSON_OBJECT(
    'handler', 'sql/backfill_missing_school_payable_ledgers.sql',
    'repair_type', 'backfill_missing_school_payable_ledger',
    'transaction_id', r.`transaction_id`,
    'payment_channel', r.`payment_channel`,
    'transaction_context', r.`transaction_context`,
    'refund_consumption_source_ref_id', NULLIF(r.`refund_consumption_source_ref_id`, ''),
    'refund_consumed_amount', r.`refund_consumed_amount`,
    'refund_source_count', r.`refund_source_count`,
    'original_created_at', DATE_FORMAT(r.`created_at`, '%Y-%m-%d %H:%i:%s')
  )
FROM `tmp_missing_school_payable_ready` AS r;

UPDATE `school_internal_wallets` AS siw
INNER JOIN (
  SELECT `school_id`, SUM(`payable_amount`) AS `added_payable_amount`
  FROM `tmp_missing_school_payable_ready`
  GROUP BY `school_id`
) AS src
  ON src.`school_id` = siw.`school_id`
SET siw.`current_balance` = siw.`current_balance` + src.`added_payable_amount`,
    siw.`pending_payout_balance` = siw.`pending_payout_balance` + src.`added_payable_amount`,
    siw.`updated_at` = NOW();

SELECT
  'inserted_ledger_rows' AS `result_type`,
  COUNT(*) AS `row_count`,
  COALESCE(SUM(`payable_amount`), 0) AS `payable_total`
FROM `tmp_missing_school_payable_ready`;

SELECT
  'still_missing_after_backfill' AS `result_type`,
  COUNT(*) AS `row_count`
FROM `tmp_missing_school_payable_candidates` AS c
LEFT JOIN `school_payable_ledger` AS spl
  ON spl.`source_ref_id` = c.`ref_id`
WHERE spl.`id` IS NULL;

DROP TEMPORARY TABLE IF EXISTS `tmp_expected_school_wallets`;
CREATE TEMPORARY TABLE `tmp_expected_school_wallets` AS
SELECT
  spl.`school_id`,
  SUM(GREATEST(COALESCE(spl.`payable_amount`, 0) - COALESCE(spl.`settled_amount`, 0), 0)) AS `expected_current_balance`,
  SUM(GREATEST(COALESCE(spl.`payable_amount`, 0) - COALESCE(spl.`settled_amount`, 0), 0)) AS `expected_pending_payout_balance`,
  SUM(COALESCE(spl.`carry_forward_amount`, 0)) AS `expected_carry_forward_balance`,
  COUNT(*) AS `ledger_row_count`
FROM `school_payable_ledger` AS spl
GROUP BY spl.`school_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_school_wallet_scope`;
CREATE TEMPORARY TABLE `tmp_school_wallet_scope` AS
SELECT `school_id` FROM `school_internal_wallets`
UNION
SELECT `school_id` FROM `tmp_expected_school_wallets`;

SELECT
  'wallet_mismatches' AS `result_type`,
  scope.`school_id`,
  s.`name` AS `school_name`,
  COALESCE(siw.`current_balance`, 0) AS `actual_current_balance`,
  COALESCE(exp.`expected_current_balance`, 0) AS `expected_current_balance`,
  COALESCE(siw.`pending_payout_balance`, 0) AS `actual_pending_payout_balance`,
  COALESCE(exp.`expected_pending_payout_balance`, 0) AS `expected_pending_payout_balance`,
  COALESCE(siw.`carry_forward_balance`, 0) AS `actual_carry_forward_balance`,
  COALESCE(exp.`expected_carry_forward_balance`, 0) AS `expected_carry_forward_balance`,
  COALESCE(exp.`ledger_row_count`, 0) AS `ledger_row_count`
FROM `tmp_school_wallet_scope` AS scope
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

-- Optional full wallet rebuild from ledger. Only run if the wallet_mismatches result set is non-empty
-- and you want school_internal_wallets to be reset to ledger-derived truth.
--
-- INSERT INTO `school_internal_wallets` (`school_id`)
-- SELECT scope.`school_id`
-- FROM `tmp_school_wallet_scope` AS scope
-- LEFT JOIN `school_internal_wallets` AS siw
--   ON siw.`school_id` = scope.`school_id`
-- WHERE siw.`id` IS NULL;
--
-- UPDATE `school_internal_wallets` AS siw
-- LEFT JOIN `tmp_expected_school_wallets` AS exp
--   ON exp.`school_id` = siw.`school_id`
-- SET siw.`current_balance` = COALESCE(exp.`expected_current_balance`, 0),
--     siw.`pending_payout_balance` = COALESCE(exp.`expected_pending_payout_balance`, 0),
--     siw.`carry_forward_balance` = COALESCE(exp.`expected_carry_forward_balance`, 0),
--     siw.`updated_at` = NOW();

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `tmp_school_wallet_scope`;
DROP TEMPORARY TABLE IF EXISTS `tmp_expected_school_wallets`;
DROP TEMPORARY TABLE IF EXISTS `tmp_missing_school_payable_ready`;
DROP TEMPORARY TABLE IF EXISTS `tmp_missing_school_payable_unresolved`;
DROP TEMPORARY TABLE IF EXISTS `tmp_missing_school_payable_candidates`;
DROP TEMPORARY TABLE IF EXISTS `tmp_cart_by_ref`;
DROP TEMPORARY TABLE IF EXISTS `tmp_events_by_ref`;
DROP TEMPORARY TABLE IF EXISTS `tmp_manuals_by_ref`;
DROP TEMPORARY TABLE IF EXISTS `tmp_latest_success_transactions`;
DROP TEMPORARY TABLE IF EXISTS `tmp_target_backfill_refs`;