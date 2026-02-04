<?php
// API: User Registration
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/mail.php';
require_once __DIR__ . '/../../model/functions.php';
require_once __DIR__ . '/../../config/fw.php';

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
validateRequiredFields(['email', 'password', 'first_name', 'last_name', 'phone', 'gender', 'school_id'], $input);

$email = sanitizeInput($conn, $input['email']);
$password = md5($input['password']);
$first_name = sanitizeInput($conn, $input['first_name']);
$last_name = sanitizeInput($conn, $input['last_name']);
$phone = sanitizeInput($conn, $input['phone']);
$gender = sanitizeInput($conn, $input['gender']);
$school_id = (int)$input['school_id'];

// Default role to 'student' for API
$role = 'student';

// Validate school exists and is active
$school_check = mysqli_query($conn, "SELECT id FROM schools WHERE id = $school_id AND status = 'active'");
if (mysqli_num_rows($school_check) === 0) {
    sendApiError('Invalid school_id. School does not exist or is not active.', 400);
}

// Check if user already exists
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

if (mysqli_num_rows($user_query) >= 1) {
    sendApiError('A user has been associated with this email. Please try again with another email!', 400);
}

// Create user (account not verified yet)
mysqli_query($conn, "INSERT INTO users (first_name, last_name, email, phone, password, role, school, gender, status)"
    . " VALUES ('$first_name', '$last_name', '$email', '$phone', '$password', '$role', $school_id, '$gender', 'unverified')");
$user_id = mysqli_insert_id($conn);

if (mysqli_affected_rows($conn) < 1) {
    sendApiError('Internal Server Error. Please try again later!', 500);
}

// Generate 6-digit OTP
$otp = rand(100000, 999999);
$exp_date = date('Y-m-d H:i:s', strtotime('+10 minutes'));

mysqli_query($conn, "INSERT INTO verification_code (user_id, code, exp_date) VALUES ($user_id, '$otp', '$exp_date')");

$subject = "Verify Your Account - NIVASITY";
$body = "Hello $first_name,
<br><br>
Welcome to Nivasity! We're excited to have you on board. To complete your registration and verify your email address, please use the verification code below:
<br><br>
<strong style='font-size: 24px; letter-spacing: 2px;'>$otp</strong>
<br><br>
This code will expire in 10 minutes.
<br><br>
If you did not create this account, please ignore this email.
<br><br>
Best regards,<br><b>Nivasity Team</b>";

$mailStatus = sendBrevoMail($subject, $body, $email);

if ($mailStatus === "success") {
    sendApiSuccess(
        'Registration successful! We\'ve sent a verification code (OTP) to your email address. Please check your inbox.',
        [
            'user_id' => $user_id,
            'email' => $email,
            'message' => 'Use the verify-otp endpoint to complete registration',
            'expires_in' => 600
        ],
        201
    );
} else {
    sendApiError('Failed to send verification email. Please try again later!', 500);
}
?>
