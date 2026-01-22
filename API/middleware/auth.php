<?php
/**
 * API Authentication Middleware
 * 
 * This file provides authentication functionality for API endpoints.
 * It validates JWT tokens and ensures users are authenticated and authorized.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

/**
 * Authenticate API request using JWT token
 * 
 * @param mysqli $conn Database connection
 * @return array User data
 * @throws Exit on authentication failure
 */
function authenticateApiRequest($conn) {
    // Get and validate JWT token
    $tokenPayload = validateTokenAndGetUser();
    
    if (!$tokenPayload) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthorized. Invalid or missing token.'
        ]);
        exit;
    }
    
    $user_id = $tokenPayload['user_id'];
    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
    
    if (mysqli_num_rows($user_query) !== 1) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid token. User not found.'
        ]);
        exit;
    }
    
    $user = mysqli_fetch_array($user_query);
    
    // Check user status
    if ($user['status'] === 'deactivated') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Account has been deactivated.'
        ]);
        exit;
    }
    
    if ($user['status'] === 'denied') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Account has been suspended.'
        ]);
        exit;
    }
    
    if ($user['status'] === 'unverified') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email is not verified.'
        ]);
        exit;
    }
    
    return $user;
}

/**
 * Get authenticated user (returns null if not authenticated)
 * 
 * @param mysqli $conn Database connection
 * @return array|null User data or null if not authenticated
 */
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

/**
 * Restrict to student role only
 * 
 * @param array $user User data
 * @throws Exit if user is not a student
 */
function requireStudentRole($user) {
    // Allow both 'student' and 'hoc' roles (hoc is student with privileges)
    if ($user['role'] !== 'student' && $user['role'] !== 'hoc') {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Access denied. This API is for students only.'
        ]);
        exit;
    }
}
?>
