# Manual Testing Checklist

This document provides step-by-step instructions for manually testing the payment gateway integration.

## Prerequisites

1. Database migration applied: `sql/add_gateway_to_settlement_accounts.sql`
2. Configuration file created: `config/payment_gateway.php` (from example)
3. Test credentials configured for each gateway
4. Settlement accounts set up with appropriate `gateway` field values

## Test 1: Flutterwave Integration

### Setup
1. Set active gateway in `config/payment_gateway.php`:
   ```php
   'active' => 'flutterwave',
   ```

### Test Steps

1. **Login and Add Items to Cart**
   - Login to the application
   - Add one or more materials/events to cart
   - Note the total amount

2. **Checkout with Amount < ₦2500**
   - Proceed to checkout
   - Verify Flutterwave modal appears
   - Use test card: 4187427415564246, CVV: 828, Expiry: 09/32, PIN: 3310, OTP: 12345
   - Complete payment
   - **Expected**: Transaction succeeds with ₦70 flat fee

3. **Checkout with Amount > ₦2500**
   - Add more items to reach > ₦2500
   - Proceed to checkout
   - Complete payment
   - **Expected**: Transaction succeeds with 2% + tiered fee (₦20-50)

4. **Verification**
   - [ ] Payment successful message displayed
   - [ ] Items appear in purchased items
   - [ ] Transaction recorded in `transactions` table
   - [ ] Correct charge calculated (₦70 or 2% + tiered)
   - [ ] Cart cleared after successful payment
   - [ ] Email confirmation received
   - [ ] Webhook received (check email logs)

## Test 2: Paystack Integration with Pricing Exception

### Setup
1. Set active gateway in `config/payment_gateway.php`:
   ```php
   'active' => 'paystack',
   ```

### Test Steps

1. **Checkout with Amount = ₦2000 (Below ₦2500)**
   - Add items totaling ₦2000
   - Proceed to checkout
   - Verify Paystack modal appears
   - Use test card: 4084084084084081, CVV: 408, Expiry: any future date, PIN: 0000
   - Complete payment
   - **Expected**: Charge = ₦2000 × 1.5% = ₦30
   - **Verify**: Total amount = ₦2000 + ₦30 = ₦2030

2. **Checkout with Amount = ₦2500 (Exactly ₦2500)**
   - Add items totaling exactly ₦2500
   - Proceed to checkout
   - Complete payment
   - **Expected**: Charge = ₦2500 × 1.5% = ₦37.50
   - **Verify**: Total amount = ₦2500 + ₦37.50 = ₦2537.50

3. **Checkout with Amount = ₦2501 (Above ₦2500) - EXCEPTION TEST**
   - Add items totaling ₦2501
   - Proceed to checkout
   - Complete payment
   - **Expected**: Charge = (₦2501 × 1.5%) + ₦100 = ₦37.52 + ₦100 = ₦137.52
   - **Verify**: Total amount = ₦2501 + ₦137.52 = ₦2638.52
   - **CRITICAL**: Verify the extra ₦100 is added!

4. **Checkout with Amount = ₦5000 (Well Above ₦2500)**
   - Add items totaling ₦5000
   - Proceed to checkout
   - Complete payment
   - **Expected**: Charge = (₦5000 × 1.5%) + ₦100 = ₦75 + ₦100 = ₦175
   - **Verify**: Total amount = ₦5000 + ₦175 = ₦5175

5. **Verification**
   - [ ] Paystack payment modal loads correctly
   - [ ] Payment successful for all test cases
   - [ ] **Pricing exception applied correctly for amounts > ₦2500**
   - [ ] Transactions recorded in database with correct amounts
   - [ ] Cart cleared after successful payment
   - [ ] Email confirmation received
   - [ ] Webhook received (check email logs)

### Pricing Verification Table

| Base Amount | Expected Charge | Expected Total | Notes |
|-------------|-----------------|----------------|-------|
| ₦1,000 | ₦15.00 | ₦1,015.00 | 1.5% only |
| ₦2,499 | ₦37.49 | ₦2,536.49 | 1.5% only |
| ₦2,500 | ₦37.50 | ₦2,537.50 | 1.5% only |
| ₦2,501 | ₦137.52 | ₦2,638.52 | **1.5% + ₦100 exception** |
| ₦3,000 | ₦145.00 | ₦3,145.00 | **1.5% + ₦100 exception** |
| ₦5,000 | ₦175.00 | ₦5,175.00 | **1.5% + ₦100 exception** |

## Test 3: Interswitch Integration

### Setup
1. Set active gateway in `config/payment_gateway.php`:
   ```php
   'active' => 'interswitch',
   ```

### Test Steps

1. **Checkout with Interswitch**
   - Add items to cart
   - Proceed to checkout
   - **Expected**: Redirected to Interswitch payment page or shown payment URL
   - Complete payment using Interswitch test credentials
   - Verify callback received

