<?php
// API Authentication Middleware
require_once __DIR__ . '/config.php';

// Authenticate API request using session
function authenticateApiRequest($conn) {
    // For API, we'll use session-based authentication
    // In a production environment, consider using JWT tokens
    session_start();
    
    if (!isset($_SESSION['nivas_userId'])) {
        sendApiError('Unauthorized. Please login first.', 401);
    }
    
    $user_id = $_SESSION['nivas_userId'];
    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
    
    if (mysqli_num_rows($user_query) !== 1) {
        sendApiError('Invalid session. Please login again.', 401);
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

// Get authenticated user
function getAuthenticatedUser($conn) {
    session_start();
    
    if (!isset($_SESSION['nivas_userId'])) {
        return null;
    }
    
    $user_id = $_SESSION['nivas_userId'];
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
?>
