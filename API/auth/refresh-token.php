<?php
// API: Refresh Access Token
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Validate required fields
validateRequiredFields(['refresh_token'], $input);

$refreshToken = $input['refresh_token'];

// Verify refresh token
$payload = verifyJWT($refreshToken);

if (!$payload) {
    sendApiError('Invalid or expired refresh token', 401);
}

// Ensure it's a refresh token
if (!isset($payload['type']) || $payload['type'] !== 'refresh') {
    sendApiError('Invalid token type. Refresh token required.', 401);
}

$user_id = $payload['user_id'];

// Get user from database
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");

if (mysqli_num_rows($user_query) !== 1) {
    sendApiError('User not found', 404);
}

$user = mysqli_fetch_array($user_query);

// Check user status
if ($user['status'] === 'unverified') {
    sendApiError('Email is not verified.', 403);
}

if ($user['status'] === 'denied') {
    sendApiError('Account has been suspended.', 403);
}

if ($user['status'] === 'deactivated') {
    sendApiError('Account has been deactivated.', 403);
}

// Only allow student and hoc roles
if ($user['role'] !== 'student' && $user['role'] !== 'hoc') {
    sendApiError('Access denied. This API is for students only.', 403);
}

// Generate new token pair
$tokens = generateTokenPair($user['id'], $user['role'], $user['school']);

sendApiSuccess('Token refreshed successfully', $tokens);
?>
