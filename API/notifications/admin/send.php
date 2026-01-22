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
// Password must be MD5 hash (32 character hexadecimal)
if (strlen($password) !== 32 || !ctype_xdigit($password)) {
    sendApiError('Password must be MD5 hash (32 character hexadecimal string)', 400);
}

$admin_query = mysqli_query($conn, "SELECT * FROM admins WHERE email = '$email' AND password = '$password' AND status = 'active' LIMIT 1");

if (mysqli_num_rows($admin_query) === 0) {
    error_log("Admin Send Notification: Failed login attempt for email: $email");
    sendApiError('Invalid credentials', 401);
}

$admin = mysqli_fetch_assoc($admin_query);

// Enforce role restriction: only roles 1, 2, or 3 are allowed
$allowed_roles = [1, 2, 3];
if (!in_array((int)$admin['role'], $allowed_roles)) {
    error_log("Admin Send Notification: Unauthorized role {$admin['role']} for email: $email");
    sendApiError('Insufficient permissions to send notifications', 403);
}

// Determine target users - only ONE targeting method allowed
$user_ids = [];
$targeting_methods_count = 0;

// Count how many targeting methods are provided
if (isset($input['user_id']) && !empty($input['user_id'])) $targeting_methods_count++;
if (isset($input['user_ids']) && is_array($input['user_ids']) && !empty($input['user_ids'])) $targeting_methods_count++;
if (isset($input['school_id']) && !empty($input['school_id'])) $targeting_methods_count++;
if (isset($input['broadcast']) && $input['broadcast'] === true) $targeting_methods_count++;

// Only allow ONE targeting method
if ($targeting_methods_count === 0) {
    sendApiError('Target users must be specified (user_id, user_ids, school_id, or broadcast)', 400);
}

if ($targeting_methods_count > 1) {
    sendApiError('Only one targeting method allowed (user_id, user_ids, school_id, or broadcast)', 400);
}

if (isset($input['user_id']) && !empty($input['user_id'])) {
    // Single user notification
    $user_ids = [(int)$input['user_id']];
    error_log("Admin Send: Targeting single user_id: {$input['user_id']}");
} elseif (isset($input['user_ids']) && is_array($input['user_ids'])) {
    // Multiple specific users
    $user_ids = array_map('intval', $input['user_ids']);
    error_log("Admin Send: Targeting user_ids array: " . json_encode($user_ids));
} elseif (isset($input['school_id']) && !empty($input['school_id'])) {
    // All users in a school
    $school_id = (int)$input['school_id'];
    $users_query = mysqli_query($conn, "SELECT id FROM users WHERE school = $school_id AND status = 'active'");
    while ($user = mysqli_fetch_assoc($users_query)) {
        $user_ids[] = (int)$user['id'];
    }
    error_log("Admin Send: Targeting school_id $school_id, found " . count($user_ids) . " users");
} elseif (isset($input['broadcast']) && $input['broadcast'] === true) {
    // System-wide broadcast to all active users
    $users_query = mysqli_query($conn, "SELECT id FROM users WHERE status = 'active'");
    while ($user = mysqli_fetch_assoc($users_query)) {
        $user_ids[] = (int)$user['id'];
    }
    error_log("Admin Send: Broadcasting to all users, found " . count($user_ids) . " users");
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
    
    if (!$result['success']) {
        sendApiError($result['message'], 400);
    }
    
    sendApiSuccess('Notification sent successfully', [
        'notification_id' => $result['notification_id'],
        'push_sent' => $result['push_result']['success'] ?? false,
        'recipients' => 1
    ], 201);
} else {
    // Multiple users
    $result = notifyMultipleUsers($conn, $user_ids, $title, $body, $type, $data);
    
    // Check if any notifications were created
    if ($result['created_count'] === 0) {
        sendApiError('No notifications created - no users have registered devices', 400);
    }
    
    sendApiSuccess('Notifications sent successfully', [
        'notifications_created' => $result['created_count'],
        'push_sent' => $result['push_result']['success'] ?? false,
        'recipients' => $result['created_count']
    ], 201);
}
?>
