-- Cleanup confirmed duplicate bulk school payable rows.
--
-- This script is intentionally limited to the confirmed duplicate rows only.
-- It does NOT touch the separate orphan bulk payable row
-- `bulk_mat_14557_1777118022_fd0d1a34`.
--
-- Cleanup strategy:
-- 1. identify only repair-created student-level bulk rows that duplicate an existing
--    successful batch-level bulk payable row
-- 2. require those rows to be unsettled and absent from settlement_batch_items
-- 3. subtract the duplicated payable from school_internal_wallets
-- 4. soft-reverse the duplicate school_payable_ledger rows by setting
--    `payable_amount = 0` and `status = 'reversed'`
--
-- Recommended workflow:
-- 1. run sql/review_duplicate_bulk_school_payables.sql first
-- 2. run this cleanup script
-- 3. verify the post-cleanup checks at the end of this script

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS tmp_duplicate_bulk_school_payables_cleanup;
CREATE TEMPORARY TABLE tmp_duplicate_bulk_school_payables_cleanup AS
SELECT
  spl.id AS school_payable_ledger_id,
  spl.school_id,
  spl.source_ref_id,
  spl.payable_amount,
  spl.collected_total,
  spl.created_at,
  s.batch_id,
  b.ref_id AS batch_ref
FROM school_payable_ledger AS spl
INNER JOIN manual_bulk_payment_students AS s
  ON s.ref_id = spl.source_ref_id
INNER JOIN manual_bulk_payment_batches AS b
  ON b.id = s.batch_id
LEFT JOIN settlement_batch_items AS sbi
  ON sbi.school_payable_ledger_id = spl.id
WHERE spl.source_channel = 'ledger_repair_sweep'
  AND spl.metadata LIKE '%nivasityEnsureSchoolPayableForPurchase%'
  AND b.payment_status = 'successful'
  AND EXISTS (
    SELECT 1
    FROM school_payable_ledger AS spl_batch
    WHERE spl_batch.source_ref_id = b.ref_id
      AND spl_batch.metadata LIKE '%bulk_material_payment_process_wallet_batch%'
    LIMIT 1
  )
  AND spl.settled_amount = 0
  AND spl.status = 'pending'
  AND spl.payable_amount > 0
  AND sbi.id IS NULL;


-- Preview the exact candidate set that will be reversed.
SELECT
  COUNT(*) AS candidate_rows,
  COALESCE(SUM(payable_amount), 0) AS candidate_payable
FROM tmp_duplicate_bulk_school_payables_cleanup;

SELECT
  batch_id,
  batch_ref,
  COUNT(*) AS candidate_rows,
  SUM(payable_amount) AS candidate_payable
FROM tmp_duplicate_bulk_school_payables_cleanup
GROUP BY batch_id, batch_ref
ORDER BY batch_id ASC;

SELECT
  school_id,
  COUNT(*) AS candidate_rows,
  SUM(payable_amount) AS candidate_payable
FROM tmp_duplicate_bulk_school_payables_cleanup
GROUP BY school_id
ORDER BY school_id ASC;


-- Guard: ensure the affected school wallet balances are large enough to absorb the reversal.
DROP TEMPORARY TABLE IF EXISTS tmp_duplicate_bulk_school_payable_balance_issues;
CREATE TEMPORARY TABLE tmp_duplicate_bulk_school_payable_balance_issues AS
SELECT
  dup.school_id,
  dup.duplicate_payable,
  siw.id AS school_wallet_id,
  siw.current_balance,
  siw.pending_payout_balance
FROM (
  SELECT
    school_id,
    SUM(payable_amount) AS duplicate_payable
  FROM tmp_duplicate_bulk_school_payables_cleanup
  GROUP BY school_id
) AS dup
LEFT JOIN school_internal_wallets AS siw
  ON siw.school_id = dup.school_id
WHERE siw.id IS NULL
   OR siw.current_balance < dup.duplicate_payable
   OR siw.pending_payout_balance < dup.duplicate_payable;

SELECT
  *
FROM tmp_duplicate_bulk_school_payable_balance_issues;


-- Adjust school internal wallet balances only when no balance issues are present.
UPDATE school_internal_wallets AS siw
INNER JOIN (
  SELECT
    school_id,
    SUM(payable_amount) AS duplicate_payable
  FROM tmp_duplicate_bulk_school_payables_cleanup
  GROUP BY school_id
) AS dup
  ON dup.school_id = siw.school_id
INNER JOIN (
  SELECT COUNT(*) AS issue_count
  FROM tmp_duplicate_bulk_school_payable_balance_issues
) AS guard
SET siw.current_balance = siw.current_balance - dup.duplicate_payable,
    siw.pending_payout_balance = siw.pending_payout_balance - dup.duplicate_payable,
    siw.updated_at = NOW()
WHERE guard.issue_count = 0;

SELECT ROW_COUNT() AS school_wallet_rows_adjusted;


-- Soft-reverse the duplicate school payable rows only when no balance issues are present.
UPDATE school_payable_ledger AS spl
INNER JOIN tmp_duplicate_bulk_school_payables_cleanup AS dup
  ON dup.school_payable_ledger_id = spl.id
INNER JOIN (
  SELECT COUNT(*) AS issue_count
  FROM tmp_duplicate_bulk_school_payable_balance_issues
) AS guard
SET spl.payable_amount = 0,
    spl.status = 'reversed',
    spl.updated_at = NOW()
WHERE guard.issue_count = 0;

SELECT ROW_COUNT() AS school_payable_rows_reversed;


-- Post-cleanup verification.
SELECT
  COUNT(*) AS remaining_candidate_rows,
  COALESCE(SUM(spl.payable_amount), 0) AS remaining_candidate_payable
FROM school_payable_ledger AS spl
INNER JOIN tmp_duplicate_bulk_school_payables_cleanup AS dup
  ON dup.school_payable_ledger_id = spl.id
WHERE spl.payable_amount > 0
   OR spl.status <> 'reversed';

SELECT
  school_id,
  current_balance,
  pending_payout_balance,
  carry_forward_balance
FROM school_internal_wallets
WHERE school_id IN (
  SELECT DISTINCT school_id
  FROM tmp_duplicate_bulk_school_payables_cleanup
)
ORDER BY school_id ASC;

COMMIT;