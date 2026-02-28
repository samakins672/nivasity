-- Material-level refunds migration
-- Adds refunds.materials (JSON array of manual IDs) and
-- backfills transactions.refund to source-transaction progress semantics.

-- 1) Add materials column to refunds table (idempotent)
SET @has_refunds_materials := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'refunds'
    AND column_name = 'materials'
);
SET @add_refunds_materials_sql := IF(
  @has_refunds_materials = 0,
  'ALTER TABLE `refunds` ADD COLUMN `materials` LONGTEXT DEFAULT NULL AFTER `ref_id`',
  'SELECT 1'
);
PREPARE stmt_add_refunds_materials FROM @add_refunds_materials_sql;
EXECUTE stmt_add_refunds_materials;
DEALLOCATE PREPARE stmt_add_refunds_materials;

-- 2) Ensure transactions.refund exists (idempotent)
SET @has_transactions_refund_column := (
  SELECT COUNT(1)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'transactions'
    AND column_name = 'refund'
);
SET @add_transactions_refund_column_sql := IF(
  @has_transactions_refund_column = 0,
  'ALTER TABLE `transactions` ADD COLUMN `refund` INT NOT NULL DEFAULT 0 AFTER `profit`',
  'SELECT 1'
);
PREPARE stmt_add_transactions_refund_column FROM @add_transactions_refund_column_sql;
EXECUTE stmt_add_transactions_refund_column;
DEALLOCATE PREPARE stmt_add_transactions_refund_column;

-- 3) Reset transactions.refund and recompute as deduction-side consumed amount
--    (sum of consumed reservation amounts by reservation ref_id)
UPDATE `transactions` SET `refund` = 0;

UPDATE `transactions` t
INNER JOIN (
  SELECT
    rr.ref_id AS tx_ref_id,
    COALESCE(SUM(rr.amount), 0) AS refunded_total
  FROM `refund_reservations` rr
  WHERE rr.status = 'consumed'
    AND rr.ref_id IS NOT NULL
    AND rr.ref_id <> ''
  GROUP BY rr.ref_id
) src ON src.tx_ref_id = t.ref_id
SET t.refund = src.refunded_total;
