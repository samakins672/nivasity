ALTER TABLE `wallet_funding_transactions`
  ADD COLUMN IF NOT EXISTS `provider_charge_amount` INT(11) NOT NULL DEFAULT 0 AFTER `amount`;

UPDATE `wallet_funding_transactions`
SET `provider_charge_amount` = LEAST(300, ROUND(`amount` * 0.01))
WHERE `provider` = 'paystack'
  AND `amount` > 0
  AND (`provider_charge_amount` IS NULL OR `provider_charge_amount` = 0);