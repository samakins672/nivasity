ALTER TABLE `school_payable_ledger`
  ADD COLUMN `refund_consumption_source_ref_id` VARCHAR(100) DEFAULT NULL AFTER `charge_amount`,
  ADD COLUMN `refund_consumed_amount` INT(11) NOT NULL DEFAULT 0 AFTER `refund_consumption_source_ref_id`;

ALTER TABLE `refund_reservations`
  ADD COLUMN `school_payable_ledger_id` INT(11) DEFAULT NULL AFTER `release_reason`,
  ADD KEY `idx_refund_reservations_school_payable_ledger_id` (`school_payable_ledger_id`);
