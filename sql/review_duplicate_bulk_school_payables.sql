-- Review duplicate bulk school payable rows before any cleanup.
--
-- This script is intentionally READ-ONLY.
-- It contains SELECT statements only.
--
-- What it covers:
-- 1. exact duplicated student-level school_payable_ledger rows created by the repair sweep
-- 2. per-batch duplicate totals against the legitimate batch-level payable rows
-- 3. settlement dependencies for those duplicate rows
-- 4. school internal wallet impact of those duplicate rows
-- 5. rollback-ready duplicate ids that are still unsettled and unstaged
-- 6. the separate orphan bulk payable row that needs manual review


-- 1. Exact duplicate student-level school payable rows.
SELECT
  spl.id AS school_payable_ledger_id,
  spl.school_id,
  spl.source_ref_id AS student_ref,
  spl.item_subtotal,
  spl.collected_total,
  spl.payable_amount,
  spl.settled_amount,
  spl.status AS school_payable_status,
  spl.created_at,
  s.batch_id,
  b.ref_id AS batch_ref,
  b.subtotal AS batch_subtotal,
  b.total_amount AS batch_total_amount,
  b.student_count AS batch_student_count,
  spl.metadata
FROM school_payable_ledger AS spl
INNER JOIN manual_bulk_payment_students AS s
  ON s.ref_id = spl.source_ref_id
INNER JOIN manual_bulk_payment_batches AS b
  ON b.id = s.batch_id
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
ORDER BY b.id ASC, spl.id ASC;


-- 2. Per-batch duplicate summary.
SELECT
  b.id AS batch_id,
  b.ref_id AS batch_ref,
  b.subtotal AS batch_subtotal,
  b.total_amount AS batch_total_amount,
  COUNT(spl.id) AS duplicate_rows,
  SUM(spl.payable_amount) AS duplicated_payable,
  SUM(spl.collected_total) AS duplicated_collected_total,
  SUM(CASE WHEN spl.payable_amount > 0 AND spl.status <> 'reversed' THEN 1 ELSE 0 END) AS active_duplicate_rows,
  SUM(CASE WHEN spl.payable_amount > 0 AND spl.status <> 'reversed' THEN spl.payable_amount ELSE 0 END) AS active_duplicated_payable,
  SUM(CASE WHEN spl.status = 'reversed' THEN 1 ELSE 0 END) AS reversed_duplicate_rows
FROM school_payable_ledger AS spl
INNER JOIN manual_bulk_payment_students AS s
  ON s.ref_id = spl.source_ref_id
INNER JOIN manual_bulk_payment_batches AS b
  ON b.id = s.batch_id
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
GROUP BY b.id, b.ref_id, b.subtotal, b.total_amount
ORDER BY b.id ASC;


-- 2b. Active duplicate summary only.
-- If cleanup has run successfully, this query should return zero rows.
SELECT
  b.id AS batch_id,
  b.ref_id AS batch_ref,
  b.subtotal AS batch_subtotal,
  b.total_amount AS batch_total_amount,
  COUNT(spl.id) AS active_duplicate_rows,
  SUM(spl.payable_amount) AS active_duplicated_payable,
  SUM(spl.collected_total) AS active_duplicated_collected_total
FROM school_payable_ledger AS spl
INNER JOIN manual_bulk_payment_students AS s
  ON s.ref_id = spl.source_ref_id
INNER JOIN manual_bulk_payment_batches AS b
  ON b.id = s.batch_id
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
  AND spl.payable_amount > 0
  AND spl.status <> 'reversed'
GROUP BY b.id, b.ref_id, b.subtotal, b.total_amount
ORDER BY b.id ASC;


-- 3. Settlement dependency review.
-- Any row returned here has already been staged or settled and should not be
-- part of a simple cleanup.
SELECT
  spl.id AS school_payable_ledger_id,
  spl.source_ref_id,
  spl.payable_amount,
  spl.settled_amount,
  COUNT(sbi.id) AS settlement_item_count,
  GROUP_CONCAT(DISTINCT sb.status ORDER BY sb.status SEPARATOR ', ') AS settlement_batch_statuses
FROM school_payable_ledger AS spl
INNER JOIN manual_bulk_payment_students AS s
  ON s.ref_id = spl.source_ref_id
INNER JOIN manual_bulk_payment_batches AS b
  ON b.id = s.batch_id
LEFT JOIN settlement_batch_items AS sbi
  ON sbi.school_payable_ledger_id = spl.id
LEFT JOIN settlement_batches AS sb
  ON sb.id = sbi.settlement_batch_id
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
GROUP BY spl.id, spl.source_ref_id, spl.payable_amount, spl.settled_amount
HAVING settlement_item_count > 0 OR spl.settled_amount > 0
ORDER BY spl.id ASC;


-- 4. School internal wallet impact of the duplicated rows.
SELECT
  d.school_id,
  COUNT(*) AS duplicate_rows,
  SUM(d.payable_amount) AS duplicate_payable,
  siw.current_balance,
  siw.pending_payout_balance,
  siw.carry_forward_balance
FROM (
  SELECT
    spl.id,
    spl.school_id,
    spl.payable_amount
  FROM school_payable_ledger AS spl
  INNER JOIN manual_bulk_payment_students AS s
    ON s.ref_id = spl.source_ref_id
  INNER JOIN manual_bulk_payment_batches AS b
    ON b.id = s.batch_id
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
) AS d
LEFT JOIN school_internal_wallets AS siw
  ON siw.school_id = d.school_id
GROUP BY d.school_id, siw.current_balance, siw.pending_payout_balance, siw.carry_forward_balance
ORDER BY d.school_id ASC;


-- 5. Rollback-ready duplicate ids only.
-- These are the safest cleanup candidates because they are:
-- - repair-created duplicate student-level bulk rows
-- - backed by an existing legitimate batch-level payable
-- - not settled yet
-- - not referenced by any settlement_batch_items row
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
  AND sbi.id IS NULL
ORDER BY b.id ASC, spl.id ASC;


-- 6. Orphan batch-level bulk payable row for manual review.
-- Keep this separate from the duplicate rollback set until its origin is fully explained.
SELECT
  spl.id,
  spl.source_ref_id,
  spl.item_subtotal,
  spl.collected_total,
  spl.payable_amount,
  spl.created_at,
  spl.metadata,
  b.id AS batch_id,
  t.id AS transaction_id,
  wle.id AS wallet_ledger_id,
  wle.reference AS wallet_ledger_reference
FROM school_payable_ledger AS spl
LEFT JOIN manual_bulk_payment_batches AS b
  ON CONVERT(b.ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(spl.source_ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
LEFT JOIN transactions AS t
  ON CONVERT(t.ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(spl.source_ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
LEFT JOIN wallet_ledger_entries AS wle
  ON CONVERT(wle.provider_reference USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(spl.source_ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
WHERE CONVERT(spl.source_ref_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'bulk_mat_14557_1777118022_fd0d1a34' COLLATE utf8mb4_unicode_ci;