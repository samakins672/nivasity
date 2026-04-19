-- Recalculate historical wallet purchase profits and wallet funding charge consumption.
--
-- Run order:
-- 1. sql/add_wallet_funding_charge_consumption.sql
-- 2. sql/backfill_wallet_purchase_profits.sql
--
-- This script assumes MySQL 8+/MariaDB 10.2+ because it uses window functions
-- and a recursive CTE to replay wallet funding and purchase events in time order.

START TRANSACTION;

-- Normalize funding charges before replaying recovery.
UPDATE `wallet_funding_transactions`
SET
  `provider_charge_amount` = CASE
    WHEN `provider` = 'paystack' AND `amount` > 0 AND (`provider_charge_amount` IS NULL OR `provider_charge_amount` = 0)
      THEN LEAST(300, ROUND(`amount` * 0.01))
    ELSE COALESCE(`provider_charge_amount`, 0)
  END,
  `consumed_charge_amount` = 0,
  `remaining_charge_amount` = CASE
    WHEN `provider` = 'paystack' AND `amount` > 0 AND (`provider_charge_amount` IS NULL OR `provider_charge_amount` = 0)
      THEN LEAST(300, ROUND(`amount` * 0.01))
    ELSE COALESCE(`provider_charge_amount`, 0)
  END
WHERE `status` = 'posted';

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_tx_refs`;
CREATE TEMPORARY TABLE `tmp_wallet_purchase_tx_refs` AS
SELECT MAX(`id`) AS `transaction_id`, `ref_id`
FROM `transactions`
WHERE `payment_channel` = 'wallet'
  AND `transaction_context` = 'purchase'
GROUP BY `ref_id`;

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_base`;
CREATE TEMPORARY TABLE `tmp_wallet_purchase_base` AS
SELECT
  wl.`id` AS `ledger_id`,
  wl.`wallet_id`,
  wl.`created_at` AS `event_at`,
  COALESCE(NULLIF(wl.`provider_reference`, ''), NULLIF(SUBSTRING(wl.`reference`, 17), '')) AS `ref_id`,
  tx.`id` AS `transaction_id`,
  GREATEST(COALESCE(tx.`charge`, 0), 0) AS `charge_amount`
FROM `wallet_ledger_entries` AS wl
INNER JOIN `tmp_wallet_purchase_tx_refs` AS txr
  ON txr.`ref_id` = COALESCE(NULLIF(wl.`provider_reference`, ''), NULLIF(SUBSTRING(wl.`reference`, 17), ''))
INNER JOIN `transactions` AS tx
  ON tx.`id` = txr.`transaction_id`
WHERE wl.`entry_type` = 'debit'
  AND wl.`reference` LIKE 'wallet_purchase:%';

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_funding_ordered`;
CREATE TEMPORARY TABLE `tmp_wallet_funding_ordered` AS
SELECT
  base.`funding_id`,
  base.`wallet_id`,
  base.`event_at`,
  base.`provider_charge_amount`,
  ROW_NUMBER() OVER (
    PARTITION BY base.`wallet_id`
    ORDER BY base.`event_at`, base.`funding_id`
  ) AS `funding_seq`,
  SUM(base.`provider_charge_amount`) OVER (
    PARTITION BY base.`wallet_id`
    ORDER BY base.`event_at`, base.`funding_id`
    ROWS UNBOUNDED PRECEDING
  ) AS `cumulative_charge_end`,
  SUM(base.`provider_charge_amount`) OVER (
    PARTITION BY base.`wallet_id`
    ORDER BY base.`event_at`, base.`funding_id`
    ROWS UNBOUNDED PRECEDING
  ) - base.`provider_charge_amount` AS `cumulative_charge_start`
FROM (
  SELECT
    f.`id` AS `funding_id`,
    f.`wallet_id`,
    COALESCE(f.`posted_at`, f.`created_at`) AS `event_at`,
    GREATEST(COALESCE(f.`provider_charge_amount`, 0), 0) AS `provider_charge_amount`
  FROM `wallet_funding_transactions` AS f
  WHERE f.`status` = 'posted'
) AS base;

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_events`;
CREATE TEMPORARY TABLE `tmp_wallet_events` AS
SELECT
  f.`wallet_id`,
  f.`event_at`,
  0 AS `type_sort`,
  f.`funding_id` AS `source_id`,
  'funding' AS `event_type`,
  f.`funding_id`,
  NULL AS `ledger_id`,
  NULL AS `transaction_id`,
  NULL AS `ref_id`,
  f.`provider_charge_amount`,
  0 AS `charge_amount`
FROM `tmp_wallet_funding_ordered` AS f
UNION ALL
SELECT
  p.`wallet_id`,
  p.`event_at`,
  1 AS `type_sort`,
  p.`ledger_id` AS `source_id`,
  'purchase' AS `event_type`,
  NULL AS `funding_id`,
  p.`ledger_id`,
  p.`transaction_id`,
  p.`ref_id`,
  0 AS `provider_charge_amount`,
  p.`charge_amount`
