# Gateway Tracking Implementation

This document explains the gateway tracking implementation that allows the system to track which payment gateway was used for each transaction.

## Overview

The system now tracks:
1. **Cart Table**: Which gateway was selected when items were added to cart
2. **Transactions Table**: Which gateway (medium) was used to complete the payment

## Database Changes

### Migration: `sql/add_gateway_tracking.sql`

**Cart Table:**
- Added `gateway` column (VARCHAR(20), nullable)
- Values: `flutterwave`, `paystack`, or `interswitch`
- Tracks which gateway was active when cart was created

**Transactions Table:**
- Ensured `medium` column exists (VARCHAR(50), nullable)
- Values: `flutterwave`, `paystack`, or `interswitch`
- Tracks which gateway processed the payment
- Defaults to `flutterwave` for existing records

## Code Changes

### 1. Cart Saving (`model/saveCart.php`)

**Before:**
```php
INSERT INTO cart (ref_id, user_id, item_id, type, status) VALUES (...)
```

**After:**
```php
INSERT INTO cart (ref_id, user_id, item_id, type, status, gateway) VALUES (..., 'paystack')
```

The gateway is now passed from the frontend when saving cart items.

### 2. Frontend (`index.php`)

**Flow Update:**
1. Get active gateway from `getKey.php`
2. Save cart with gateway information
3. Initialize payment with correct gateway

**Code:**
```javascript
// Get gateway first
$.ajax({
  url: 'model/getKey.php',
  success: function(data) {
    var activeGateway = data.active_gateway;
    
    // Save cart WITH gateway
    $.ajax({
      url: 'model/saveCart.php',
      data: JSON.stringify({
        gateway: activeGateway,  // NEW
        ref_id: myUniqueID,
        user_id: userId,
        items: items
      })
    });
    
    // Initialize payment...
  }
});
```

### 3. Transaction Recording

All transaction INSERT statements now include `medium`:

**Files Updated:**
- `handle-fw-webhook.php` → `medium = 'flutterwave'`
- `handle-ps-webhook.php` → `medium = 'paystack'`
- `handle-isw-webhook.php` → `medium = 'interswitch'`
- `handle-fw-payment.php` → `medium = 'flutterwave'`
- `handle-ps-payment.php` → `medium = 'paystack'`
- `handle-payment.php` → `medium = $gatewayName` (dynamic)
- `verify-pending-fw.php` → `medium = 'flutterwave'`
- `verify-pending-payment.php` → `medium = $cart_gateway` (from cart)

**Example:**
```php
// Get gateway from cart
$cart_query = mysqli_query($conn, "SELECT gateway FROM cart WHERE ref_id = '$ref_id'");
$row = mysqli_fetch_assoc($cart_query);
$cart_gateway = $row['gateway'];

// Save transaction with medium
mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) 
                     VALUES ('$ref_id', $user_id, $amount, $charge, $profit, 'successful', '$cart_gateway')");
```

## Data Flow

### Payment Initiation
1. User adds items to cart
2. User clicks "Checkout"
3. System gets active gateway: `getKey.php` returns `paystack`
4. System saves cart with gateway: `cart.gateway = 'paystack'`
5. System initializes Paystack payment
6. Payment reference created: `NIVAS-123456`

### Payment Completion (Webhook)
1. Paystack webhook received
2. System verifies payment
3. System saves transaction: `transactions.medium = 'paystack'`
4. Items delivered

### Pending Payment Verification
1. User clicks "Verify" on pending payment
2. System reads gateway from cart
3. System verifies with correct gateway API
4. System saves transaction with cart's gateway

## Benefits

### Analytics
- Track which gateway is most popular
- Compare success rates by gateway
- Analyze revenue by payment method

**Query Example:**
```sql
SELECT 
  medium,
  COUNT(*) as transaction_count,
  SUM(amount) as total_revenue,
  AVG(profit) as avg_profit
FROM transactions
WHERE status = 'successful'
GROUP BY medium;
```

### Debugging
- Quickly identify which gateway failed
- Track gateway-specific issues
- Audit payment history

### Reporting
- Generate gateway-specific reports
- Settlement reconciliation per gateway
- Financial reporting by payment method

## Migration Guide

### For Existing Deployments

1. **Backup Database:**
   ```bash
   mysqldump -u user -p database > backup.sql
   ```

2. **Run Migration:**
   ```bash
   mysql -u user -p database < sql/add_gateway_tracking.sql
   ```

3. **Verify Columns:**
   ```sql
   DESCRIBE cart;
   DESCRIBE transactions;
   ```

4. **Test Payment Flow:**
   - Add items to cart
   - Complete payment
   - Verify `cart.gateway` is populated
   - Verify `transactions.medium` is populated

### For New Deployments

The migration is included in the setup process. Just run:
```bash
mysql -u user -p database < sql/add_gateway_tracking.sql
```

## Backward Compatibility

### Existing Transactions
- Old transactions without `medium` are set to `flutterwave` by default
- This maintains consistency with the previous single-gateway system

### Existing Cart Items
- Old cart items without `gateway` will have `NULL`
- System handles NULL gracefully, defaulting to active gateway

### Legacy Handlers
- `handle-fw-payment.php` still works (hardcoded to `flutterwave`)
- `handle-ps-payment.php` still works (hardcoded to `paystack`)
- `verify-pending-fw.php` still works (hardcoded to `flutterwave`)

## Testing

### Manual Test
1. Set active gateway to Paystack
2. Add items to cart
3. Check database:
   ```sql
   SELECT ref_id, gateway FROM cart ORDER BY id DESC LIMIT 1;
   ```
4. Complete payment
5. Check database:
   ```sql
   SELECT ref_id, medium FROM transactions ORDER BY id DESC LIMIT 1;
   ```

### Expected Results
- `cart.gateway` should be `paystack`
- `transactions.medium` should be `paystack`

## Troubleshooting

### Cart Gateway is NULL
- Frontend may not be passing gateway parameter
- Check browser console for errors
- Verify `getKey.php` returns active gateway

### Transaction Medium is NULL
- Check if migration was run successfully
- Verify INSERT statement includes `medium`
- Check application logs for errors

### Gateway Mismatch
- Cart shows `paystack`, transaction shows `flutterwave`
- Possible race condition or caching issue
- Verify webhook handler uses cart gateway, not active gateway

## Future Enhancements

1. **Gateway Switching Mid-Transaction**
   - Allow fallback to alternate gateway if primary fails
   - Track attempted vs successful gateway

2. **Gateway-Specific Subaccounts**
   - Already implemented in `settlement_accounts.gateway`
   - Can be extended for more complex routing

3. **Multi-Gateway Transactions**
   - Split payment across multiple gateways
   - Track multiple mediums per transaction

4. **Gateway Analytics Dashboard**
   - Real-time gateway performance
   - Success rate comparison
   - Revenue distribution
