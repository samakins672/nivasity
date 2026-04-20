-- Audit wallet purchases that only succeeded because a misclassified wallet funding credit existed.
--
-- Usage:
-- 1. Replace the sample refs in tmp_suspect_funding_refs with the provider_reference values
--    returned by sql/audit_misclassified_wallet_funding_transactions.sql.
-- 2. Run this script.
-- 3. Any rows returned are wallet purchases that should be reviewed and typically reversed
--    before removing the fake wallet funding credit.

DROP TEMPORARY TABLE IF EXISTS `tmp_suspect_funding_refs`;
CREATE TEMPORARY TABLE `tmp_suspect_funding_refs` (
  `provider_reference` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`provider_reference`)
);

INSERT INTO `tmp_suspect_funding_refs` (`provider_reference`) VALUES
  ('nivas_14084_1776688802'),
  ('nivas_11238_1776671686079'),
  ('nivas_12221_1776665825');

DROP TEMPORARY TABLE IF EXISTS `tmp_affected_wallets`;
CREATE TEMPORARY TABLE `tmp_affected_wallets` AS
SELECT DISTINCT f.`wallet_id`
FROM `wallet_funding_transactions` AS f
INNER JOIN `tmp_suspect_funding_refs` AS s
  ON s.`provider_reference` = f.`provider_reference`;

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_replay_events`;
CREATE TEMPORARY TABLE `tmp_wallet_replay_events` AS
SELECT
  wl.`wallet_id`,
  wl.`id` AS `ledger_id`,
  wl.`created_at` AS `event_at`,
  wl.`reference`,
  wl.`provider_reference`,
  wl.`entry_type`,
  wl.`amount`,
  CASE
    WHEN s.`provider_reference` IS NOT NULL THEN 0
    WHEN wl.`entry_type` IN ('credit', 'refund', 'adjustment') THEN wl.`amount`
    WHEN wl.`entry_type` IN ('debit', 'fee') THEN -wl.`amount`
    ELSE 0
  END AS `signed_effect`,
  CASE WHEN wl.`reference` LIKE 'wallet_purchase:%' THEN SUBSTRING(wl.`reference`, 17) END AS `purchase_ref_id`,
  CASE WHEN s.`provider_reference` IS NOT NULL THEN 1 ELSE 0 END AS `excluded_fake_credit`
FROM `wallet_ledger_entries` AS wl
INNER JOIN `tmp_affected_wallets` AS aw
  ON aw.`wallet_id` = wl.`wallet_id`
LEFT JOIN `tmp_suspect_funding_refs` AS s
  ON wl.`reference` = CONCAT('wallet_funding:', s.`provider_reference`)
WHERE wl.`status` = 'posted';

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_replay_ranked`;
CREATE TEMPORARY TABLE `tmp_wallet_replay_ranked` AS
SELECT
  e.*,
  ROW_NUMBER() OVER (
    PARTITION BY e.`wallet_id`
    ORDER BY e.`event_at`, e.`ledger_id`
  ) AS `seq`
FROM `tmp_wallet_replay_events` AS e;

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_breaks`;
CREATE TEMPORARY TABLE `tmp_wallet_purchase_breaks` AS
WITH RECURSIVE `flow` AS (
  SELECT
    r.`wallet_id`,
    r.`ledger_id`,
    r.`event_at`,
    r.`reference`,
    r.`provider_reference`,
    r.`entry_type`,
    r.`amount`,
    r.`signed_effect`,
    r.`purchase_ref_id`,
    r.`excluded_fake_credit`,
    r.`seq`,
    0 AS `simulated_balance_before`,
    CASE
      WHEN r.`signed_effect` < 0 AND 0 < ABS(r.`signed_effect`) THEN 1
      ELSE 0
    END AS `is_unsupported_purchase`,
    CASE
      WHEN r.`signed_effect` < 0 AND 0 < ABS(r.`signed_effect`) THEN 0
      ELSE r.`signed_effect`
    END AS `simulated_balance_after`
  FROM `tmp_wallet_replay_ranked` AS r
  WHERE r.`seq` = 1

  UNION ALL

  SELECT
    r.`wallet_id`,
    r.`ledger_id`,
    r.`event_at`,
    r.`reference`,
    r.`provider_reference`,
    r.`entry_type`,
    r.`amount`,
    r.`signed_effect`,
    r.`purchase_ref_id`,
    r.`excluded_fake_credit`,
    r.`seq`,
    f.`simulated_balance_after` AS `simulated_balance_before`,
    CASE
      WHEN r.`signed_effect` < 0 AND f.`simulated_balance_after` < ABS(r.`signed_effect`) THEN 1
      ELSE 0
    END AS `is_unsupported_purchase`,
    CASE
      WHEN r.`signed_effect` < 0 AND f.`simulated_balance_after` < ABS(r.`signed_effect`) THEN f.`simulated_balance_after`
      ELSE f.`simulated_balance_after` + r.`signed_effect`
    END AS `simulated_balance_after`
  FROM `flow` AS f
  INNER JOIN `tmp_wallet_replay_ranked` AS r
    ON r.`wallet_id` = f.`wallet_id`
   AND r.`seq` = f.`seq` + 1
)
SELECT *
FROM `flow`
WHERE `purchase_ref_id` IS NOT NULL
  AND `is_unsupported_purchase` = 1;

SELECT
  b.`wallet_id`,
  f.`user_id`,
  b.`purchase_ref_id`,
  b.`event_at` AS `wallet_purchase_at`,
  b.`amount` AS `wallet_purchase_amount`,
  b.`simulated_balance_before`,
  t.`id` AS `transaction_id`,
  t.`status` AS `transaction_status`,
  t.`amount` AS `transaction_amount`,
  t.`charge`,
  t.`profit`,
  spl.`id` AS `school_payable_ledger_id`,
  spl.`payable_amount`,
  spl.`settled_amount`,
  COUNT(DISTINCT mb.`id`) AS `manual_rows`,
  COUNT(DISTINCT et.`id`) AS `event_rows`
FROM `tmp_wallet_purchase_breaks` AS b
LEFT JOIN `transactions` AS t
  ON t.`ref_id` = b.`purchase_ref_id`
LEFT JOIN `school_payable_ledger` AS spl
  ON spl.`source_ref_id` = b.`purchase_ref_id`
LEFT JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = b.`purchase_ref_id`
LEFT JOIN `event_tickets` AS et
  ON et.`ref_id` = b.`purchase_ref_id`
LEFT JOIN `wallet_funding_transactions` AS f
  ON f.`wallet_id` = b.`wallet_id`
 AND f.`provider_reference` IN (SELECT `provider_reference` FROM `tmp_suspect_funding_refs`)
GROUP BY
  b.`wallet_id`,
  f.`user_id`,
  b.`purchase_ref_id`,
  b.`event_at`,
  b.`amount`,
  b.`simulated_balance_before`,
  t.`id`,
  t.`status`,
  t.`amount`,
  t.`charge`,
  t.`profit`,
  spl.`id`,
  spl.`payable_amount`,
  spl.`settled_amount`
ORDER BY b.`event_at`, b.`wallet_id`, b.`purchase_ref_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_breaks`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_replay_ranked`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_replay_events`;
DROP TEMPORARY TABLE IF EXISTS `tmp_affected_wallets`;
DROP TEMPORARY TABLE IF EXISTS `tmp_suspect_funding_refs`;