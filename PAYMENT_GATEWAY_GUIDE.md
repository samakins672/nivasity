# Payment Gateway Integration Guide

This document explains how to configure and switch between Flutterwave, Paystack, and Interswitch payment gateways in the Nivasity system.

## Overview

The payment system supports three payment gateways:
- **Flutterwave**: Complete integration with existing flow
- **Paystack**: Complete integration with special pricing exception
- **Interswitch/Quickteller**: Complete integration from scratch

## Configuration

### 1. Database Setup

Run the migration to add gateway support to settlement accounts:

```bash
mysql -u username -p database_name < sql/add_gateway_to_settlement_accounts.sql
```

This adds a `gateway` field to the `settlement_accounts` table to track which gateway each subaccount belongs to.

### 2. Gateway Configuration

1. Copy the example config file:
   ```bash
   cp config/payment_gateway.php.example config/payment_gateway.php
   ```

2. Edit `config/payment_gateway.php` and add your credentials:

```php
return [
    // Set the active gateway: 'flutterwave', 'paystack', or 'interswitch'
    'active' => 'flutterwave',
    
    'flutterwave' => [
        'public_key' => 'FLWPUBK-xxxxxxxxxxxxxxx',
        'secret_key' => 'FLWSECK-xxxxxxxxxxxxxxx',
        'verif_hash' => 'your-webhook-hash',
        'enabled' => true,
    ],
    
    'paystack' => [
        'public_key' => 'pk_test_xxxxxxxxxxxxxxx',
        'secret_key' => 'sk_test_xxxxxxxxxxxxxxx',
        'enabled' => true,
    ],
    
    'interswitch' => [
        'merchant_code' => 'MXXXXXXx',
        'pay_item_id' => 'Default_Payable_MX1234',
        'mac_key' => 'your-mac-key',
        'api_key' => 'your-api-key',
        'enabled' => true,
        'quickteller' => [
            'client_id' => 'your-client-id',
            'client_secret' => 'your-client-secret',
        ],
    ],
];
```

### 3. Webhook Setup

Configure webhooks in each gateway's dashboard:

#### Flutterwave
- **URL**: `https://yourdomain.com/model/handle-fw-webhook.php`
- **Secret Hash**: Set in config as `verif_hash`

#### Paystack
- **URL**: `https://yourdomain.com/model/handle-ps-webhook.php`
- **Events**: Select "Charge Success"

#### Interswitch
- **Callback URL**: `https://yourdomain.com/model/handle-isw-webhook.php`
- Configure in merchant dashboard

## Switching Between Gateways

To switch the active payment gateway, simply update the `active` field in `config/payment_gateway.php`:

```php
'active' => 'paystack', // Change from 'flutterwave' to 'paystack'
```

The system will automatically:
- Use the correct gateway for new transactions
- Apply the appropriate pricing logic
- Route webhooks to the correct handler
- Use gateway-specific subaccounts

## Pricing Logic

### Flutterwave (Standard Pricing)
- Amount < ₦2,500: Flat ₦70 fee
- Amount ≥ ₦2,500: 2% + tiered fee (₦20-₦50)

### Paystack (Special Exception)
- Amount ≤ ₦2,500: 1.5% fee
- Amount > ₦2,500: 1.5% fee + **₦100 flat fee** (EXCEPTION)

### Interswitch (Standard Pricing)
- Same as Flutterwave: Standard tiered pricing

## Subaccount Management

Each gateway uses different subaccount systems:

### Adding Subaccounts

When adding settlement accounts, specify the gateway:

```sql
INSERT INTO settlement_accounts 
(user_id, acct_name, acct_number, bank, subaccount_code, gateway, type) 
VALUES 
(1, 'John Doe', '1234567890', '044', 'SUBACCT_CODE_HERE', 'paystack', 'user');
```

### Gateway-Specific Subaccounts

The system automatically selects the correct subaccount based on:
1. Current active gateway
2. School-level account (if available)
3. Fallback to user account

## Manual Testing Instructions

### Testing Flutterwave

1. Set active gateway to `'flutterwave'` in config
2. Add items to cart
3. Proceed to checkout
4. Complete payment using Flutterwave test cards:
   - **Card**: 4187427415564246
   - **CVV**: 828
   - **Expiry**: 09/32
   - **PIN**: 3310
   - **OTP**: 12345
5. Verify:
   - Transaction appears in `transactions` table
   - Items appear in `manuals_bought`/`event_tickets`
   - Webhook is received (check email for confirmation)
   - Correct pricing applied

### Testing Paystack

1. Set active gateway to `'paystack'` in config
2. Add items to cart (test both ≤₦2,500 and >₦2,500)
3. Proceed to checkout
4. Complete payment using Paystack test cards:
   - **Card**: 4084084084084081
   - **CVV**: 408
   - **Expiry**: Any future date
   - **PIN**: 0000
5. Verify:
   - For amount >₦2,500: Extra ₦100 fee is added
   - Transaction recorded correctly
   - Webhook received
   - Items delivered to buyer

### Testing Interswitch

1. Set active gateway to `'interswitch'` in config
2. Add items to cart
3. Proceed to checkout
4. Complete payment using Interswitch test credentials
5. Verify callback is received and processed

### Verification Checklist

