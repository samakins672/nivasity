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
$user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

if (mysqli_num_rows($user_query) === 1) {
    // User exists - perform login
    $user = mysqli_fetch_array($user_query);
    
    // Check user status
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
    
    // If account was unverified and email is verified by Google, mark as verified
    if ($user['status'] === 'unverified' && $email_verified === 'true') {
        mysqli_query($conn, "UPDATE users SET status = 'active' WHERE id = {$user['id']}");
        $user['status'] = 'active';
    }
    
    // Update profile picture if not set
    if (empty($user['profile_pic']) && !empty($profile_pic)) {
        mysqli_query($conn, "UPDATE users SET profile_pic = '$profile_pic' WHERE id = {$user['id']}");
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
    $school_check = mysqli_query($conn, "SELECT id FROM schools WHERE id = $school_id AND status = 'active'");
    if (mysqli_num_rows($school_check) === 0) {
        sendApiError('Invalid school_id. School does not exist or is not active.', 400);
    }
    
    $status = $email_verified === 'true' ? 'active' : 'unverified';
    $role = 'student';
    
    // Generate a random password (user won't need it for Google auth)
    $random_password = md5(uniqid($google_id, true));
    
    // Default values
    $phone = $input['phone'] ?? '';
    $gender = $input['gender'] ?? '';
    
    // Create user
    mysqli_query($conn, "INSERT INTO users (first_name, last_name, email, phone, password, role, school, gender, profile_pic, status)"
        . " VALUES ('$first_name', '$last_name', '$email', '$phone', '$random_password', '$role', $school_id, '$gender', '$profile_pic', '$status')");
    $user_id = mysqli_insert_id($conn);
    
    if (mysqli_affected_rows($conn) < 1) {
        sendApiError('Failed to create user account. Please try again later!', 500);
    }
    
    // Generate JWT tokens
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
    
    sendApiSuccess('Account created and logged in successfully with Google!', $responseData, 201);
}
?>
