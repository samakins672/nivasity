-- Add gateway tracking to cart and transactions tables
-- This allows the system to track which payment gateway was used for each transaction

-- Add gateway column to cart table
ALTER TABLE `cart` 
ADD COLUMN `gateway` VARCHAR(20) NULL DEFAULT NULL AFTER `status`,
ADD INDEX `idx_gateway` (`gateway`);

-- Ensure medium column exists in transactions table (for gateway tracking)
-- Check if column exists first, if not add it
SET @col_exists = (SELECT COUNT(*) 
                   FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_NAME = 'transactions' 
                   AND COLUMN_NAME = 'medium' 
                   AND TABLE_SCHEMA = DATABASE());

SET @query = IF(@col_exists = 0,
    'ALTER TABLE `transactions` ADD COLUMN `medium` VARCHAR(50) NULL DEFAULT NULL AFTER `status`, ADD INDEX `idx_medium` (`medium`)',
    'SELECT "Column medium already exists in transactions table" AS message');

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add comments to clarify purpose
ALTER TABLE `cart` 
MODIFY COLUMN `gateway` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Payment gateway used: flutterwave, paystack, or interswitch';

-- Update existing transactions without medium to have a default value
UPDATE `transactions` 
SET `medium` = 'flutterwave' 
WHERE `medium` IS NULL OR `medium` = '';

-- Add index for faster queries
ALTER TABLE `transactions` 
ADD INDEX IF NOT EXISTS `idx_medium` (`medium`);

SELECT 'Gateway tracking columns added successfully!' AS message;
