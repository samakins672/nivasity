<?php
// API: Admin Send Notification
// Allows system administrators to send notifications to users
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../../model/notifications.php';
require_once __DIR__ . '/../../../model/functions.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$email = isset($input['email']) ? sanitizeInput($conn, $input['email']) : '';
$password = isset($input['password']) ? $input['password'] : '';
$title = isset($input['title']) ? sanitizeInput($conn, $input['title']) : '';
$body = isset($input['body']) ? sanitizeInput($conn, $input['body']) : '';
$type = isset($input['type']) ? sanitizeInput($conn, $input['type']) : 'general';
$data = isset($input['data']) ? $input['data'] : null;

if (empty($email) || empty($password) || empty($title) || empty($body)) {
    sendApiError('Email, password, title, and body are required', 400);
}

// Validate admin credentials from admins table
// Password can be sent as MD5 hash or plain text (will be hashed if not already)
$password_hash = (strlen($password) === 32 && ctype_xdigit($password)) ? $password : md5($password);
$admin_query = mysqli_query($conn, "SELECT * FROM admins WHERE email = '$email' AND password = '$password_hash' AND status = 'active' LIMIT 1");

if (mysqli_num_rows($admin_query) === 0) {
    error_log("Admin Send Notification: Failed login attempt for email: $email");
    sendApiError('Invalid credentials', 401);
}

$admin = mysqli_fetch_assoc($admin_query);

// Determine target users
$user_ids = [];

if (isset($input['user_id']) && !empty($input['user_id'])) {
    // Single user notification
    $user_ids = [(int)$input['user_id']];
} elseif (isset($input['user_ids']) && is_array($input['user_ids'])) {
    // Multiple specific users
    $user_ids = array_map('intval', $input['user_ids']);
} elseif (isset($input['school_id']) && !empty($input['school_id'])) {
    // All users in a school
    $school_id = (int)$input['school_id'];
    $users_query = mysqli_query($conn, "SELECT id FROM users WHERE school = $school_id AND status = 'active'");
    while ($user = mysqli_fetch_assoc($users_query)) {
        $user_ids[] = (int)$user['id'];
    }
} elseif (isset($input['broadcast']) && $input['broadcast'] === true) {
    // System-wide broadcast to all active users
    $users_query = mysqli_query($conn, "SELECT id FROM users WHERE status = 'active'");
    while ($user = mysqli_fetch_assoc($users_query)) {
        $user_ids[] = (int)$user['id'];
    }
} else {
    sendApiError('Target users must be specified (user_id, user_ids, school_id, or broadcast)', 400);
}

if (empty($user_ids)) {
    sendApiError('No target users found', 400);
}

// Log notification send
error_log("Admin Send Notification: Admin {$admin['id']} ({$admin['email']}) sending notification to " . count($user_ids) . " users");

// Send notifications
if (count($user_ids) === 1) {
    // Single user
    $result = notifyUser($conn, $user_ids[0], $title, $body, $type, $data);
    
    sendApiSuccess('Notification sent successfully', [
        'notification_id' => $result['notification_id'],
        'push_sent' => $result['push_result']['success'] ?? false,
        'recipients' => 1
    ], 201);
} else {
    // Multiple users
    $result = notifyMultipleUsers($conn, $user_ids, $title, $body, $type, $data);
    
    sendApiSuccess('Notifications sent successfully', [
        'notifications_created' => $result['created_count'],
        'push_sent' => $result['push_result']['success'] ?? false,
        'recipients' => count($user_ids)
    ], 201);
}
?>
