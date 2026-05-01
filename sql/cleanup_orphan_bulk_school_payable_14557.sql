-- Cleanup the orphan bulk school payable row
-- `bulk_mat_14557_1777118022_fd0d1a34`.
--
-- This script is intentionally narrow and only targets that single reference.
-- It will only proceed when all of the following are still true:
-- 1. the school_payable_ledger row still exists and is unreversed
-- 2. there is no matching row in manual_bulk_payment_batches
-- 3. there is no matching row in manual_bulk_payment_students
-- 4. there is no matching row in transactions
-- 5. there is no matching row in wallet_ledger_entries
-- 6. there is no matching row in settlement_batch_items
-- 7. the school internal wallet has enough balance to absorb the reversal
--
-- If any guard fails, the update statements become no-ops.

START TRANSACTION;

SET @target_ref := 'bulk_mat_14557_1777118022_fd0d1a34';

DROP TEMPORARY TABLE IF EXISTS tmp_orphan_bulk_school_payable_cleanup;
CREATE TEMPORARY TABLE tmp_orphan_bulk_school_payable_cleanup AS
SELECT
  spl.id AS school_payable_ledger_id,
  spl.school_id,
  spl.source_ref_id,
  spl.payable_amount,
  spl.collected_total,
  spl.created_at
FROM school_payable_ledger AS spl
WHERE CONVERT(spl.source_ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@target_ref USING utf8mb4) COLLATE utf8mb4_unicode_ci
  AND spl.metadata LIKE '%bulk_material_payment_process_wallet_batch%'
  AND spl.status = 'pending'
  AND spl.settled_amount = 0
  AND spl.payable_amount > 0
  AND NOT EXISTS (
    SELECT 1
    FROM manual_bulk_payment_batches AS b
    WHERE CONVERT(b.ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(spl.source_ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
    LIMIT 1
  )
  AND NOT EXISTS (
    SELECT 1
    FROM manual_bulk_payment_students AS s
    WHERE CONVERT(s.ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(spl.source_ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
    LIMIT 1
  )
  AND NOT EXISTS (
    SELECT 1
    FROM transactions AS t
    WHERE CONVERT(t.ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(spl.source_ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
    LIMIT 1
  )
  AND NOT EXISTS (
    SELECT 1
    FROM wallet_ledger_entries AS wle
    WHERE CONVERT(wle.provider_reference USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(spl.source_ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
       OR CONVERT(wle.reference USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(CONCAT('wallet_bulk_purchase:', spl.source_ref_id) USING utf8mb4) COLLATE utf8mb4_unicode_ci
    LIMIT 1
  )
  AND NOT EXISTS (
    SELECT 1
    FROM settlement_batch_items AS sbi
    WHERE sbi.school_payable_ledger_id = spl.id
    LIMIT 1
  );


-- Preview the candidate row.
SELECT
  COUNT(*) AS candidate_rows,
  COALESCE(SUM(payable_amount), 0) AS candidate_payable
FROM tmp_orphan_bulk_school_payable_cleanup;

SELECT
  *
FROM tmp_orphan_bulk_school_payable_cleanup;


-- Guard table.
DROP TEMPORARY TABLE IF EXISTS tmp_orphan_bulk_school_payable_issues;
CREATE TEMPORARY TABLE tmp_orphan_bulk_school_payable_issues AS
SELECT
  'candidate_count_mismatch' AS issue,
  CONCAT('expected 1 candidate row, found ', COUNT(*)) AS details
FROM tmp_orphan_bulk_school_payable_cleanup
HAVING COUNT(*) <> 1

UNION ALL

SELECT
  'school_wallet_missing_or_insufficient' AS issue,
  CONCAT(
    'school_id=', cleanup.school_id,
    ', payable=', cleanup.payable_amount,
    ', wallet_id=', COALESCE(CAST(siw.id AS CHAR), 'NULL'),
    ', current_balance=', COALESCE(CAST(siw.current_balance AS CHAR), 'NULL'),
    ', pending_payout_balance=', COALESCE(CAST(siw.pending_payout_balance AS CHAR), 'NULL')
  ) AS details
FROM tmp_orphan_bulk_school_payable_cleanup AS cleanup
LEFT JOIN school_internal_wallets AS siw
  ON siw.school_id = cleanup.school_id
WHERE siw.id IS NULL
   OR siw.current_balance < cleanup.payable_amount
   OR siw.pending_payout_balance < cleanup.payable_amount;

SELECT
  *
FROM tmp_orphan_bulk_school_payable_issues;


-- Reverse school wallet balances only when no issues exist.
UPDATE school_internal_wallets AS siw
INNER JOIN tmp_orphan_bulk_school_payable_cleanup AS cleanup
  ON cleanup.school_id = siw.school_id
INNER JOIN (
  SELECT COUNT(*) AS issue_count
  FROM tmp_orphan_bulk_school_payable_issues
) AS guard
SET siw.current_balance = siw.current_balance - cleanup.payable_amount,
    siw.pending_payout_balance = siw.pending_payout_balance - cleanup.payable_amount,
    siw.updated_at = NOW()
WHERE guard.issue_count = 0;

SELECT ROW_COUNT() AS school_wallet_rows_adjusted;


-- Soft-reverse the orphan school payable row only when no issues exist.
UPDATE school_payable_ledger AS spl
INNER JOIN tmp_orphan_bulk_school_payable_cleanup AS cleanup
  ON cleanup.school_payable_ledger_id = spl.id
INNER JOIN (
  SELECT COUNT(*) AS issue_count
  FROM tmp_orphan_bulk_school_payable_issues
) AS guard
SET spl.payable_amount = 0,
    spl.status = 'reversed',
    spl.updated_at = NOW()
WHERE guard.issue_count = 0;

SELECT ROW_COUNT() AS school_payable_rows_reversed;


-- Post-cleanup verification.
SELECT
  spl.id,
  spl.source_ref_id,
  spl.payable_amount,
  spl.status,
  spl.settled_amount,
  spl.updated_at
FROM school_payable_ledger AS spl
WHERE CONVERT(spl.source_ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(@target_ref USING utf8mb4) COLLATE utf8mb4_unicode_ci;

SELECT
  siw.school_id,
  siw.current_balance,
  siw.pending_payout_balance,
  siw.carry_forward_balance
FROM school_internal_wallets AS siw
WHERE siw.school_id IN (
  SELECT DISTINCT school_id
  FROM tmp_orphan_bulk_school_payable_cleanup
);

COMMIT;