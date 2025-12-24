<?php
// API: Reset Password with Token
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
validateRequiredFields(['token', 'new_password'], $input);

$token = $input['token'];
$new_password = md5($input['new_password']);

// Verify reset token
$payload = verifyJWT($token);

if (!$payload) {
    sendApiError('Invalid or expired reset token.', 401);
}

// Ensure it's a password reset token
if (!isset($payload['type']) || $payload['type'] !== 'password_reset') {
    sendApiError('Invalid token type. Please use a password reset token.', 400);
}

$user_id = (int)$payload['user_id'];
$email = $payload['email'];

// Verify user still exists
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id AND email = '$email'");

if (mysqli_num_rows($user_query) === 0) {
    sendApiError('User not found.', 404);
}

// Update password
$update_query = mysqli_query($conn, "UPDATE users SET password = '$new_password' WHERE id = $user_id");

if (!$update_query || mysqli_affected_rows($conn) === 0) {
    sendApiError('Failed to reset password. Please try again.', 500);
}

sendApiSuccess('Password reset successfully! You can now login with your new password.');
?>
