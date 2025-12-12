# Pending Payment Verification Feature

## Overview
This feature allows users to verify their pending payments by entering a transaction reference. The system automatically detects whether the reference is a numeric gateway transaction ID or an alphanumeric transaction reference (tx_ref) and verifies it with the appropriate payment gateway.

## User Flow

1. **View Pending Payments**: Users can see their pending payments in the Cart tab on the store page
2. **Click "Verify Payment"**: Instead of clicking "Yes", users now click the "Verify Payment" button
3. **Enter Transaction Reference**: A modal appears prompting the user to enter their transaction reference
4. **Automatic Verification**: The system:
   - Checks if the cart is still in pending status
   - Detects if the reference is numeric (gateway transaction ID) or alphanumeric (tx_ref)
   - Verifies with the appropriate payment gateway (Flutterwave or Paystack)
   - If successful, completes the purchase and delivers the items

## Technical Implementation

### Files Modified

1. **model/cart.php**
   - Changed "Yes" button to "Verify Payment" button with class `pending-verify-btn`

2. **index.php**
   - Added modal component (`pendingPaymentModal`) with:
     - Transaction reference input field
     - Loading spinner
     - Confirm button
   - Added JavaScript handlers for:
     - Opening the modal when "Verify Payment" is clicked
     - Handling the confirm button click
     - Sending AJAX request with transaction reference
     - Showing loading state and error messages

3. **model/verify-pending-payment.php**
   - Added `transaction_ref` parameter handling
   - Added cart status check to ensure cart is still pending
   - Updated verification logic to use `transaction_ref` if provided

### Gateway Support

The feature works with both payment gateways:

#### Flutterwave
- **Numeric reference** (e.g., "1234567890"): Treated as transaction ID
  - Endpoint: `https://api.flutterwave.com/v3/transactions/{id}/verify`
- **Alphanumeric reference** (e.g., "FLW_REF_123abc"): Treated as tx_ref
  - Endpoint: `https://api.flutterwave.com/v3/transactions?tx_ref={ref}`

#### Paystack
- Both numeric and alphanumeric references work with the same endpoint
  - Endpoint: `https://api.paystack.co/transaction/verify/{reference}`

## Testing Guide

### Manual Testing

1. **Setup Test Environment**
   - Ensure you have access to a test account with pending payments
   - Have test payment gateway credentials configured

2. **Test Scenarios**

   **Scenario 1: Numeric Transaction ID (Flutterwave)**
   - Add items to cart and checkout using Flutterwave
   - Complete payment but don't return to the site
   - Go to Cart tab and find the pending payment
   - Click "Verify Payment"
   - Enter the numeric transaction ID from Flutterwave dashboard
   - Click "Confirm"
   - Expected: Payment should be verified and items delivered

   **Scenario 2: Alphanumeric Transaction Reference (Flutterwave)**
   - Same as above but enter the tx_ref instead
   - Expected: Payment should be verified and items delivered

   **Scenario 3: Paystack Reference**
   - Add items to cart and checkout using Paystack
   - Complete payment but don't return to the site
   - Go to Cart tab and find the pending payment
   - Click "Verify Payment"
   - Enter the reference from Paystack dashboard
   - Click "Confirm"
   - Expected: Payment should be verified and items delivered

   **Scenario 4: Invalid Reference**
   - Click "Verify Payment" on a pending payment
   - Enter an invalid or non-existent reference
   - Click "Confirm"
   - Expected: Error message displayed, payment remains pending

   **Scenario 5: Empty Reference**
   - Click "Verify Payment"
   - Leave the input field empty
   - Click "Confirm"
   - Expected: Warning message prompts user to enter a reference

   **Scenario 6: Already Confirmed Payment**
   - Try to verify a payment that was already confirmed
   - Expected: Error message indicating the cart is no longer pending

3. **UI/UX Testing**
   - Verify modal appears correctly
   - Verify spinner shows during verification
   - Verify button is disabled during verification
   - Verify modal closes on successful verification
   - Verify error messages display correctly
   - Verify page reloads after successful verification

### Security Considerations

- User authentication is checked before verification
- SQL injection prevention through `mysqli_real_escape_string`
- Cart ownership is verified (user_id must match)
- Cart status is checked before verification
- Transaction must be marked as successful by gateway before confirming

## Error Messages

- "Not authenticated" - User is not logged in
- "Missing parameters" - Required parameters not provided
- "Cart data not found for reference" - No cart found for the provided ref_id
- "This cart is no longer pending" - Cart has already been processed
- "No successful payment found for this transaction reference" - Gateway did not find a successful payment
- "Please enter a transaction reference" - Input field was empty

## Rollback Instructions

If needed, the changes can be rolled back by:
1. Reverting the button text from "Verify Payment" back to "Yes" in model/cart.php
2. Changing the button class from `pending-verify-btn` back to `pending-verify`
3. Removing the modal HTML from index.php
4. Removing the new JavaScript handlers from index.php
5. Reverting changes to model/verify-pending-payment.php
