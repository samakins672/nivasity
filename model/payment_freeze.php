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
function is_payment_frozen() {
    // Try to load the payment freeze configuration
    $configFile = __DIR__ . '/../config/payment_freeze.php';
    
    // Validate the config file path is within expected directory
    $realConfigPath = realpath($configFile);
    $expectedConfigDir = realpath(__DIR__ . '/../config');
    
    if ($realConfigPath && $expectedConfigDir && strpos($realConfigPath, $expectedConfigDir) === 0) {
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
    
    // Check if freeze is enabled
    if (!defined('PAYMENT_FREEZE_ENABLED') || !PAYMENT_FREEZE_ENABLED) {
        return false;
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
 * Get the payment freeze information
 * 
 * @return array|null Array with freeze details or null if not frozen
 */
function get_payment_freeze_info() {
    if (!is_payment_frozen()) {
        return null;
    }
    
    $expiryDate = defined('PAYMENT_FREEZE_EXPIRY') ? PAYMENT_FREEZE_EXPIRY : '';
    $customMessage = defined('PAYMENT_FREEZE_MESSAGE') ? PAYMENT_FREEZE_MESSAGE : '';
    
    // Format the expiry date for display
    $formattedExpiry = '';
    if ($expiryDate) {
        $timestamp = strtotime($expiryDate);
        
        // Validate strtotime result
        if ($timestamp !== false) {
            $formattedExpiry = date('l, F j, Y \a\t g:i A', $timestamp);
        }
    }
    
    // Build the message
    $message = '';
    if (!empty($customMessage)) {
        $message = $customMessage;
    } else {
        if ($formattedExpiry) {
            $message = "Payments are currently paused until " . $formattedExpiry . ". You will be notified when we activate all operations again.";
        } else {
            $message = "Payments are currently paused. You will be notified when we activate all operations again.";
        }
    }
    
    return [
        'enabled' => true,
        'expiry_date' => $expiryDate,
        'formatted_expiry' => $formattedExpiry,
        'message' => $message
    ];
}
