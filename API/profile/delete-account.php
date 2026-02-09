<?php
// API: Delete Account (Deactivate)
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
validateRequiredFields(['password'], $input);

$user_id = $user['id'];
$password = md5($input['password']);

// Verify password
$password_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id AND password = '$password'");

if (mysqli_num_rows($password_query) !== 1) {
    sendApiError('Password incorrect! Please try again.', 400);
}

// Deactivate account
mysqli_query($conn, "UPDATE users SET status = 'deactivated' WHERE id = $user_id");

if (mysqli_affected_rows($conn) >= 1) {
    // Destroy session
    session_start();
    session_unset();
    session_destroy();
    
    sendApiSuccess('Account successfully deactivated.');
} else {
    sendApiError('Internal Server Error. Please try again later!', 500);
}
?>
