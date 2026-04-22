-- Repair `transactions.refund` so it matches the refund amount logged against
-- each transaction in `refunds.ref_id`.
--
-- Business rule:
-- 1. Actual refunded transactions should keep their refunded amount in
--    `transactions.refund`.
-- 2. Any legacy amount that came from reservation consumption or school-share
--    extraction must NOT inflate `transactions.refund`; that movement already
--    lives in `refund_reservations`.
--
-- This script recomputes the correct refund total per `transactions.ref_id`
-- from non-cancelled rows in `refunds` and updates `transactions.refund` to
-- that amount. `refund_reservations` is included only as diagnostic context.
--
-- Run it in a maintenance window and review the preview queries before
-- committing.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS `tmp_transaction_refund_repair_candidates`;
CREATE TEMPORARY TABLE `tmp_transaction_refund_repair_candidates` AS
SELECT
  t.`id` AS `transaction_id`,
  t.`ref_id`,
  t.`user_id`,
  COALESCE(t.`amount`, 0) AS `transaction_amount`,
  COALESCE(t.`refund`, 0) AS `current_refund`,
  COALESCE(rf.`refund_total`, 0) AS `expected_refund`,
  COALESCE(rf.`refund_count`, 0) AS `refund_count`,
  COALESCE(rf.`latest_refund_id`, 0) AS `latest_refund_id`,
  COALESCE(rr.`reservation_consumed_total`, 0) AS `reservation_consumed_total`,
  COALESCE(t.`status`, '') AS `transaction_status`,
  COALESCE(t.`medium`, '') AS `medium`,
  COALESCE(t.`payment_channel`, '') AS `payment_channel`,
  COALESCE(t.`transaction_context`, '') AS `transaction_context`
FROM `transactions` AS t
LEFT JOIN (
  SELECT
    r.`ref_id`,
    COALESCE(SUM(r.`amount`), 0) AS `refund_total`,
    COUNT(DISTINCT r.`id`) AS `refund_count`,
    MAX(r.`id`) AS `latest_refund_id`
  FROM `refunds` AS r
  WHERE r.`ref_id` IS NOT NULL
    AND r.`ref_id` <> ''
    AND COALESCE(r.`status`, '') <> 'cancelled'
  GROUP BY r.`ref_id`
) AS rf
  ON rf.`ref_id` = t.`ref_id`
LEFT JOIN (
  SELECT
    rr.`ref_id`,
    COALESCE(SUM(CASE WHEN rr.`status` = 'consumed' THEN rr.`amount` ELSE 0 END), 0) AS `reservation_consumed_total`,
    COUNT(*) AS `reservation_row_count`
  FROM `refund_reservations` AS rr
  WHERE rr.`ref_id` IS NOT NULL
    AND rr.`ref_id` <> ''
  GROUP BY rr.`ref_id`
) AS rr
  ON rr.`ref_id` = t.`ref_id`
WHERE ROUND(COALESCE(t.`refund`, 0), 2) <> ROUND(COALESCE(rf.`refund_total`, 0), 2)
  AND (
    COALESCE(rf.`refund_total`, 0) > 0
    OR COALESCE(rr.`reservation_consumed_total`, 0) > 0
    OR COALESCE(t.`refund`, 0) > 0
  );

-- Review the exact rows that will be fixed.
SELECT *
FROM `tmp_transaction_refund_repair_candidates`
ORDER BY `expected_refund` DESC, `current_refund` DESC, `transaction_id` DESC;

-- Preview the repair impact before applying it.
SELECT
  COUNT(*) AS `candidate_rows`,
  COALESCE(SUM(`current_refund`), 0) AS `current_refund_total`,
  COALESCE(SUM(`expected_refund`), 0) AS `expected_refund_total`,
  COALESCE(SUM(`reservation_consumed_total`), 0) AS `reservation_consumed_total`
FROM `tmp_transaction_refund_repair_candidates`;

UPDATE `transactions` AS t
INNER JOIN `tmp_transaction_refund_repair_candidates` AS c
  ON c.`transaction_id` = t.`id`
SET t.`refund` = c.`expected_refund`
WHERE ROUND(COALESCE(t.`refund`, 0), 2) <> ROUND(COALESCE(c.`expected_refund`, 0), 2);

SELECT ROW_COUNT() AS `updated_rows`;

SELECT
  COUNT(*) AS `remaining_mismatch_rows`,
  COALESCE(SUM(t.`refund`), 0) AS `current_refund_total_after_update`,
  COALESCE(SUM(c.`expected_refund`), 0) AS `expected_refund_total_after_update`
FROM `transactions` AS t
INNER JOIN `tmp_transaction_refund_repair_candidates` AS c
  ON c.`transaction_id` = t.`id`
WHERE ROUND(COALESCE(t.`refund`, 0), 2) <> ROUND(COALESCE(c.`expected_refund`, 0), 2);

COMMIT;

DROP TEMPORARY TABLE IF EXISTS `tmp_transaction_refund_repair_candidates`;