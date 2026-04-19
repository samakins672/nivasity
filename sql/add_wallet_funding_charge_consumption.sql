ALTER TABLE `wallet_funding_transactions`
  ADD COLUMN IF NOT EXISTS `consumed_charge_amount` INT(11) NOT NULL DEFAULT 0 AFTER `provider_charge_amount`,
  ADD COLUMN IF NOT EXISTS `remaining_charge_amount` INT(11) NOT NULL DEFAULT 0 AFTER `consumed_charge_amount`;

UPDATE `wallet_funding_transactions`
SET
  `provider_charge_amount` = CASE
    WHEN `provider` = 'paystack' AND `amount` > 0 AND (`provider_charge_amount` IS NULL OR `provider_charge_amount` = 0)
      THEN LEAST(300, ROUND(`amount` * 0.01))
    ELSE COALESCE(`provider_charge_amount`, 0)
  END,
  `consumed_charge_amount` = LEAST(
    GREATEST(COALESCE(`consumed_charge_amount`, 0), 0),
    CASE
      WHEN `provider` = 'paystack' AND `amount` > 0 AND (`provider_charge_amount` IS NULL OR `provider_charge_amount` = 0)
        THEN LEAST(300, ROUND(`amount` * 0.01))
      ELSE COALESCE(`provider_charge_amount`, 0)
    END
  ),
  `remaining_charge_amount` = GREATEST(
    CASE
      WHEN `provider` = 'paystack' AND `amount` > 0 AND (`provider_charge_amount` IS NULL OR `provider_charge_amount` = 0)
        THEN LEAST(300, ROUND(`amount` * 0.01))
      ELSE COALESCE(`provider_charge_amount`, 0)
    END - LEAST(
      GREATEST(COALESCE(`consumed_charge_amount`, 0), 0),
      CASE
        WHEN `provider` = 'paystack' AND `amount` > 0 AND (`provider_charge_amount` IS NULL OR `provider_charge_amount` = 0)
          THEN LEAST(300, ROUND(`amount` * 0.01))
        ELSE COALESCE(`provider_charge_amount`, 0)
      END
    ),
    0
  );