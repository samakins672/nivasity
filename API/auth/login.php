<?php
// API: User Login
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
    // Auto-resend verification link
    require_once __DIR__ . '/../../model/mail.php';
    
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
We noticed you tried to log in with an unverified account. We've sent you a new verification link to complete your registration.
<br><br>
Click on the following link to verify your account: <a href='https://funaab.nivasity.com/$verificationLink'>Verify Account</a>
<br>If you are unable to click on the link, please copy and paste the following URL into your browser: https://funaab.nivasity.com/$verificationLink
<br><br>
Thank you for choosing Nivasity. We look forward to serving you!
<br><br>
Best regards,<br><b>Nivasity Team</b>";
    
    sendBrevoMail($subject, $body, $user['email']);
    
    sendApiError('Your email is unverified. We\'ve sent you a new verification link. Please check your inbox (and spam folder).', 403);
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

// Generate JWT tokens
$tokens = generateTokenPair($user['id'], $user['role'], $user['school']);

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
    'school_id' => $user['school'],
    'matric_no' => $user['matric_no'] ?? null,
    'dept' => $user['dept'] ?? null,
    'adm_year' => $user['adm_year'] ?? null
];

// Combine user data with tokens
$responseData = array_merge($userData, $tokens);

sendApiSuccess('Logged in successfully!', $responseData);
?>
