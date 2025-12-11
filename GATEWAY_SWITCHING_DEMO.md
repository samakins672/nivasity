# Payment Gateway Switching Demo

## How to Switch Payment Gateways

### Current Setup
```
config/payment_gateway.php
├── active: 'flutterwave'  ← Controls which gateway is used
├── flutterwave: { credentials }
├── paystack: { credentials }
└── interswitch: { credentials }
```

### Switching Example

**To switch from Flutterwave to Paystack:**

1. Edit `config/payment_gateway.php`
2. Change one line:
   ```php
   'active' => 'paystack',  // was 'flutterwave'
   ```
3. Save the file
4. **Done!** All new transactions use Paystack

### What Happens Automatically

```
User clicks checkout
        ↓
System reads config → 'active' = 'paystack'
        ↓
PaymentGatewayFactory creates PaystackGateway
        ↓
Frontend shows Paystack payment modal
        ↓
Paystack pricing applied (1.5% + ₦100 if >₦2500)
        ↓
Payment processed through Paystack
        ↓
Webhook received at handle-ps-webhook.php
        ↓
Transaction recorded with Paystack subaccount
```

## Pricing Comparison

### Example: User buying items worth ₦3000

**With Flutterwave (active = 'flutterwave'):**
```
Base amount:  ₦3,000.00
Charge:       ₦   80.00  (2% + tiered)
Total paid:   ₦3,080.00
```

**With Paystack (active = 'paystack'):**
```
Base amount:  ₦3,000.00
Charge:       ₦  145.00  (1.5% + ₦100 exception)
Total paid:   ₦3,145.00
```

**With Interswitch (active = 'interswitch'):**
```
Base amount:  ₦3,000.00
Charge:       ₦   80.00  (2% + tiered)
Total paid:   ₦3,080.00
```

## Visual Flow Comparison

### Before This Implementation
```
User → Checkout → Flutterwave Only
                      ↓
                 Hardcoded logic
```

### After This Implementation
```
User → Checkout → Config Check
                      ↓
        ┌─────────────┼─────────────┐
        ↓             ↓             ↓
  Flutterwave    Paystack    Interswitch
        ↓             ↓             ↓
   Standard      Exception      Standard
    Pricing    (₦100 for >₦2500)  Pricing
```

## Subaccount Selection Example

### Scenario: School has accounts with all three gateways

**Database:**
```sql
settlement_accounts table:
┌────────┬──────────┬──────────────┬────────────┐
│ user_id│ subacct  │   gateway    │    type    │
├────────┼──────────┼──────────────┼────────────┤
│   10   │ FLW_123  │ flutterwave  │   school   │
│   10   │ SUBAC_456│ paystack     │   school   │
│   10   │ ISW_789  │ interswitch  │   school   │
└────────┴──────────┴──────────────┴────────────┘
```

**When active = 'flutterwave':**
- System uses: `FLW_123`

**When active = 'paystack':**
- System uses: `SUBAC_456`

**When active = 'interswitch':**
- System uses: `ISW_789`

## Webhook URLs

Each gateway needs its webhook URL configured:

```
Flutterwave Dashboard:
  → https://yourdomain.com/model/handle-fw-webhook.php

Paystack Dashboard:
  → https://yourdomain.com/model/handle-ps-webhook.php

Interswitch Dashboard:
  → https://yourdomain.com/model/handle-isw-webhook.php
```

## Testing the Switch

### Step-by-Step Test

1. **Start with Flutterwave:**
   - Set `'active' => 'flutterwave'`
   - Buy item for ₦3000
   - Verify charge is ₦80
   - Total paid: ₦3,080

2. **Switch to Paystack:**
   - Change to `'active' => 'paystack'`
   - Buy item for ₦3000
   - **Verify charge is ₦145** (₦100 exception!)
   - Total paid: ₦3,145

3. **Switch to Interswitch:**
   - Change to `'active' => 'interswitch'`
   - Buy item for ₦3000
   - Verify charge is ₦80
   - Total paid: ₦3,080

## Key Points

✅ **No code changes required** - just config update
✅ **Automatic pricing** - correct fees for each gateway
✅ **Subaccount routing** - uses right account per gateway
✅ **Webhook handling** - each gateway has its handler
✅ **Frontend adaptive** - shows correct payment modal

## Paystack Special Case

**Remember:** Paystack adds ₦100 for amounts > ₦2500

```
Amount ≤ ₦2500:  Charge = 1.5%
Amount > ₦2500:  Charge = 1.5% + ₦100  ← EXCEPTION
```

Examples:
- ₦2,500 → ₦37.50 charge
- ₦2,501 → ₦137.52 charge (notice the jump!)
- ₦5,000 → ₦175.00 charge

This is **only for Paystack**, other gateways don't have this exception.