FROM `tmp_wallet_purchase_base` AS p;

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_recovery`;
CREATE TEMPORARY TABLE `tmp_wallet_purchase_recovery` (
  `ledger_id` INT(11) NOT NULL,
  `transaction_id` INT(11) NOT NULL,
  `wallet_id` INT(11) NOT NULL,
  `ref_id` VARCHAR(100) NOT NULL,
  `charge_amount` INT(11) NOT NULL DEFAULT 0,
  `outstanding_before` INT(11) NOT NULL DEFAULT 0,
  `recovered_amount` INT(11) NOT NULL DEFAULT 0,
  `profit_amount` INT(11) NOT NULL DEFAULT 0,
  `outstanding_after` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ledger_id`),
  KEY `idx_tmp_wallet_purchase_recovery_tx` (`transaction_id`),
  KEY `idx_tmp_wallet_purchase_recovery_wallet` (`wallet_id`)
);

INSERT INTO `tmp_wallet_purchase_recovery` (
  `ledger_id`, `transaction_id`, `wallet_id`, `ref_id`, `charge_amount`,
  `outstanding_before`, `recovered_amount`, `profit_amount`, `outstanding_after`
)
WITH RECURSIVE ranked_events AS (
  SELECT
    e.*,
    ROW_NUMBER() OVER (
      PARTITION BY e.`wallet_id`
      ORDER BY e.`event_at`, e.`type_sort`, e.`source_id`
    ) AS `seq`
  FROM `tmp_wallet_events` AS e
),
flow AS (
  SELECT
    r.`wallet_id`,
    r.`seq`,
    r.`event_type`,
    r.`funding_id`,
    r.`ledger_id`,
    r.`transaction_id`,
    r.`ref_id`,
    r.`provider_charge_amount`,
    r.`charge_amount`,
    0 AS `outstanding_before`,
    0 AS `recovered_amount`,
    CASE
      WHEN r.`event_type` = 'funding' THEN r.`provider_charge_amount`
      ELSE 0
    END AS `outstanding_after`
  FROM `ranked_events` AS r
  WHERE r.`seq` = 1

  UNION ALL

  SELECT
    r.`wallet_id`,
    r.`seq`,
    r.`event_type`,
    r.`funding_id`,
    r.`ledger_id`,
    r.`transaction_id`,
    r.`ref_id`,
    r.`provider_charge_amount`,
    r.`charge_amount`,
    f.`outstanding_after` AS `outstanding_before`,
    CASE
      WHEN r.`event_type` = 'purchase' THEN LEAST(f.`outstanding_after`, r.`charge_amount`)
      ELSE 0
    END AS `recovered_amount`,
    CASE
      WHEN r.`event_type` = 'funding' THEN f.`outstanding_after` + r.`provider_charge_amount`
      ELSE GREATEST(f.`outstanding_after` - LEAST(f.`outstanding_after`, r.`charge_amount`), 0)
    END AS `outstanding_after`
  FROM `flow` AS f
  INNER JOIN `ranked_events` AS r
    ON r.`wallet_id` = f.`wallet_id`
   AND r.`seq` = f.`seq` + 1
)
SELECT
  f.`ledger_id`,
  f.`transaction_id`,
  f.`wallet_id`,
  COALESCE(f.`ref_id`, '') AS `ref_id`,
  GREATEST(COALESCE(f.`charge_amount`, 0), 0) AS `charge_amount`,
  GREATEST(COALESCE(f.`outstanding_before`, 0), 0) AS `outstanding_before`,
  GREATEST(COALESCE(f.`recovered_amount`, 0), 0) AS `recovered_amount`,
  GREATEST(COALESCE(f.`charge_amount`, 0) - COALESCE(f.`recovered_amount`, 0), 0) AS `profit_amount`,
  GREATEST(COALESCE(f.`outstanding_after`, 0), 0) AS `outstanding_after`
FROM `flow` AS f
WHERE f.`event_type` = 'purchase';

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_total_recovered`;
CREATE TEMPORARY TABLE `tmp_wallet_total_recovered` AS
SELECT
  `wallet_id`,
  COALESCE(SUM(`recovered_amount`), 0) AS `total_recovered_amount`
FROM `tmp_wallet_purchase_recovery`
GROUP BY `wallet_id`;

UPDATE `wallet_funding_transactions` AS f
INNER JOIN `tmp_wallet_funding_ordered` AS o
  ON o.`funding_id` = f.`id`
LEFT JOIN `tmp_wallet_total_recovered` AS r
  ON r.`wallet_id` = o.`wallet_id`
SET
  f.`provider_charge_amount` = o.`provider_charge_amount`,
  f.`consumed_charge_amount` = GREATEST(
    LEAST(o.`cumulative_charge_end`, COALESCE(r.`total_recovered_amount`, 0)) - o.`cumulative_charge_start`,
    0
  ),
  f.`remaining_charge_amount` = GREATEST(
    o.`provider_charge_amount` - GREATEST(
      LEAST(o.`cumulative_charge_end`, COALESCE(r.`total_recovered_amount`, 0)) - o.`cumulative_charge_start`,
      0
    ),
    0
  ),
  f.`updated_at` = NOW();

UPDATE `transactions` AS t
INNER JOIN `tmp_wallet_purchase_recovery` AS p
  ON p.`transaction_id` = t.`id`
SET t.`profit` = p.`profit_amount`;

UPDATE `transactions` AS t
INNER JOIN `wallet_funding_transactions` AS f
  ON f.`provider_reference` = t.`ref_id`
SET
  t.`charge` = f.`provider_charge_amount`,
  t.`profit` = 0
WHERE t.`payment_channel` = 'wallet'
  AND t.`transaction_context` = 'wallet_funding';

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_total_recovered`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_recovery`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_events`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_funding_ordered`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_base`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_purchase_tx_refs`;

COMMIT;