<?php
/**
 * Payment Gateway Configuration Loader
 * 
 * This file maintains backward compatibility by loading credentials from
 * the payment_gateway.php config and defining legacy constants.
 * 
 * This file is part of the repository as it contains no credentials,
 * only loading logic.
 */

// Load the new payment gateway config
$paymentConfigFile = __DIR__ . '/payment_gateway.php';
$paymentConfigExample = __DIR__ . '/payment_gateway.example.php';

if (!file_exists($paymentConfigFile)) {
    if (file_exists($paymentConfigExample)) {
        // In development/example mode, use example values
        $paymentConfig = include $paymentConfigExample;
    } else {
        // Fallback to empty config
        $paymentConfig = [
            'active' => 'flutterwave',
            'flutterwave' => ['public_key' => '', 'secret_key' => '', 'verif_hash' => ''],
            'paystack' => ['public_key' => '', 'secret_key' => ''],
            'interswitch' => ['merchant_code' => '', 'pay_item_id' => '', 'mac_key' => '', 'api_key' => ''],
        ];
    }
} else {
    $paymentConfig = include $paymentConfigFile;
}

// Define constants for backward compatibility
// Flutterwave
if (isset($paymentConfig['flutterwave'])) {
    if (!defined('FLW_PUBLIC_KEY')) {
        define('FLW_PUBLIC_KEY', $paymentConfig['flutterwave']['public_key'] ?? '');
    }
    if (!defined('FLW_SECRET_KEY')) {
        define('FLW_SECRET_KEY', $paymentConfig['flutterwave']['secret_key'] ?? '');
    }
    if (!defined('FLW_VERIF_HASH')) {
        define('FLW_VERIF_HASH', $paymentConfig['flutterwave']['verif_hash'] ?? '');
    }
}

// Paystack
if (isset($paymentConfig['paystack'])) {
    if (!defined('PAYSTACK_PUBLIC_KEY')) {
        define('PAYSTACK_PUBLIC_KEY', $paymentConfig['paystack']['public_key'] ?? '');
    }
    if (!defined('PAYSTACK_SECRET_KEY')) {
        define('PAYSTACK_SECRET_KEY', $paymentConfig['paystack']['secret_key'] ?? '');
    }
}

// Interswitch
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

// Store the active gateway globally
if (!defined('ACTIVE_PAYMENT_GATEWAY')) {
    define('ACTIVE_PAYMENT_GATEWAY', $paymentConfig['active'] ?? 'flutterwave');
}

// Store config in global variable for access by gateway classes
$GLOBALS['payment_gateway_config'] = $paymentConfig;
