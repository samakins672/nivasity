-- Material-level refunds migration
-- Adds refunds.materials (JSON array of manual IDs).

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

-- 3) Do not backfill transactions.refund from refund reservation consumption.
--    That column belongs to the actual refunded transaction row, not the
--    source transaction whose purchase is being offset by refund reservations.
