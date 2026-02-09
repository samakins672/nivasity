<?php
// API: Change Password
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Validate required fields
validateRequiredFields(['current_password', 'new_password'], $input);

$user_id = $user['id'];
$current_password = md5($input['current_password']);
$new_password = md5($input['new_password']);

// Verify current password
$password_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id AND password = '$current_password'");

if (mysqli_num_rows($password_query) !== 1) {
    sendApiError('Your current password is incorrect.', 400);
}

// Update password
mysqli_query($conn, "UPDATE users SET password = '$new_password' WHERE id = $user_id");

if (mysqli_affected_rows($conn) >= 1) {
    sendApiSuccess('Password successfully changed!');
} else {
    sendApiError('Internal Server Error. Please try again later!', 500);
}
?>
