<?php
// API: Resend Verification Email
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/mail.php';
require_once __DIR__ . '/../../model/functions.php';

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

if (mysqli_num_rows($user_query) !== 1) {
    sendApiError('We couldn\'t find that email address. Please sign up again.', 404);
}

$user = mysqli_fetch_array($user_query);

// Check if already verified
if ($user['status'] !== 'unverified') {
    sendApiError('This account has already been verified. You can go ahead and sign in.', 400);
}

$user_id = $user['id'];
$verificationCode = generateVerificationCode(12);

while (!isCodeUnique($verificationCode, $conn, 'verification_code')) {
    $verificationCode = generateVerificationCode(12);
}

// Update or insert verification code
$existingCode = mysqli_query($conn, "SELECT user_id FROM verification_code WHERE user_id = $user_id");

if (mysqli_num_rows($existingCode) > 0) {
    mysqli_query($conn, "UPDATE verification_code SET code = '$verificationCode' WHERE user_id = $user_id");
} else {
    mysqli_query($conn, "INSERT INTO verification_code (user_id, code) VALUES ($user_id, '$verificationCode')");
}

if (mysqli_affected_rows($conn) < 1 && mysqli_errno($conn) !== 0) {
    sendApiError('We couldn\'t generate a new verification link right now. Please try again shortly.', 500);
}

// Prepare verification link based on role
if ($user['role'] === 'org_admin') {
    $verificationLink = "setup_org.html?verify=$verificationCode";
} elseif ($user['role'] === 'visitor') {
    $verificationLink = "verify.html?verify=$verificationCode";
} else {
    $verificationLink = "setup.html?verify=$verificationCode";
}

$subject = "Verify Your Account on NIVASITY";
$first_name = $user['first_name'];
$body = "Hello $first_name,
<br><br>
We're sending you a new verification link so you can finish setting up your Nivasity account.
<br><br>
Click on the following link to verify your account: <a href='https://funaab.nivasity.com/$verificationLink'>Verify Account</a>
<br>If you are unable to click on the link, please copy and paste the following URL into your browser: https://funaab.nivasity.com/$verificationLink
<br><br>
Thank you for choosing Nivasity. We look forward to serving you!
<br><br>
Best regards,<br><b>Nivasity Team</b>";

$mailStatus = sendBrevoMail($subject, $body, $email);

if ($mailStatus === "success") {
    sendApiSuccess('We\'ve sent you a fresh verification link. Please check your inbox (and spam folder).');
} else {
    sendApiError('We couldn\'t send the verification email right now. Please try again shortly.', 500);
}
?>