For each gateway, verify:
- [ ] Payment initialization works
- [ ] Transaction verification succeeds
- [ ] Correct pricing/fees applied
- [ ] Webhook/callback received and processed
- [ ] Items delivered to buyer
- [ ] Email confirmation sent
- [ ] Transaction recorded in database
- [ ] Cart cleared after successful payment

## Troubleshooting

### Common Issues

1. **Payment verification fails**
   - Check API credentials in config
   - Verify network connectivity
   - Check gateway API status

2. **Webhook not received**
   - Verify webhook URL in gateway dashboard
   - Check webhook secret/hash configuration
   - Review server logs for errors

3. **Wrong pricing applied**
   - Verify active gateway is correct
   - Check `calculateGatewayCharges()` is being called
   - For Paystack, verify amount is >₦2,500 for exception

4. **Subaccount not found**
   - Ensure settlement_accounts table has records
   - Verify `gateway` field matches active gateway
   - Check user/school has registered settlement account

## Architecture

### Core Components

1. **PaymentGateway Interface** (`model/PaymentGateway.php`)
   - Defines standard methods all gateways must implement

2. **Gateway Implementations**
   - `FlutterwaveGateway.php`: Flutterwave integration
   - `PaystackGateway.php`: Paystack with pricing exception
   - `InterswitchGateway.php`: Interswitch/Quickteller

3. **PaymentGatewayFactory** (`model/PaymentGatewayFactory.php`)
   - Creates gateway instances based on config

4. **Handlers**
   - `handle-payment.php`: Unified payment verification
   - `handle-fw-webhook.php`: Flutterwave webhooks
   - `handle-ps-webhook.php`: Paystack webhooks
   - `handle-isw-webhook.php`: Interswitch callbacks

5. **Helper Functions** (`model/functions.php`)
   - `calculateGatewayCharges()`: Gateway-aware pricing
   - `getSettlementSubaccount()`: Gateway-aware subaccount lookup

## Migration from Old System

The new system is backward compatible:
- Existing `handle-fw-payment.php` and `handle-ps-payment.php` still work
- New unified handler is available at `handle-payment.php`
- Config file `config/fw.php` loads from new `payment_gateway.php`
- Constants like `FLW_SECRET_KEY` still defined for compatibility

## Security Considerations

1. **Webhook Verification**
   - All webhooks verify signatures/hashes
   - Invalid signatures are rejected with 403 response

2. **Duplicate Prevention**
   - System checks for duplicate transactions
   - Uses reference ID to prevent double-processing

3. **Configuration**
   - `payment_gateway.php` should be in `.gitignore`
   - Never commit API keys to version control

## Pending Payment Verification

The system includes a unified pending payment verification system that works with all three gateways.

### How It Works

1. **Payment Initiation**
   - User adds items to cart
   - System saves cart items with `status = 'pending'`
   - Payment is initiated with active gateway
   - Transaction reference is stored

2. **Pending Payments Display**
   - Users see pending payments in the Cart tab
   - Each pending payment has:
     - "Verify" button to check payment status
     - "Cancel" button to mark as cancelled

3. **Verification Process** (`model/verify-pending-payment.php`)
   - Determines active gateway from configuration
   - Routes verification to appropriate gateway API:
     - **Flutterwave**: Queries `/v3/transactions?tx_ref=`
     - **Paystack**: Calls `/transaction/verify/{reference}`
     - **Interswitch**: Uses `/gettransaction.json` endpoint
   - Verifies payment was successful
   - Uses gateway-specific charge calculation
   - Processes purchase and delivers items
   - Sends confirmation email

4. **Duplicate Prevention**
   - System checks if transaction already processed
   - Prevents double-delivery of items
   - Returns success if already processed

### User Experience

**Pending Payment:**
```
Reference: NIVAS-1234567890
Status: Pending
Amount: ₦5,000.00
[Verify] [Cancel]
```

**After Verification:**
- If successful: Items delivered, cart cleared, confirmation email sent
- If still pending: User notified to retry later
- If failed: Payment remains in pending state

### Technical Details

**Endpoint:** `model/verify-pending-payment.php`

**Request:**
```javascript
$.ajax({
  type: 'POST',
  url: 'model/verify-pending-payment.php',
  data: { 
    ref_id: 'NIVAS-1234567890', 
    action: 'verify' // or 'cancel'
  }
})
```

**Response:**
```json
{
  "status": "success",
  "message": "Payment confirmed and items delivered",
  "gateway": "paystack"
}
```

### Gateway-Specific Verification

**Flutterwave:**
- Verifies using transaction reference (tx_ref)
- Checks status is 'successful'
- Uses Flutterwave 2.15% pricing

**Paystack:**
- Verifies using reference
- Checks status code and data
- Uses Paystack special pricing (₦100 flat <₦2500)

**Interswitch:**
- Verifies using transaction reference
- Checks ResponseCode is '00'
- Uses Interswitch 2% charge, 1.65% profit calculation

### Migration from Old System

**Old System:**
- `verify-pending-fw.php` - Flutterwave only

**New System:**
- `verify-pending-payment.php` - All gateways
- Automatically routes to correct gateway
- Frontend updated to use new endpoint

**Backward Compatibility:**
- Old `verify-pending-fw.php` still exists
- Can be used for Flutterwave-only deployments
- New deployments should use unified endpoint

## Support

For issues or questions:
- Check webhook logs via email notifications
- Review database for transaction records
- Verify gateway dashboard for transaction status
- Contact gateway support for API-specific issues
- For pending payment issues, check active gateway configuration
