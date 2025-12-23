<?php
// API: User Login
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
validateRequiredFields(['email', 'password'], $input);

$email = sanitizeInput($conn, $input['email']);
$password = md5($input['password']);

// Check if user exists
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email' AND password = '$password'");

if (mysqli_num_rows($user_query) !== 1) {
    sendApiError('Email or Password incorrect!', 401);
}

$user = mysqli_fetch_array($user_query);

// Check user status
if ($user['status'] === 'unverified') {
    sendApiError('Your email is unverified. Please check your mail inbox for the verification link.', 403);
}

if ($user['status'] === 'denied') {
    sendApiError('Your account is temporarily suspended. Contact our support team for help.', 403);
}

if ($user['status'] === 'deactivated') {
    sendApiError('Your account has been deactivated. Contact our support team to reopen your account.', 403);
}

// Only allow student and hoc roles for API
if ($user['role'] !== 'student' && $user['role'] !== 'hoc') {
    sendApiError('Access denied. This API is for students only.', 403);
}

// Start session
session_start();
$_SESSION['nivas_userId'] = $user['id'];
$_SESSION['nivas_userName'] = $user['first_name'];
$_SESSION['nivas_userRole'] = $user['role'];
$_SESSION['nivas_userSch'] = $user['school'];

// Prepare user data
$userData = [
    'id' => $user['id'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'email' => $user['email'],
    'phone' => $user['phone'],
    'role' => $user['role'],
    'gender' => $user['gender'],
    'status' => $user['status'],
    'profile_pic' => $user['profile_pic'],
    'matric_no' => $user['matric_no'] ?? null,
    'dept' => $user['dept'] ?? null,
    'adm_year' => $user['adm_year'] ?? null
];

sendApiSuccess('Logged in successfully!', $userData);
?>
