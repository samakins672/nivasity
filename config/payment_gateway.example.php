<?php
/**
 * Payment Gateway Configuration
 * 
 * This file configures the payment gateways available in the system.
 * Copy this file to payment_gateway.php and update with your credentials.
 * 
 * To switch between payment providers, update the 'active' field below.
 */

return [
    // Active gateway: 'flutterwave', 'paystack', or 'interswitch'
    'active' => 'flutterwave',
    
    // Flutterwave Configuration
    'flutterwave' => [
        'public_key' => 'YOUR_FLUTTERWAVE_PUBLIC_KEY',
        'secret_key' => 'YOUR_FLUTTERWAVE_SECRET_KEY',
        'verif_hash' => 'YOUR_FLUTTERWAVE_WEBHOOK_HASH', // Optional webhook verification hash
        'enabled' => true,
    ],
    
    // Paystack Configuration
    'paystack' => [
        'public_key' => 'YOUR_PAYSTACK_PUBLIC_KEY',
        'secret_key' => 'YOUR_PAYSTACK_SECRET_KEY',
        'enabled' => true,
    ],
    
    // Interswitch Configuration
    'interswitch' => [
        'merchant_code' => 'YOUR_INTERSWITCH_MERCHANT_CODE',
        'pay_item_id' => 'YOUR_INTERSWITCH_PAY_ITEM_ID',
        'mac_key' => 'YOUR_INTERSWITCH_MAC_KEY',
        'api_key' => 'YOUR_INTERSWITCH_API_KEY',
        'enabled' => true,
        // Interswitch uses Quickteller for payouts
        'quickteller' => [
            'client_id' => 'YOUR_QUICKTELLER_CLIENT_ID',
            'client_secret' => 'YOUR_QUICKTELLER_CLIENT_SECRET',
        ],
    ],
];
