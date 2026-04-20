-- Reverse wallet purchases that only succeeded because a fake wallet funding credit existed.
--
-- Run this AFTER reviewing sql/audit_wallet_purchases_dependent_on_suspect_funding.sql.
-- This script only auto-reverses purchases whose school payable rows are not settled.
-- Review exported/granted/manual-swap side effects before running.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_refs_to_reverse`;
CREATE TEMPORARY TABLE `tmp_wallet_purchase_refs_to_reverse` (
  `ref_id` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`ref_id`)
);

-- Replace the sample value(s) with the wallet purchase ref(s) returned by the audit script.
INSERT INTO `tmp_wallet_purchase_refs_to_reverse` (`ref_id`) VALUES
  ('nivas_14084_1776689605');

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_reverse_candidates`;
CREATE TEMPORARY TABLE `tmp_wallet_purchase_reverse_candidates` AS
SELECT
  p.`ref_id`,
  wl.`id` AS `ledger_id`,
  wl.`wallet_id`,
  uw.`user_id`,
  wl.`amount` AS `wallet_purchase_amount`,
  wl.`created_at` AS `ledger_created_at`,
  t.`id` AS `transaction_id`,
  spl.`id` AS `school_payable_ledger_id`,
  spl.`school_id`,
  COALESCE(spl.`payable_amount`, 0) AS `payable_amount`,
  COALESCE(spl.`settled_amount`, 0) AS `settled_amount`
FROM `tmp_wallet_purchase_refs_to_reverse` AS p
INNER JOIN `wallet_ledger_entries` AS wl
  ON wl.`reference` = CONCAT('wallet_purchase:', p.`ref_id`)
 AND wl.`status` = 'posted'
INNER JOIN `user_wallets` AS uw
  ON uw.`id` = wl.`wallet_id`
LEFT JOIN `transactions` AS t
  ON t.`ref_id` = p.`ref_id`
LEFT JOIN `school_payable_ledger` AS spl
  ON spl.`source_ref_id` = p.`ref_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_reverse_blockers`;
