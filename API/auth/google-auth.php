<?php
// API: Google OAuth Authentication
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../jwt.php';
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
validateRequiredFields(['id_token'], $input);

$id_token = sanitizeInput($conn, $input['id_token']);
$school_id = isset($input['school_id']) ? (int)$input['school_id'] : null;

// Load Google OAuth credentials
$google_config_file = __DIR__ . '/../../config/google-oauth.php';
if (!file_exists($google_config_file)) {
    sendApiError('Google OAuth is not configured. Please contact the administrator.', 500);
}

require_once $google_config_file;

if (!defined('GOOGLE_ALLOWED_CLIENT_IDS') || empty(GOOGLE_ALLOWED_CLIENT_IDS)) {
    sendApiError('Google OAuth client IDs are not configured.', 500);
}

// Verify the Google ID token
$token_info_url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_info_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200 || !$response) {
    sendApiError('Invalid or expired Google ID token', 401);
}

$token_data = json_decode($response, true);

// Verify token is for our app (support multiple client IDs for Web, Android, iOS)
if (!isset($token_data['aud']) || !in_array($token_data['aud'], GOOGLE_ALLOWED_CLIENT_IDS, true)) {
    sendApiError('Google ID token is not valid for this application', 401);
}

// Verify token is not expired
if (!isset($token_data['exp']) || $token_data['exp'] < time()) {
    sendApiError('Google ID token has expired', 401);
}

// Extract user information from token
$google_id = $token_data['sub'] ?? null;
$email = $token_data['email'] ?? null;
$email_verified = $token_data['email_verified'] ?? false;
$first_name = $token_data['given_name'] ?? '';
$last_name = $token_data['family_name'] ?? '';
$profile_pic = $token_data['picture'] ?? null;

if (!$google_id || !$email) {
    sendApiError('Unable to retrieve user information from Google', 401);
}

// Check if user exists with this email
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$user_query = $stmt->get_result();
$stmt->close();

