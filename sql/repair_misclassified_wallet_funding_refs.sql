-- Convert misclassified wallet funding refs into proper Paystack purchases and remove the fake wallet credit.
--
-- Run order:
-- 1. sql/audit_wallet_purchases_dependent_on_suspect_funding.sql
-- 2. sql/reverse_invalid_wallet_purchase_refs.sql (if the audit returns downstream invalid wallet purchases)
-- 3. sql/repair_misclassified_wallet_funding_refs.sql
-- 4. sql/backfill_wallet_purchase_profits.sql
--
-- This script assumes the wallet currently has enough balance to remove the fake credit.
-- If it does not, reverse the invalid downstream wallet purchase(s) first.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS `tmp_misclassified_wallet_funding_refs`;
CREATE TEMPORARY TABLE `tmp_misclassified_wallet_funding_refs` (
  `provider_reference` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`provider_reference`)
);

INSERT INTO `tmp_misclassified_wallet_funding_refs` (`provider_reference`) VALUES
  ('nivas_14084_1776688802'),
  ('nivas_11238_1776671686079'),
  ('nivas_12221_1776665825');

DROP TEMPORARY TABLE IF EXISTS `tmp_misclassified_wallet_funding_candidates`;
CREATE TEMPORARY TABLE `tmp_misclassified_wallet_funding_candidates` AS
SELECT
  f.`id` AS `wallet_funding_id`,
  f.`wallet_id`,
  f.`user_id`,
  f.`provider_reference`,
  f.`amount` AS `collected_total`,
  f.`provider_charge_amount`,
  f.`source`,
  f.`posted_at`,
  wl.`id` AS `wallet_credit_ledger_id`
FROM `wallet_funding_transactions` AS f
INNER JOIN `tmp_misclassified_wallet_funding_refs` AS src
  ON src.`provider_reference` = f.`provider_reference`
LEFT JOIN `wallet_ledger_entries` AS wl
  ON wl.`wallet_id` = f.`wallet_id`
 AND wl.`reference` = CONCAT('wallet_funding:', f.`provider_reference`)
 AND wl.`status` = 'posted'
WHERE f.`status` = 'posted';

DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_balance_blockers`;
CREATE TEMPORARY TABLE `tmp_wallet_balance_blockers` AS
SELECT
  uw.`id` AS `wallet_id`,
  uw.`user_id`,
  uw.`balance` AS `current_balance`,
  src.`remove_amount`
FROM `user_wallets` AS uw
INNER JOIN (
  SELECT `wallet_id`, SUM(`collected_total`) AS `remove_amount`
  FROM `tmp_misclassified_wallet_funding_candidates`
  GROUP BY `wallet_id`
) AS src
  ON src.`wallet_id` = uw.`id`
WHERE uw.`balance` < src.`remove_amount`;

DROP TEMPORARY TABLE IF EXISTS `tmp_misclassified_wallet_funding_ready`;
CREATE TEMPORARY TABLE `tmp_misclassified_wallet_funding_ready` AS
SELECT *
FROM `tmp_misclassified_wallet_funding_candidates`
WHERE `wallet_id` NOT IN (SELECT `wallet_id` FROM `tmp_wallet_balance_blockers`);

DROP TEMPORARY TABLE IF EXISTS `tmp_misclassified_wallet_funding_wallets`;
CREATE TEMPORARY TABLE `tmp_misclassified_wallet_funding_wallets` AS
SELECT
  r.*,
  uw.`balance` AS `current_wallet_balance`,
  COALESCE(
    SUM(r.`collected_total`) OVER (
      PARTITION BY r.`wallet_id`
      ORDER BY r.`posted_at`, r.`wallet_funding_id`
      ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
    ),
    0
  ) AS `removed_before`
FROM `tmp_misclassified_wallet_funding_ready` AS r
INNER JOIN `user_wallets` AS uw
  ON uw.`id` = r.`wallet_id`;

INSERT INTO `wallet_ledger_entries` (
  `wallet_id`, `entry_type`, `amount`, `balance_before`, `balance_after`, `status`,
  `reference`, `provider_reference`, `description`, `metadata`
)
SELECT
  r.`wallet_id`,
  'adjustment',
  r.`collected_total`,
  r.`current_wallet_balance` - r.`removed_before`,
  r.`current_wallet_balance` - r.`removed_before` - r.`collected_total`,
  'posted',
  CONCAT('wallet_funding_reversal:', r.`provider_reference`),
  r.`provider_reference`,
  'Reversal of misclassified wallet funding credit',
  JSON_OBJECT(
    'repair_type', 'misclassified_wallet_funding_reversal',
    'provider_reference', r.`provider_reference`,
    'wallet_funding_id', r.`wallet_funding_id`,
    'wallet_credit_ledger_id', r.`wallet_credit_ledger_id`
  )
FROM `tmp_misclassified_wallet_funding_wallets` AS r;

UPDATE `wallet_ledger_entries` AS wl
INNER JOIN `tmp_misclassified_wallet_funding_ready` AS r
  ON r.`wallet_credit_ledger_id` = wl.`id`
SET wl.`status` = 'reversed';

UPDATE `user_wallets` AS uw
INNER JOIN (
  SELECT `wallet_id`, SUM(`collected_total`) AS `remove_amount`
  FROM `tmp_misclassified_wallet_funding_ready`
  GROUP BY `wallet_id`
) AS src
  ON src.`wallet_id` = uw.`id`
SET uw.`balance` = uw.`balance` - src.`remove_amount`,
    uw.`updated_at` = NOW();

UPDATE `wallet_funding_transactions` AS f
INNER JOIN `tmp_misclassified_wallet_funding_ready` AS r
  ON r.`wallet_funding_id` = f.`id`
SET f.`status` = 'reversed',
    f.`consumed_charge_amount` = 0,
    f.`remaining_charge_amount` = 0,
    f.`updated_at` = NOW();

DROP TEMPORARY TABLE IF EXISTS `tmp_repaired_gateway_base`;
CREATE TEMPORARY TABLE `tmp_repaired_gateway_base` AS
SELECT
  r.`provider_reference` AS `ref_id`,
  r.`user_id`,
  r.`wallet_id`,
  u.`school` AS `school_id`,
  r.`collected_total`,
  SUM(
    CASE
      WHEN c.`type` = 'manual' THEN COALESCE(m.`price`, 0)
      WHEN c.`type` = 'event' THEN COALESCE(e.`price`, 0)
      ELSE 0
    END
  ) AS `item_subtotal`
FROM `tmp_misclassified_wallet_funding_ready` AS r
INNER JOIN `users` AS u
  ON u.`id` = r.`user_id`
INNER JOIN `cart` AS c
  ON c.`ref_id` = r.`provider_reference`
 AND c.`user_id` = r.`user_id`
LEFT JOIN `manuals` AS m
  ON c.`type` = 'manual'
 AND m.`id` = c.`item_id`
LEFT JOIN `events` AS e
  ON c.`type` = 'event'
 AND e.`id` = c.`item_id`
GROUP BY
  r.`provider_reference`,
  r.`user_id`,
  r.`wallet_id`,
  u.`school`,
  r.`collected_total`;

DROP TEMPORARY TABLE IF EXISTS `tmp_repaired_gateway_tx`;
CREATE TEMPORARY TABLE `tmp_repaired_gateway_tx` AS
SELECT
  b.`ref_id`,
  b.`user_id`,
  b.`wallet_id`,
  b.`school_id`,
  b.`collected_total`,
  b.`item_subtotal`,
  GREATEST(b.`collected_total` - b.`item_subtotal`, 0) AS `charge_amount`,
  CASE
    WHEN b.`item_subtotal` <= 0 THEN 0
    WHEN b.`item_subtotal` < 2400 THEN ROUND(b.`collected_total` * 0.015)
    ELSE ROUND((b.`collected_total` * 0.015) + 100)
  END AS `gateway_fee_amount`,
  GREATEST(
    GREATEST(b.`collected_total` - b.`item_subtotal`, 0)
    - CASE
        WHEN b.`item_subtotal` <= 0 THEN 0
        WHEN b.`item_subtotal` < 2400 THEN ROUND(b.`collected_total` * 0.015)
        ELSE ROUND((b.`collected_total` * 0.015) + 100)
      END,
    0
  ) AS `profit_amount`
FROM `tmp_repaired_gateway_base` AS b;

INSERT INTO `manuals_bought` (`manual_id`, `price`, `seller`, `buyer`, `ref_id`, `status`, `school_id`)
SELECT
  c.`item_id`,
  m.`price`,
  m.`user_id`,
  c.`user_id`,
  c.`ref_id`,
  'successful',
  COALESCE(m.`school_id`, tx.`school_id`)
FROM `cart` AS c
INNER JOIN `tmp_repaired_gateway_tx` AS tx
  ON tx.`ref_id` = c.`ref_id`
INNER JOIN `manuals` AS m
  ON m.`id` = c.`item_id`
LEFT JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = c.`ref_id`
 AND mb.`manual_id` = c.`item_id`
 AND mb.`buyer` = c.`user_id`
WHERE c.`type` = 'manual'
  AND mb.`id` IS NULL;

INSERT INTO `event_tickets` (`event_id`, `price`, `seller`, `buyer`, `ref_id`, `status`)
SELECT
  c.`item_id`,
  e.`price`,
  e.`user_id`,
  c.`user_id`,
  c.`ref_id`,
  'successful'
FROM `cart` AS c
INNER JOIN `tmp_repaired_gateway_tx` AS tx
  ON tx.`ref_id` = c.`ref_id`
INNER JOIN `events` AS e
  ON e.`id` = c.`item_id`
LEFT JOIN `event_tickets` AS et
  ON et.`ref_id` = c.`ref_id`
 AND et.`event_id` = c.`item_id`
 AND et.`buyer` = c.`user_id`
WHERE c.`type` = 'event'
  AND et.`id` IS NULL;

UPDATE `cart` AS c
INNER JOIN `tmp_repaired_gateway_tx` AS tx
  ON tx.`ref_id` = c.`ref_id`
SET c.`status` = 'confirmed';

UPDATE `transactions` AS t
INNER JOIN `tmp_repaired_gateway_tx` AS tx
  ON tx.`ref_id` = t.`ref_id`
SET t.`user_id` = tx.`user_id`,
    t.`amount` = tx.`collected_total`,
    t.`charge` = tx.`charge_amount`,
    t.`profit` = tx.`profit_amount`,
    t.`refund` = 0,
    t.`status` = 'successful',
    t.`medium` = 'PAYSTACK',
    t.`payment_channel` = 'gateway',
    t.`transaction_context` = 'purchase';

INSERT INTO `transactions` (
  `ref_id`, `user_id`, `amount`, `charge`, `profit`, `refund`, `status`, `medium`, `payment_channel`, `transaction_context`
)
SELECT
  tx.`ref_id`,
  tx.`user_id`,
  tx.`collected_total`,
  tx.`charge_amount`,
  tx.`profit_amount`,
  0,
  'successful',
  'PAYSTACK',
  'gateway',
  'purchase'
FROM `tmp_repaired_gateway_tx` AS tx
LEFT JOIN `transactions` AS t
  ON t.`ref_id` = tx.`ref_id`
WHERE t.`id` IS NULL;

DROP TEMPORARY TABLE IF EXISTS `tmp_school_payable_inserts`;
CREATE TEMPORARY TABLE `tmp_school_payable_inserts` AS
SELECT
  tx.`school_id`,
  tx.`ref_id`,
  tx.`user_id`,
  tx.`item_subtotal`,
  tx.`collected_total`,
  tx.`charge_amount`
FROM `tmp_repaired_gateway_tx` AS tx
LEFT JOIN `school_payable_ledger` AS spl
  ON spl.`source_ref_id` = tx.`ref_id`
WHERE spl.`id` IS NULL
  AND tx.`school_id` > 0
  AND tx.`item_subtotal` > 0;

INSERT INTO `school_payable_ledger` (
  `school_id`, `source_ref_id`, `payer_user_id`, `source_medium`, `source_channel`,
  `item_subtotal`, `collected_total`, `charge_amount`, `refund_amount`, `payable_amount`, `metadata`
)
SELECT
  s.`school_id`,
  s.`ref_id`,
  s.`user_id`,
  'PAYSTACK',
  'repair',
  s.`item_subtotal`,
  s.`collected_total`,
  s.`charge_amount`,
  0,
  s.`item_subtotal`,
  JSON_OBJECT(
    'handler', 'sql/repair_misclassified_wallet_funding_refs.sql',
    'repair_type', 'misclassified_wallet_funding_to_gateway_purchase'
  )
FROM `tmp_school_payable_inserts` AS s;

UPDATE `school_internal_wallets` AS siw
INNER JOIN (
  SELECT `school_id`, SUM(`item_subtotal`) AS `payable_amount`
  FROM `tmp_school_payable_inserts`
  GROUP BY `school_id`
) AS src
  ON src.`school_id` = siw.`school_id`
SET siw.`current_balance` = siw.`current_balance` + src.`payable_amount`,
    siw.`pending_payout_balance` = siw.`pending_payout_balance` + src.`payable_amount`,
    siw.`updated_at` = NOW();

SELECT
  'wallet_balance_blockers' AS `result_type`,
  b.`wallet_id`,
  b.`user_id`,
  b.`current_balance`,
  b.`remove_amount`
FROM `tmp_wallet_balance_blockers` AS b;

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `tmp_school_payable_inserts`;
DROP TEMPORARY TABLE IF EXISTS `tmp_repaired_gateway_tx`;
DROP TEMPORARY TABLE IF EXISTS `tmp_repaired_gateway_base`;
DROP TEMPORARY TABLE IF EXISTS `tmp_misclassified_wallet_funding_wallets`;
DROP TEMPORARY TABLE IF EXISTS `tmp_misclassified_wallet_funding_ready`;
DROP TEMPORARY TABLE IF EXISTS `tmp_wallet_balance_blockers`;
DROP TEMPORARY TABLE IF EXISTS `tmp_misclassified_wallet_funding_candidates`;
DROP TEMPORARY TABLE IF EXISTS `tmp_misclassified_wallet_funding_refs`;