-- Migration: Add gateway support to settlement accounts
-- This allows each subaccount to be associated with a specific payment gateway
-- Run this migration to support multiple payment gateways (Flutterwave, Paystack, Interswitch)

-- Add gateway field to settlement_accounts table
ALTER TABLE `settlement_accounts` 
ADD COLUMN `gateway` varchar(20) NOT NULL DEFAULT 'paystack' AFTER `subaccount_code`;

-- Add index for faster gateway lookups
ALTER TABLE `settlement_accounts` 
ADD INDEX `idx_gateway` (`gateway`);

-- Update existing records to specify paystack as the default gateway
-- (since the current implementation uses Paystack for subaccounts)
UPDATE `settlement_accounts` 
SET `gateway` = 'paystack' 
WHERE `gateway` = '' OR `gateway` IS NULL;

-- Note: When adding new settlement accounts for different gateways:
-- - Flutterwave subaccounts should have gateway = 'flutterwave'
-- - Paystack subaccounts should have gateway = 'paystack'  
-- - Interswitch/Quickteller accounts should have gateway = 'interswitch'
