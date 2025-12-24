<?php
// API: Reset Password with OTP
require_once __DIR__ . '/../config.php';

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
validateRequiredFields(['email', 'otp', 'new_password'], $input);

$email = sanitizeInput($conn, $input['email']);
$otp = sanitizeInput($conn, $input['otp']);
$new_password = md5($input['new_password']);

// Get user by email
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

if (mysqli_num_rows($user_query) === 0) {
    sendApiError('Invalid email address.', 404);
}

$user = mysqli_fetch_assoc($user_query);
$user_id = (int)$user['id'];

// Verify OTP
$now = date('Y-m-d H:i:s');
$otp_query = mysqli_query($conn, "SELECT * FROM verification_code WHERE user_id = $user_id AND code = '$otp' AND exp_date >= '$now' LIMIT 1");

if (mysqli_num_rows($otp_query) === 0) {
    sendApiError('Invalid or expired OTP. Please request a new one.', 400);
}

// Update password
$update_query = mysqli_query($conn, "UPDATE users SET password = '$new_password' WHERE id = $user_id");

if (!$update_query || mysqli_affected_rows($conn) === 0) {
    sendApiError('Failed to reset password. Please try again.', 500);
}

// Remove used OTP
mysqli_query($conn, "DELETE FROM verification_code WHERE user_id = $user_id");

sendApiSuccess('Password reset successfully! You can now login with your new password.');
?>
