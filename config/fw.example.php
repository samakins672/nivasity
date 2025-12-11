<?php
/**
 * Payment Gateway Configuration File (Example)
 * 
 * Copy this file to fw.php in the same directory.
 * This file should contain your payment gateway credentials and other constants.
 * 
 * IMPORTANT: fw.php is ignored by git to keep your credentials secure.
 */

// ============================================================================
// PAYMENT GATEWAY CREDENTIALS
// ============================================================================

// Flutterwave Test Keys
define('FLW_PUBLIC_KEY_TEST', 'FLWPUBK_TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-X');
define('FLW_SECRET_KEY_TEST', 'FLWSECK_TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-X');

// Flutterwave Live/Production Keys
define('FLW_PUBLIC_KEY', 'FLWPUBK_TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-X');
define('FLW_SECRET_KEY', 'FLWSECK_TEST-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-X');

// Alternative Flutterwave Keys (if needed)
define('FLW_PUBLIC_KEY_', 'FLWPUBK-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-X');
define('FLW_SECRET_KEY_', 'FLWSECK-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx-X');
define('FLW_VERIF_HASH', 'your_webhook_verification_hash');

// Paystack Keys
define('PAYSTACK_PUBLIC_KEY', 'pk_live_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('PAYSTACK_SECRET_KEY', 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// ============================================================================
// STAGING ACCESS CONTROL
// ============================================================================

// Set to true to restrict front-end access to a specific tester account.
// When enabled, only the email in STAGING_ALLOWED_EMAIL can log in
// and view pages that include page_config.php
define('STAGING_GATE', false);
define('STAGING_ALLOWED_EMAIL', 'your_email@example.com');

// ============================================================================
// MULTI-GATEWAY SUPPORT (New System)
// ============================================================================

// Load the new payment gateway configuration if it exists
// This allows switching between Flutterwave, Paystack, and Interswitch
$paymentConfigFile = __DIR__ . '/payment_gateway.php';

if (file_exists($paymentConfigFile)) {
    $paymentConfig = include $paymentConfigFile;
    
    // Store config in global variable for access by gateway classes
    $GLOBALS['payment_gateway_config'] = $paymentConfig;
    
    // Define the active gateway constant
    if (!defined('ACTIVE_PAYMENT_GATEWAY')) {
        define('ACTIVE_PAYMENT_GATEWAY', $paymentConfig['active'] ?? 'flutterwave');
    }
    
    // Define Interswitch constants if configured
    if (isset($paymentConfig['interswitch'])) {
        if (!defined('INTERSWITCH_MERCHANT_CODE')) {
            define('INTERSWITCH_MERCHANT_CODE', $paymentConfig['interswitch']['merchant_code'] ?? '');
        }
        if (!defined('INTERSWITCH_PAY_ITEM_ID')) {
            define('INTERSWITCH_PAY_ITEM_ID', $paymentConfig['interswitch']['pay_item_id'] ?? '');
        }
        if (!defined('INTERSWITCH_MAC_KEY')) {
            define('INTERSWITCH_MAC_KEY', $paymentConfig['interswitch']['mac_key'] ?? '');
        }
        if (!defined('INTERSWITCH_API_KEY')) {
            define('INTERSWITCH_API_KEY', $paymentConfig['interswitch']['api_key'] ?? '');
        }
    }
} else {
    // Fallback: If payment_gateway.php doesn't exist, use legacy defines
    if (!defined('ACTIVE_PAYMENT_GATEWAY')) {
        define('ACTIVE_PAYMENT_GATEWAY', 'flutterwave');
    }
    
    // Create a minimal config from the legacy defines
    $GLOBALS['payment_gateway_config'] = [
        'active' => 'flutterwave',
        'flutterwave' => [
            'public_key' => FLW_PUBLIC_KEY ?? '',
            'secret_key' => FLW_SECRET_KEY ?? '',
            'verif_hash' => FLW_VERIF_HASH ?? '',
        ],
        'paystack' => [
            'public_key' => PAYSTACK_PUBLIC_KEY ?? '',
            'secret_key' => PAYSTACK_SECRET_KEY ?? '',
        ],
    ];
}
