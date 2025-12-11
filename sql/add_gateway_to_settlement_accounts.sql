-- Migration: Add gateway support to settlement accounts
-- This allows each subaccount to be associated with a specific payment gateway
-- Run this migration to support multiple payment gateways (Flutterwave, Paystack, Interswitch)

-- Add gateway field to settlement_accounts table
-- Default is NULL - application will handle migration of existing records
ALTER TABLE `settlement_accounts` 
ADD COLUMN `gateway` varchar(20) DEFAULT NULL AFTER `subaccount_code`;

-- Add index for faster gateway lookups
ALTER TABLE `settlement_accounts` 
ADD INDEX `idx_gateway` (`gateway`);

-- Update existing records to specify paystack as the gateway
-- (since the current implementation uses Paystack for subaccounts)
-- This is based on the fact that settlement_accounts currently stores Paystack subaccount codes
UPDATE `settlement_accounts` 
SET `gateway` = 'paystack' 
WHERE `gateway` IS NULL;

-- After this migration, you should manually verify and update gateway values
-- based on which provider each subaccount actually belongs to

-- Note: When adding new settlement accounts for different gateways:
-- - Flutterwave subaccounts should have gateway = 'flutterwave'
-- - Paystack subaccounts should have gateway = 'paystack'  
-- - Interswitch/Quickteller accounts should have gateway = 'interswitch'
