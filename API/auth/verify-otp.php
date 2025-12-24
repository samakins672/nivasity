<?php
// API: Verify OTP and Complete Registration
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
validateRequiredFields(['email', 'otp'], $input);

$email = sanitizeInput($conn, $input['email']);
$otp = sanitizeInput($conn, $input['otp']);

// Get user by email
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

if (mysqli_num_rows($user_query) === 0) {
    sendApiError('Invalid email address.', 404);
}

$user = mysqli_fetch_assoc($user_query);
$user_id = (int)$user['id'];

// Check if user is already verified
if ($user['status'] === 'active') {
    sendApiError('Account already verified. Please login instead.', 400);
}

// Verify OTP
$now = date('Y-m-d H:i:s');
$otp_query = mysqli_query($conn, "SELECT * FROM verification_code WHERE user_id = $user_id AND code = '$otp' AND exp_date >= '$now' LIMIT 1");

if (mysqli_num_rows($otp_query) === 0) {
    sendApiError('Invalid or expired OTP. Please request a new verification code.', 400);
}

// Update user status to active
mysqli_query($conn, "UPDATE users SET status = 'active' WHERE id = $user_id");

// Remove used OTP
mysqli_query($conn, "DELETE FROM verification_code WHERE user_id = $user_id");

// Generate JWT tokens
$access_token = generateAccessToken($user);
$refresh_token = generateRefreshToken($user);

// Get department info if available
$dept_name = null;
if ($user['dept']) {
    $dept_query = mysqli_query($conn, "SELECT name FROM depts WHERE id = {$user['dept']}");
    if ($dept_query && mysqli_num_rows($dept_query) > 0) {
        $dept = mysqli_fetch_assoc($dept_query);
        $dept_name = $dept['name'];
    }
}

// Prepare user data response
$userData = [
    'id' => (int)$user['id'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'email' => $user['email'],
    'phone' => $user['phone'],
    'gender' => $user['gender'],
    'role' => $user['role'],
    'profile_pic' => $user['profile_pic'],
    'school_id' => (int)$user['school'],
    'dept_id' => $user['dept'] ? (int)$user['dept'] : null,
    'dept_name' => $dept_name,
    'matric_no' => $user['matric_no'],
    'adm_year' => $user['adm_year'],
    'status' => $user['status']
];

sendApiSuccess(
    'Account verified successfully! Welcome to Nivasity.',
    [
        'access_token' => $access_token,
        'refresh_token' => $refresh_token,
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'user' => $userData
    ]
);
?>
