<?php
/**
 * Payment Freeze Helper Functions
 * 
 * This file provides utility functions for checking and managing the payment freeze system.
 */

/**
 * Check if payments are currently frozen
 * 
 * @return bool True if payments are frozen, false otherwise
 */
function payment_freeze_load_config() {
    // Try to load the payment freeze configuration
    $configFile = __DIR__ . '/../config/payment_freeze.php';
    
    // Validate the config file path is within expected directory
    $realConfigPath = realpath($configFile);
    $expectedConfigDir = realpath(__DIR__ . '/../config');
    
    // Use safer path comparison (PHP 5.4+ compatible)
    if ($realConfigPath && $expectedConfigDir && 
        substr($realConfigPath, 0, strlen($expectedConfigDir)) === $expectedConfigDir &&
        ($realConfigPath === $expectedConfigDir || $realConfigPath[strlen($expectedConfigDir)] === DIRECTORY_SEPARATOR)) {
        if (file_exists($realConfigPath)) {
            require_once $realConfigPath;
        } else {
            // If config file doesn't exist, payments are not frozen
            return false;
        }
    } else {
        // Invalid config path, payments are not frozen
        return false;
    }

    return true;
}

/**
 * Get the configured payment freeze scope.
 *
 * Supported values:
 * - all: block every checkout path
 * - gateway: block only hosted gateway checkouts and leave wallet/free flows available
 *
 * @return string
 */
function get_payment_freeze_scope() {
    if (!defined('PAYMENT_FREEZE_SCOPE')) {
        return 'all';
    }

    $scope = strtolower(trim((string) PAYMENT_FREEZE_SCOPE));

    if ($scope === 'gateway_only') {
        return 'gateway';
    }

    return $scope === 'gateway' ? 'gateway' : 'all';
}

/**
 * Check whether the payment freeze should remain active until manually disabled.
 *
 * @return bool
 */
function payment_freeze_has_no_expiry() {
    if (defined('PAYMENT_FREEZE_NO_EXPIRY') && PAYMENT_FREEZE_NO_EXPIRY) {
        return true;
    }

    if (!defined('PAYMENT_FREEZE_EXPIRY')) {
        return true;
    }

    return trim((string) PAYMENT_FREEZE_EXPIRY) === '';
}

/**
 * Check if the configured freeze is currently active.
 *
 * @return bool
 */
function is_payment_freeze_active() {
    if (!payment_freeze_load_config()) {
        return false;
    }
    
    // Check if freeze is enabled
    if (!defined('PAYMENT_FREEZE_ENABLED') || !PAYMENT_FREEZE_ENABLED) {
        return false;
    }

    if (payment_freeze_has_no_expiry()) {
        return true;
    }
    
    // Check if expiry date has passed
    if (defined('PAYMENT_FREEZE_EXPIRY')) {
        $expiryTime = strtotime(PAYMENT_FREEZE_EXPIRY);
        
        // Validate strtotime result
        if ($expiryTime === false) {
            // Invalid date format, treat as no expiry (remain frozen)
            return true;
        }
        
        $currentTime = time();
        
        // If expiry time has passed, payments are no longer frozen
        if ($currentTime >= $expiryTime) {
            return false;
        }
    }
    
    return true;
}

/**
 * Check if a payment operation is currently blocked by the freeze configuration.
 *
 * @param string|null $channel gateway, wallet, free, or null for any active freeze
 * @return bool True if the specified operation is frozen, false otherwise
 */
function is_payment_frozen($channel = null) {
    if (!is_payment_freeze_active()) {
        return false;
    }

    $scope = get_payment_freeze_scope();
    if ($scope === 'all') {
        return true;
    }

    if ($channel === null || $channel === '') {
        return true;
    }

    $normalizedChannel = strtolower(trim((string) $channel));
    return $scope === 'gateway' && $normalizedChannel === 'gateway';
}

/**
 * Get the payment freeze information
 * 
 * @return array|null Array with freeze details or null if not frozen
 */
function get_payment_freeze_info($channel = null) {
    if (!is_payment_frozen($channel)) {
        return null;
    }
    
    $expiryDate = defined('PAYMENT_FREEZE_EXPIRY') ? PAYMENT_FREEZE_EXPIRY : '';
    $customMessage = defined('PAYMENT_FREEZE_MESSAGE') ? PAYMENT_FREEZE_MESSAGE : '';
    $scope = get_payment_freeze_scope();
    $noExpiry = payment_freeze_has_no_expiry();
    
    // Format the expiry date for display
    $formattedExpiry = '';
    if (!$noExpiry && $expiryDate) {
        $timestamp = strtotime($expiryDate);
        
        // Validate strtotime result
        if ($timestamp !== false) {
            $formattedExpiry = date('l, F jS, Y', $timestamp);
        }
    }
    
    // Build the message
    $message = '';
    if (!empty($customMessage)) {
        $message = $customMessage;
    } else {
        if ($scope === 'gateway' && strtolower(trim((string) $channel)) === 'gateway') {
            if ($formattedExpiry) {
                $message = "Gateway payments are currently paused until " . $formattedExpiry . ". Only wallet payments are allowed right now.";
            } else {
                $message = "Gateway payments are currently paused. Only wallet payments are allowed right now.";
            }
        } else {
            if ($formattedExpiry) {
                $message = "Payments are currently paused until " . $formattedExpiry . ". You will be notified when we activate all operations again.";
            } else {
                $message = "Payments are currently paused. You will be notified when we activate all operations again.";
            }
        }
    }
    
    return [
        'enabled' => true,
        'scope' => $scope,
        'expiry_date' => $noExpiry ? '' : $expiryDate,
        'no_expiry' => $noExpiry,
        'formatted_expiry' => $formattedExpiry,
        'gateway_enabled' => !is_payment_frozen('gateway'),
        'wallet_enabled' => !is_payment_frozen('wallet'),
        'message' => $message
    ];
}
