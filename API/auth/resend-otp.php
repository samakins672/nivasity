<?php
// API: Resend Registration OTP
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
validateRequiredFields(['email'], $input);

$email = sanitizeInput($conn, $input['email']);

// Get user by email
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

if (mysqli_num_rows($user_query) === 0) {
    sendApiError('No account found with this email address.', 404);
}

$user = mysqli_fetch_assoc($user_query);
$user_id = (int)$user['id'];

// Check if user is already verified
if ($user['status'] === 'active') {
    sendApiError('Account already verified. Please login instead.', 400);
}

// Check if user account is deactivated or banned
if ($user['status'] !== 'pending') {
    sendApiError('Account cannot be verified. Please contact support.', 400);
}

// Delete any existing OTPs for this user
mysqli_query($conn, "DELETE FROM verification_code WHERE user_id = $user_id");

// Generate new 6-digit OTP
$otp = rand(100000, 999999);
$exp_date = date('Y-m-d H:i:s', strtotime('+10 minutes'));

mysqli_query($conn, "INSERT INTO verification_code (user_id, code, exp_date) VALUES ($user_id, '$otp', '$exp_date')");

$first_name = $user['first_name'];
$subject = "Verify Your Account - NIVASITY";
$body = "Hello $first_name,
<br><br>
You requested a new verification code for your Nivasity account. Please use the verification code below:
<br><br>
<strong style='font-size: 24px; letter-spacing: 2px;'>$otp</strong>
<br><br>
This code will expire in 10 minutes.
<br><br>
If you did not request this code, please ignore this email.
<br><br>
Best regards,<br><b>Nivasity Team</b>";

$mailStatus = sendBrevoMail($subject, $body, $email);

if ($mailStatus === "success") {
    sendApiSuccess(
        'Verification code sent successfully! Please check your email inbox.',
        [
            'email' => $email,
            'message' => 'Use the verify-otp endpoint to complete registration',
            'expires_in' => 600
        ]
    );
} else {
    sendApiError('Failed to send verification email. Please try again later!', 500);
}
?>
