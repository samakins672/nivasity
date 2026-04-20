-- Identify wallet funding rows that look like checkout purchases instead of real topups.
--
-- This is intentionally read-only. Review the result set before applying any reversals.

SELECT
  f.`id` AS `wallet_funding_id`,
  f.`wallet_id`,
  f.`user_id`,
  f.`provider_reference`,
  f.`amount`,
  f.`provider_charge_amount`,
  f.`status`,
  f.`source`,
  f.`posted_at`,
  wl.`id` AS `ledger_id`,
  wl.`amount` AS `ledger_amount`,
  wl.`balance_before`,
  wl.`balance_after`,
  tx.`id` AS `transaction_id`,
  tx.`payment_channel`,
  tx.`transaction_context`,
  CASE
    WHEN c.`ref_id` IS NOT NULL THEN 'cart'
    WHEN mb.`ref_id` IS NOT NULL THEN 'manuals_bought'
    WHEN et.`ref_id` IS NOT NULL THEN 'event_tickets'
    WHEN ptx.`ref_id` IS NOT NULL THEN 'transactions.purchase'
    WHEN f.`provider_reference` REGEXP '^nivas_[0-9]+_[0-9]+$' THEN 'checkout_reference_pattern'
    ELSE 'unknown'
  END AS `suspicion_reason`
FROM `wallet_funding_transactions` AS f
LEFT JOIN `wallet_ledger_entries` AS wl
  ON wl.`wallet_id` = f.`wallet_id`
 AND wl.`reference` = CONCAT('wallet_funding:', f.`provider_reference`)
LEFT JOIN `transactions` AS tx
  ON tx.`ref_id` = f.`provider_reference`
LEFT JOIN `cart` AS c
  ON c.`ref_id` = f.`provider_reference`
LEFT JOIN `manuals_bought` AS mb
  ON mb.`ref_id` = f.`provider_reference`
LEFT JOIN `event_tickets` AS et
  ON et.`ref_id` = f.`provider_reference`
LEFT JOIN `transactions` AS ptx
  ON ptx.`ref_id` = f.`provider_reference`
 AND (ptx.`transaction_context` = 'purchase' OR ptx.`payment_channel` = 'gateway')
WHERE f.`status` = 'posted'
  AND (
    c.`ref_id` IS NOT NULL
    OR mb.`ref_id` IS NOT NULL
    OR et.`ref_id` IS NOT NULL
    OR ptx.`ref_id` IS NOT NULL
    OR f.`provider_reference` REGEXP '^nivas_[0-9]+_[0-9]+$'
  )
ORDER BY COALESCE(f.`posted_at`, f.`created_at`) DESC, f.`id` DESC;