CREATE TEMPORARY TABLE `tmp_wallet_purchase_reverse_blockers` AS
SELECT *
FROM `tmp_wallet_purchase_reverse_candidates`
WHERE `settled_amount` > 0;

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_manual_side_effect_blockers`;
CREATE TEMPORARY TABLE `tmp_wallet_purchase_manual_side_effect_blockers` AS
SELECT DISTINCT
  r.`ref_id`,
  r.`wallet_id`,
  r.`user_id`,
  r.`school_payable_ledger_id`,
  r.`settled_amount`,
  mb.`id` AS `manuals_bought_id`,
  CASE
    WHEN COALESCE(mb.`grant_status`, 0) = 1 THEN 'manual_granted'
    WHEN mb.`export_id` IS NOT NULL THEN 'manual_export_linked'
    WHEN mcl.`id` IS NOT NULL THEN 'manual_changed'
    WHEN mea_granted.`id` IS NOT NULL THEN 'manual_export_granted'
    WHEN mea_range.`id` IS NOT NULL THEN 'manual_export_range_match'
    WHEN mea_json.`id` IS NOT NULL THEN 'manual_export_json_match'
    ELSE 'manual_side_effect'
  END AS `blocker_reason`
FROM `tmp_wallet_purchase_reverse_candidates` AS r
INNER JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = r.`ref_id`
LEFT JOIN `manual_change_logs` AS mcl
  ON mcl.`manuals_bought_id` = mb.`id`
LEFT JOIN `manual_export_audits` AS mea_granted
  ON mea_granted.`id` = mb.`export_id`
 AND LOWER(TRIM(COALESCE(mea_granted.`grant_status`, 'pending'))) = 'granted'
LEFT JOIN `manual_export_audits` AS mea_range
  ON mea_range.`manual_id` = mb.`manual_id`
 AND mb.`id` BETWEEN COALESCE(mea_range.`from_bought_id`, mb.`id`) AND COALESCE(mea_range.`to_bought_id`, mb.`id`)
LEFT JOIN `manual_export_audits` AS mea_json
  ON mea_json.`bought_ids_json` IS NOT NULL
 AND JSON_VALID(mea_json.`bought_ids_json`)
 AND JSON_SEARCH(mea_json.`bought_ids_json`, 'one', CAST(mb.`id` AS CHAR)) IS NOT NULL
WHERE COALESCE(mb.`grant_status`, 0) = 1
   OR mb.`export_id` IS NOT NULL
   OR mcl.`id` IS NOT NULL
   OR mea_granted.`id` IS NOT NULL
   OR mea_range.`id` IS NOT NULL
   OR mea_json.`id` IS NOT NULL;

INSERT INTO `tmp_wallet_purchase_reverse_blockers`
SELECT
  r.`ref_id`,
  r.`ledger_id`,
  r.`wallet_id`,
  r.`user_id`,
  r.`wallet_purchase_amount`,
  r.`ledger_created_at`,
  r.`transaction_id`,
  r.`school_payable_ledger_id`,
  r.`school_id`,
  r.`payable_amount`,
  r.`settled_amount`
FROM `tmp_wallet_purchase_reverse_candidates` AS r
INNER JOIN `tmp_wallet_purchase_manual_side_effect_blockers` AS b
  ON b.`ref_id` = r.`ref_id`
WHERE r.`ref_id` NOT IN (SELECT `ref_id` FROM `tmp_wallet_purchase_reverse_blockers`);

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_reverse_ready`;
CREATE TEMPORARY TABLE `tmp_wallet_purchase_reverse_ready` AS
SELECT *
FROM `tmp_wallet_purchase_reverse_candidates`
WHERE `ref_id` NOT IN (SELECT `ref_id` FROM `tmp_wallet_purchase_reverse_blockers`);

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_reverse_wallets`;
CREATE TEMPORARY TABLE `tmp_wallet_purchase_reverse_wallets` AS
SELECT
  r.*,
  uw.`balance` AS `current_wallet_balance`,
  COALESCE(
    SUM(r.`wallet_purchase_amount`) OVER (
      PARTITION BY r.`wallet_id`
      ORDER BY r.`ledger_created_at`, r.`ledger_id`
      ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
    ),
    0
  ) AS `restored_before`
FROM `tmp_wallet_purchase_reverse_ready` AS r
INNER JOIN `user_wallets` AS uw
  ON uw.`id` = r.`wallet_id`;

INSERT INTO `wallet_ledger_entries` (
  `wallet_id`, `entry_type`, `amount`, `balance_before`, `balance_after`, `status`,
  `reference`, `provider_reference`, `description`, `metadata`
)
SELECT
  r.`wallet_id`,
  'adjustment',
  r.`wallet_purchase_amount`,
  r.`current_wallet_balance` + r.`restored_before`,
  r.`current_wallet_balance` + r.`restored_before` + r.`wallet_purchase_amount`,
  'posted',
  CONCAT('wallet_purchase_reversal:', r.`ref_id`),
  r.`ref_id`,
  'Reversal of invalid wallet purchase after fake wallet funding audit',
  JSON_OBJECT(
    'repair_type', 'invalid_wallet_purchase_reversal',
    'source_ref_id', r.`ref_id`,
    'original_wallet_ledger_id', r.`ledger_id`
  )
FROM `tmp_wallet_purchase_reverse_wallets` AS r;

UPDATE `wallet_ledger_entries` AS wl
INNER JOIN `tmp_wallet_purchase_reverse_ready` AS r
  ON r.`ledger_id` = wl.`id`
SET wl.`status` = 'reversed';

UPDATE `user_wallets` AS uw
INNER JOIN (
  SELECT `wallet_id`, SUM(`wallet_purchase_amount`) AS `restore_amount`
  FROM `tmp_wallet_purchase_reverse_ready`
  GROUP BY `wallet_id`
) AS src
  ON src.`wallet_id` = uw.`id`
SET uw.`balance` = uw.`balance` + src.`restore_amount`,
    uw.`updated_at` = NOW();

UPDATE `transactions` AS t
INNER JOIN `tmp_wallet_purchase_reverse_ready` AS r
  ON r.`transaction_id` = t.`id`
SET t.`status` = 'reversed';

DELETE mb
FROM `manuals_bought` AS mb
INNER JOIN `tmp_wallet_purchase_reverse_ready` AS r
  ON r.`ref_id` = mb.`ref_id`;

DELETE et
FROM `event_tickets` AS et
INNER JOIN `tmp_wallet_purchase_reverse_ready` AS r
  ON r.`ref_id` = et.`ref_id`;

UPDATE `cart` AS c
INNER JOIN `tmp_wallet_purchase_reverse_ready` AS r
  ON r.`ref_id` = c.`ref_id`
SET c.`status` = 'pending';

UPDATE `school_internal_wallets` AS siw
INNER JOIN (
  SELECT `school_id`, SUM(`payable_amount`) AS `reverse_payable_amount`
  FROM `tmp_wallet_purchase_reverse_ready`
  WHERE `school_id` IS NOT NULL
  GROUP BY `school_id`
) AS src
  ON src.`school_id` = siw.`school_id`
SET siw.`current_balance` = GREATEST(0, siw.`current_balance` - src.`reverse_payable_amount`),
    siw.`pending_payout_balance` = GREATEST(0, siw.`pending_payout_balance` - src.`reverse_payable_amount`),
    siw.`updated_at` = NOW();

UPDATE `school_payable_ledger` AS spl
INNER JOIN `tmp_wallet_purchase_reverse_ready` AS r
  ON r.`school_payable_ledger_id` = spl.`id`
SET spl.`status` = 'reversed',
    spl.`payable_amount` = 0,
    spl.`updated_at` = NOW();

SELECT
  'blocked_settled_rows' AS `result_type`,
  b.`ref_id`,
  b.`wallet_id`,
  b.`user_id`,
  b.`school_payable_ledger_id`,
  b.`settled_amount`,
  CASE
    WHEN b.`settled_amount` > 0 THEN 'school_payable_settled'
    ELSE COALESCE(msb.`blocker_reason`, 'unknown_blocker')
  END AS `blocker_reason`
FROM `tmp_wallet_purchase_reverse_blockers` AS b
LEFT JOIN `tmp_wallet_purchase_manual_side_effect_blockers` AS msb
  ON msb.`ref_id` = b.`ref_id`;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_manual_side_effect_blockers`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_reverse_wallets`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_reverse_ready`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_reverse_blockers`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_reverse_candidates`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_refs_to_reverse`;