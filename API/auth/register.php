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

// Create user
mysqli_query($conn, "INSERT INTO users (first_name, last_name, email, phone, password, role, school, gender)"
    . " VALUES ('$first_name', '$last_name', '$email', '$phone', '$password', '$role', $school_id, '$gender')");
$user_id = mysqli_insert_id($conn);

if (mysqli_affected_rows($conn) < 1) {
    sendApiError('Internal Server Error. Please try again later!', 500);
}

// Generate verification code
$verificationCode = generateVerificationCode(12);

while (!isCodeUnique($verificationCode, $conn, 'verification_code')) {
    $verificationCode = generateVerificationCode(12);
}

mysqli_query($conn, "INSERT INTO verification_code (user_id, code) VALUES ($user_id, '$verificationCode')");

// Prepare verification link
$verificationLink = "setup.html?verify=$verificationCode";

$subject = "Verify Your Account on NIVASITY";
$body = "Hello $first_name,
<br><br>
Welcome to Nivasity! We're excited to have you on board. To ensure the security of your account and to provide you with the best experience, we kindly ask you to verify your email address.
<br><br>
Click on the following link to verify your account: <a href='https://funaab.nivasity.com/$verificationLink'>Verify Account</a>
<br>If you are unable to click on the link, please copy and paste the following URL into your browser: https://funaab.nivasity.com/$verificationLink
<br><br>
Thank you for choosing Nivasity. We look forward to serving you!
<br><br>
Best regards,<br><b>Nivasity Team</b>";

$mailStatus = sendBrevoMail($subject, $body, $email);

if ($mailStatus === "success") {
    sendApiSuccess(
        'Registration successful! We\'ve sent an account verification link to your email address. Please check your inbox.',
        [
            'user_id' => $user_id,
            'email' => $email
        ],
        201
    );
} else {
    sendApiError('Failed to send verification email. Please try again later!', 500);
}
?>
