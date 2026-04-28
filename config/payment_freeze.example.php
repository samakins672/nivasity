<?php
/**
 * Payment Freeze Configuration (Example)
 * 
 * Copy this file to payment_freeze.php in the same directory.
 * This file controls the payment freeze system that allows you to temporarily
 * pause all payment operations (like a staging/maintenance mode).
 * 
 * IMPORTANT: payment_freeze.php is ignored by git to allow per-environment configuration.
 */

// ============================================================================
// PAYMENT FREEZE SETTINGS
// ============================================================================

/**
 * Enable or disable the payment freeze system
 * Set to true to freeze payments, false to allow normal payment operations
 */
define('PAYMENT_FREEZE_ENABLED', false);

/**
 * The date and time when the payment freeze will be lifted
 * Format: 'YYYY-MM-DD HH:MM:SS' (24-hour format)
 * Example: '2026-01-15 14:30:00' for January 15, 2026 at 2:30 PM
 * Note: Set this to a future date when you want the freeze to automatically end.
 * Leave this as '' when PAYMENT_FREEZE_NO_EXPIRY is true.
 */
define('PAYMENT_FREEZE_EXPIRY', 'YYYY-MM-DD HH:MM:SS');

/**
 * Keep the payment freeze active until you manually disable it.
 * When true, PAYMENT_FREEZE_EXPIRY is ignored and can be left as ''.
 */
define('PAYMENT_FREEZE_NO_EXPIRY', false);

/**
 * Custom message to display to users when payments are frozen
 * You can customize this message or leave it empty to use the default message
 * If empty, the system will display: "Payments are currently paused until [date/time]"
 */
define('PAYMENT_FREEZE_MESSAGE', '');

/**
 * Freeze scope
 * Supported values:
 * - 'all': block every payment path
 * - 'gateway': block only gateway checkout and keep wallet/free checkout available
 */
define('PAYMENT_FREEZE_SCOPE', 'all');

// Example custom messages:
// define('PAYMENT_FREEZE_MESSAGE', 'We are performing system maintenance. Payment services will resume shortly.');
// define('PAYMENT_FREEZE_MESSAGE', 'Payments are temporarily unavailable while we upgrade our systems.');
// define('PAYMENT_FREEZE_NO_EXPIRY', true);