if ($user_query->num_rows === 1) {
    // User exists - perform login
    $user = $user_query->fetch_array();
    
    // Check user status - Google OAuth users also need to verify
    if ($user['status'] === 'unverified') {
        // Auto-resend verification link (same as regular login)
        require_once __DIR__ . '/../../model/mail.php';
        
        $user_id = $user['id'];
        $verificationCode = generateVerificationCode(12);
        
        while (!isCodeUnique($verificationCode, $conn, 'verification_code')) {
            $verificationCode = generateVerificationCode(12);
        }
        
        // Update or insert verification code
        $stmt = $conn->prepare("SELECT user_id FROM verification_code WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
        if ($result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE verification_code SET code = ? WHERE user_id = ?");
            $stmt->bind_param('si', $verificationCode, $user_id);
            $updateSuccess = $stmt->execute();
            $stmt->close();
            
            if (!$updateSuccess) {
                sendApiError('Your email is unverified. We encountered an issue generating a new verification link. Please try again or contact support.', 500);
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO verification_code (user_id, code) VALUES (?, ?)");
            $stmt->bind_param('is', $user_id, $verificationCode);
            $insertSuccess = $stmt->execute();
            $stmt->close();
            
            if (!$insertSuccess) {
                sendApiError('Your email is unverified. We encountered an issue generating a new verification link. Please try again or contact support.', 500);
            }
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
        $first_name_escaped = htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8');
        $verificationLinkEscaped = htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8');
        $body = "Hello $first_name_escaped,
<br><br>
We noticed you tried to log in with Google but your account is still unverified. We've sent you a verification link to complete your registration.
<br><br>
Click on the following link to verify your account: <a href='https://funaab.nivasity.com/$verificationLinkEscaped'>Verify Account</a>
<br>If you are unable to click on the link, please copy and paste the following URL into your browser: https://funaab.nivasity.com/$verificationLinkEscaped
<br><br>
Thank you for choosing Nivasity. We look forward to serving you!
<br><br>
Best regards,<br><b>Nivasity Team</b>";
        
        $mailStatus = sendBrevoMail($subject, $body, $user['email']);
        
        if ($mailStatus === "success") {
            sendApiError("Your email is unverified. We've sent you a new verification link. Please check your inbox (and spam folder).", 403);
        } else {
            sendApiError('Your email is unverified. We tried to send you a new verification link, but encountered an issue. Please use the resend verification option or contact support.', 403);
        }
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
    
    // Update profile picture if not set
    if (empty($user['profile_pic']) && !empty($profile_pic)) {
        $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
        $stmt->bind_param('si', $profile_pic, $user['id']);
        $stmt->execute();
        $stmt->close();
        $user['profile_pic'] = $profile_pic;
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
        'adm_year' => $user['adm_year'] ?? null,
        'auth_provider' => 'google'
    ];
    
    // Combine user data with tokens
    $responseData = array_merge($userData, $tokens);
    
    sendApiSuccess('Logged in successfully with Google!', $responseData);
    
} else {
    // User doesn't exist - create new account
    // school_id is required for new user registration
    if (!$school_id) {
        sendApiError('school_id is required for new user registration', 400);
    }
    
    // Validate school exists and is active
    $stmt = $conn->prepare("SELECT id FROM schools WHERE id = ? AND status = 'active'");
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $school_check = $stmt->get_result();
    $stmt->close();
    
    if ($school_check->num_rows === 0) {
        sendApiError('Invalid school_id. School does not exist or is not active.', 400);
    }
    
    // All new users start as unverified - they need to go through setup
    $status = 'unverified';
    $role = 'student';
    
    // Generate a random password (user won't need it for Google auth)
    $random_password = md5(uniqid($google_id, true));
    
    // Default values
    $phone = $input['phone'] ?? '';
    $gender = $input['gender'] ?? '';
    
    // Create user
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, school, gender, profile_pic, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssssisss', $first_name, $last_name, $email, $phone, $random_password, $role, $school_id, $gender, $profile_pic, $status);
    $stmt->execute();
    $user_id = $conn->insert_id;
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    if ($affected < 1) {
        sendApiError('Failed to create user account. Please try again later!', 500);
    }
    
    // Generate verification code for new user
    $verificationCode = generateVerificationCode(12);
    
    while (!isCodeUnique($verificationCode, $conn, 'verification_code')) {
        $verificationCode = generateVerificationCode(12);
    }
    
    // Insert verification code
    $stmt = $conn->prepare("INSERT INTO verification_code (user_id, code) VALUES (?, ?)");
    $stmt->bind_param('is', $user_id, $verificationCode);
    $insertSuccess = $stmt->execute();
    $stmt->close();
    
    if (!$insertSuccess) {
        sendApiError('Account created but failed to generate verification code. Please contact support.', 500);
    }
    
    // Prepare verification link based on role
    $verificationLink = "setup.html?verify=$verificationCode";
    
    $subject = "Verify Your Account on NIVASITY";
    $first_name_escaped = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
    $verificationLinkEscaped = htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8');
    $body = "Hello $first_name_escaped,
<br><br>
Welcome to Nivasity! You've successfully created an account using Google Sign-In. To complete your registration, please verify your email address.
<br><br>
Click on the following link to verify your account and complete setup: <a href='https://funaab.nivasity.com/$verificationLinkEscaped'>Verify Account</a>
<br>If you are unable to click on the link, please copy and paste the following URL into your browser: https://funaab.nivasity.com/$verificationLinkEscaped
<br><br>
Thank you for choosing Nivasity. We look forward to serving you!
<br><br>
Best regards,<br><b>Nivasity Team</b>";
    
    // Send verification email
    $mailStatus = sendBrevoMail($subject, $body, $email);
    
    // Note: We don't fail registration if email fails, but we inform the user
    // They can use the resend-verification endpoint to get a new link
    
    // Generate JWT tokens
    // Note: Tokens are provided immediately to allow app to persist session
    // The app should check user.status and guide unverified users through verification
    // Protected routes should verify user status before granting access
    $tokens = generateTokenPair($user_id, $role, $school_id);
    
    // Prepare user data
    $userData = [
        'id' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'phone' => $phone,
        'role' => $role,
        'gender' => $gender,
        'status' => $status,
        'profile_pic' => $profile_pic,
        'school_id' => $school_id,
        'matric_no' => null,
        'dept' => null,
        'adm_year' => null,
        'auth_provider' => 'google'
    ];
    
    // Combine user data with tokens
    $responseData = array_merge($userData, $tokens);
    
    // Inform user about email status in the message
    if ($mailStatus === "success") {
        sendApiSuccess('Account created successfully with Google! Please check your email for verification link.', $responseData, 201);
    } else {
        sendApiSuccess('Account created successfully with Google! However, we could not send the verification email. Please use the resend verification option.', $responseData, 201);
    }
}
?>
