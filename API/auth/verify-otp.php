<?php
// API: Verify OTP and Complete Registration or Password Reset
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
$reason = isset($input['reason']) ? sanitizeInput($conn, $input['reason']) : 'registration';

// Get user by email
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

if (mysqli_num_rows($user_query) === 0) {
    sendApiError('Invalid email address.', 404);
}

$user = mysqli_fetch_assoc($user_query);
$user_id = (int)$user['id'];

// Verify OTP
$now = date('Y-m-d H:i:s');
$otp_query = mysqli_query($conn, "SELECT * FROM verification_code WHERE user_id = $user_id AND code = '$otp' AND exp_date >= '$now' LIMIT 1");

if (mysqli_num_rows($otp_query) === 0) {
    sendApiError('Invalid or expired OTP. Please request a new verification code.', 400);
}

// Handle based on reason
if ($reason === 'password_reset') {
    // Password Reset Flow - Generate a single-use token for password reset
    $resetPayload = [
        'user_id' => $user_id,
        'type' => 'password_reset',
        'email' => $email
    ];
    
    $resetToken = generateJWT($resetPayload, 600); // 10 minutes expiry
    
    // Remove used OTP
    mysqli_query($conn, "DELETE FROM verification_code WHERE user_id = $user_id");
    
    sendApiSuccess(
        'OTP verified successfully. Use the token to reset your password.',
        [
            'reset_token' => $resetToken,
            'expires_in' => 600 // 10 minutes in seconds
        ]
    );
} else {
    // Registration Flow - Check if user is already verified
    if ($user['status'] === 'verified') {
        sendApiError('Account already verified. Please login instead.', 400);
    }
    
    // Update user status to verified
    mysqli_query($conn, "UPDATE users SET status = 'verified' WHERE id = $user_id");
    
    // Remove used OTP
    mysqli_query($conn, "DELETE FROM verification_code WHERE user_id = $user_id");

    // Generate JWT tokens
    $tokens = generateTokenPair($user['id'], $user['role'], $user['school']);
    
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
        array_merge($tokens, ['user' => $userData])
    );
}
?>
