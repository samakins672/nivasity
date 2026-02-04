<?php
// API: Forgot Password - Send OTP
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/mail.php';

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

// Check if user exists
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

if (mysqli_num_rows($user_query) === 0) {
    sendApiError('No account found with this email address.', 404);
}

$user = mysqli_fetch_assoc($user_query);
$user_id = (int)$user['id'];

// Generate 6-digit OTP
$otp = rand(100000, 999999);
$exp_date = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Remove any existing codes for the user
mysqli_query($conn, "DELETE FROM verification_code WHERE user_id = $user_id");

// Insert new OTP
$insert_query = mysqli_query($conn, "INSERT INTO verification_code (user_id, code, exp_date) VALUES ($user_id, '$otp', '$exp_date')");

if (!$insert_query) {
    sendApiError('Failed to generate OTP. Please try again.', 500);
}

// Send OTP via email
$subject = 'Password Reset Code - NIVASITY';
$body = "Hello {$user['first_name']},
<br><br>
You requested to reset your password. Use the code below to reset your password:
<br><br>
<strong style='font-size: 24px; letter-spacing: 2px;'>$otp</strong>
<br><br>
This code will expire in 10 minutes.
<br><br>
If you did not request this, please ignore this email.
<br><br>
Best regards,<br><b>Nivasity Team</b>";

$mailStatus = sendBrevoMail($subject, $body, $email);

if ($mailStatus === "success") {
    sendApiSuccess(
        'OTP sent to your email address. Please check your inbox.',
        [
            'email' => $email,
            'expires_in' => 600 // 10 minutes in seconds
        ]
    );
} else {
    sendApiError('Failed to send OTP email. Please try again later.', 500);
}
?>
