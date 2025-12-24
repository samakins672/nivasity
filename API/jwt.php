<?php
// JWT Helper Functions for API Authentication

// JWT Configuration
define('JWT_SECRET_KEY', getenv('JWT_SECRET_KEY') ?: 'nivasity_jwt_secret_key_change_in_production_2024');
define('JWT_ACCESS_TOKEN_EXPIRY', 3600); // 1 hour
define('JWT_REFRESH_TOKEN_EXPIRY', 604800); // 7 days

/**
 * Base64 URL encode
 */
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL decode
 */
function base64UrlDecode($data) {
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Generate JWT token
 * 
 * @param array $payload Token payload
 * @param int $expiry Expiry time in seconds (default: 1 hour)
 * @return string JWT token
 */
function generateJWT($payload, $expiry = JWT_ACCESS_TOKEN_EXPIRY) {
    $header = [
        'typ' => 'JWT',
        'alg' => 'HS256'
    ];
    
    $payload['iat'] = time();
    $payload['exp'] = time() + $expiry;
    
    $headerEncoded = base64UrlEncode(json_encode($header));
    $payloadEncoded = base64UrlEncode(json_encode($payload));
    
    $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET_KEY, true);
    $signatureEncoded = base64UrlEncode($signature);
    
    return "$headerEncoded.$payloadEncoded.$signatureEncoded";
}

/**
 * Verify and decode JWT token
 * 
 * @param string $token JWT token
 * @return array|false Decoded payload or false on failure
 */
function verifyJWT($token) {
    $parts = explode('.', $token);
    
    if (count($parts) !== 3) {
        return false;
    }
    
    list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
    
    // Verify signature
    $signature = base64UrlDecode($signatureEncoded);
    $expectedSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", JWT_SECRET_KEY, true);
    
    if (!hash_equals($expectedSignature, $signature)) {
        return false;
    }
    
    // Decode payload
    $payload = json_decode(base64UrlDecode($payloadEncoded), true);
    
    if (!$payload) {
        return false;
    }
    
    // Check expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}

/**
 * Generate access and refresh tokens for a user
 * 
 * @param int $userId User ID
 * @param string $userRole User role
 * @param int $schoolId School ID
 * @return array Access and refresh tokens
 */
function generateTokenPair($userId, $userRole, $schoolId) {
    $accessPayload = [
        'user_id' => $userId,
        'role' => $userRole,
        'school_id' => $schoolId,
        'type' => 'access'
    ];
    
    $refreshPayload = [
        'user_id' => $userId,
        'type' => 'refresh'
    ];
    
    return [
        'access_token' => generateJWT($accessPayload, JWT_ACCESS_TOKEN_EXPIRY),
        'refresh_token' => generateJWT($refreshPayload, JWT_REFRESH_TOKEN_EXPIRY),
        'token_type' => 'Bearer',
        'expires_in' => JWT_ACCESS_TOKEN_EXPIRY
    ];
}

/**
 * Extract JWT token from Authorization header
 * 
 * @return string|null JWT token or null if not found
 */
function getTokenFromHeader() {
    $headers = getallheaders();
    
    if (isset($headers['Authorization'])) {
        $authHeader = $headers['Authorization'];
        
        // Check for Bearer token
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

/**
 * Validate and extract user from JWT token
 * 
 * @return array|false User payload or false on failure
 */
function validateTokenAndGetUser() {
    $token = getTokenFromHeader();
    
    if (!$token) {
        return false;
    }
    
    $payload = verifyJWT($token);
    
    if (!$payload) {
        return false;
    }
    
    // Ensure it's an access token
    if (!isset($payload['type']) || $payload['type'] !== 'access') {
        return false;
    }
    
    return $payload;
}
?>
