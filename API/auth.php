<?php
// API Authentication Middleware
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/jwt.php';

// Authenticate API request using JWT token
function authenticateApiRequest($conn) {
    // Get and validate JWT token
    $tokenPayload = validateTokenAndGetUser();
    
    if (!$tokenPayload) {
        sendApiError('Unauthorized. Invalid or missing token.', 401);
    }
    
    $user_id = $tokenPayload['user_id'];
    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
    
    if (mysqli_num_rows($user_query) !== 1) {
        sendApiError('Invalid token. User not found.', 401);
    }
    
    $user = mysqli_fetch_array($user_query);
    
    // Check user status
    if ($user['status'] === 'deactivated') {
        sendApiError('Account has been deactivated.', 403);
    }
    
    if ($user['status'] === 'denied') {
        sendApiError('Account has been suspended.', 403);
    }
    
    if ($user['status'] === 'unverified') {
        sendApiError('Email is not verified.', 403);
    }
    
    return $user;
}

// Get authenticated user (returns null if not authenticated)
function getAuthenticatedUser($conn) {
    $tokenPayload = validateTokenAndGetUser();
    
    if (!$tokenPayload) {
        return null;
    }
    
    $user_id = $tokenPayload['user_id'];
    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
    
    if (mysqli_num_rows($user_query) === 1) {
        return mysqli_fetch_array($user_query);
    }
    
    return null;
}

// Restrict to student role only
function requireStudentRole($user) {
    // Allow both 'student' and 'hoc' roles (hoc is student with privileges)
    if ($user['role'] !== 'student' && $user['role'] !== 'hoc') {
        sendApiError('Access denied. This API is for students only.', 403);
    }
}

// The API cart lives in a PHP session. Browsers calling the API cross-origin (white label) do not
// send the session cookie, so bind the session to the authenticated user instead: same cart for
// every request and device, no cookie needed. The id is an HMAC so it cannot be guessed.
function startApiCartSession($userId) {
    $userId = (int)$userId;
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secret = (defined('JWT_SECRET_KEY') && strpos(JWT_SECRET_KEY, 'INSECURE_DEFAULT_KEY') !== 0)
        ? JWT_SECRET_KEY
        : 'nivasity-api-cart';
    session_id('nvcart' . substr(hash_hmac('sha256', 'api-cart:' . $userId, $secret), 0, 40));
    session_start();
}
?>