2. **Verification**
   - [ ] Payment initialization works
   - [ ] Redirected to Interswitch payment page
   - [ ] Payment can be completed
   - [ ] Callback received and processed
   - [ ] Transaction recorded in database
   - [ ] Standard pricing applied (same as Flutterwave)

## Test 4: Gateway Switching

### Test Steps

1. **Switch from Flutterwave to Paystack**
   - Complete a transaction with Flutterwave
   - Change `active` to `'paystack'` in config
   - Complete another transaction
   - **Verify**: Second transaction uses Paystack

2. **Switch from Paystack to Interswitch**
   - Complete a transaction with Paystack
   - Change `active` to `'interswitch'` in config
   - Complete another transaction
   - **Verify**: Second transaction uses Interswitch

3. **Verification**
   - [ ] Gateway switching works without code changes
   - [ ] Each gateway uses correct pricing
   - [ ] Transactions recorded correctly with different gateways

## Test 5: Webhook Testing

### Flutterwave Webhook
1. Configure webhook URL in Flutterwave dashboard: `https://yourdomain.com/model/handle-fw-webhook.php`
2. Set secret hash in config
3. Trigger a payment
4. **Verify**: Webhook received, signature validated, transaction processed

### Paystack Webhook
1. Configure webhook URL in Paystack dashboard: `https://yourdomain.com/model/handle-ps-webhook.php`
2. Enable "Charge Success" event
3. Trigger a payment
4. **Verify**: Webhook received, signature validated, transaction processed

### Interswitch Callback
1. Configure callback URL in Interswitch dashboard: `https://yourdomain.com/model/handle-isw-webhook.php`
2. Trigger a payment
3. **Verify**: Callback received, MAC validated, transaction processed

## Test 6: Subaccount Management

### Test Steps

1. **Create Settlement Accounts for Different Gateways**
   ```sql
   -- Flutterwave subaccount
   INSERT INTO settlement_accounts 
   (user_id, acct_name, acct_number, bank, subaccount_code, gateway, type) 
   VALUES (1, 'Test User', '1234567890', '044', 'FLW_SUBACCT_123', 'flutterwave', 'user');
   
   -- Paystack subaccount
   INSERT INTO settlement_accounts 
   (user_id, acct_name, acct_number, bank, subaccount_code, gateway, type) 
   VALUES (1, 'Test User', '1234567890', '044', 'SUBACCT_123', 'paystack', 'user');
   ```

2. **Test Subaccount Selection**
   - Set active gateway to Flutterwave
   - Add items and checkout
   - **Verify**: Flutterwave subaccount used
   
   - Set active gateway to Paystack
   - Add items and checkout
   - **Verify**: Paystack subaccount used

## Test 7: Edge Cases and Error Handling

### Test Steps

1. **Invalid Gateway Configuration**
   - Set active to invalid gateway name
   - Try to checkout
   - **Expected**: Error message displayed

2. **Missing Credentials**
   - Remove API keys from config
   - Try to checkout
   - **Expected**: Error message displayed

3. **Duplicate Transaction**
   - Complete a payment
   - Try to verify the same transaction again
   - **Expected**: Duplicate detected, no double processing

4. **Failed Payment**
   - Initiate payment but cancel/fail it
   - **Expected**: Transaction not recorded, cart not cleared

## Success Criteria

All tests must pass with the following criteria:

- [ ] All three gateways work independently
- [ ] Gateway switching works without code changes
- [ ] Paystack pricing exception correctly applied for amounts > ₦2500
- [ ] All webhooks/callbacks received and processed
- [ ] Correct pricing applied for each gateway
- [ ] Subaccounts correctly selected based on active gateway
- [ ] No double processing of transactions
- [ ] Error handling works for edge cases
- [ ] Database records accurate for all gateways
- [ ] Email confirmations sent for all successful transactions

## Troubleshooting

### Issue: Payment modal doesn't appear
- Check browser console for JavaScript errors
- Verify SDK scripts loaded in page source
- Check API keys are correct in config

### Issue: Wrong pricing applied
- Verify active gateway is set correctly
- Check `calculateGatewayCharges()` is being called
- Run `php test-payment-pricing.php` to verify pricing logic

### Issue: Webhook not received
- Verify webhook URL in gateway dashboard
- Check webhook secret/hash matches config
- Review server logs for incoming requests
- Test webhook delivery in gateway dashboard

### Issue: Subaccount not found
- Check `gateway` field in settlement_accounts table
- Verify records exist for active gateway
- Ensure user has registered settlement account for the gateway

## Notes

- Always use test/sandbox credentials for testing
- Keep test credentials secure and don't commit to git
- Monitor email for webhook/transaction notifications
- Check database after each test to verify data integrity
