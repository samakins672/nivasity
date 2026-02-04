<?php
/**
 * JWT Configuration File (Example)
 * 
 * Copy this file to jwt.php in the same directory.
 * This file should contain your JWT secret key and token expiry settings.
 * 
 * IMPORTANT: jwt.php is ignored by git to keep your secret key secure.
 */

// ============================================================================
// JWT SECRET KEY
// ============================================================================

/**
 * JWT Secret Key - CRITICAL SECURITY SETTING
 * 
 * This key is used to sign and verify JWT tokens. It must be:
 * 1. At least 32 characters long
 * 2. Randomly generated and unique to your application
 * 3. Never shared publicly or committed to version control
 * 4. Changed immediately if compromised
 * 
 * To generate a secure key, you can use:
 * - openssl rand -base64 64
 * - Or any cryptographically secure random string generator
 * 
 * PRODUCTION: Store this in an environment variable for maximum security
 * Example: getenv('JWT_SECRET_KEY') ?: 'fallback_key_for_dev_only'
 */
define('JWT_SECRET_KEY', 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING_AT_LEAST_32_CHARACTERS_FOR_PRODUCTION');

// ============================================================================
// TOKEN EXPIRY SETTINGS
// ============================================================================

/**
 * Access Token Expiry (in seconds)
 * Default: 3600 (1 hour)
 * 
 * Access tokens are short-lived for security. Users authenticate with these
 * tokens for each API request. When expired, they use the refresh token.
 */
define('JWT_ACCESS_TOKEN_EXPIRY', 3600);

/**
 * Refresh Token Expiry (in seconds)
 * Default: 604800 (7 days)
 * 
 * Refresh tokens are long-lived and used to obtain new access tokens
 * without requiring the user to log in again.
 */
define('JWT_REFRESH_TOKEN_EXPIRY', 604800);

// ============================================================================
// OPTIONAL: ENVIRONMENT-BASED CONFIGURATION
// ============================================================================

/**
 * If you prefer to use environment variables (recommended for production):
 * 
 * 1. Set JWT_SECRET_KEY in your server environment variables
 * 2. Uncomment the line below to use environment variable with fallback
 */
// define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY') ?: 'fallback_dev_key_change_in_production');

/**
 * For different environments, you might want different expiry times:
 */
// define('JWT_ACCESS_TOKEN_EXPIRY', getenv('JWT_ACCESS_EXPIRY') ?: 3600);
// define('JWT_REFRESH_TOKEN_EXPIRY', getenv('JWT_REFRESH_EXPIRY') ?: 604800);
?>
