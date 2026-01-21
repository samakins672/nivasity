<?php
/**
 * Register Device for Push Notifications
 * 
 * Registers an Expo push token for the authenticated user
 * Supports multi-device (upserts based on expo_push_token)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth.php';

header('Content-Type: application/json');

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Authenticate user
$auth_result = authenticate();
if (!$auth_result['success']) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => $auth_result['message']]);
    exit;
}

$user_id = $auth_result['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['expo_push_token']) || empty($input['expo_push_token'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'expo_push_token is required']);
    exit;
}

$expo_push_token = trim($input['expo_push_token']);
$platform = isset($input['platform']) ? trim($input['platform']) : null;
$app_version = isset($input['app_version']) ? trim($input['app_version']) : null;

// Validate Expo push token format
if (!preg_match('/^ExponentPushToken\[.+\]$/', $expo_push_token) && 
    !preg_match('/^ExpoPushToken\[.+\]$/', $expo_push_token)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid expo_push_token format']);
    exit;
}

// Validate platform if provided
if ($platform !== null && !in_array($platform, ['android', 'ios', 'web'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid platform. Must be android, ios, or web']);
    exit;
}

try {
    // Check if token already exists (for any user)
    $check_query = "SELECT id, user_id, disabled_at FROM notification_devices WHERE expo_push_token = ?";
    $stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($stmt, 's', $expo_push_token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($existing = mysqli_fetch_assoc($result)) {
        // Token exists - update it
        $device_id = $existing['id'];
        
        // If token belongs to a different user, reassign it
        if ($existing['user_id'] != $user_id) {
            error_log("Expo token $expo_push_token reassigned from user {$existing['user_id']} to user $user_id");
        }
        
        // Update existing record - reactivate if disabled, update user_id if changed
        $update_query = "UPDATE notification_devices 
                        SET user_id = ?, 
                            platform = ?, 
                            app_version = ?, 
                            disabled_at = NULL, 
                            updated_at = NOW() 
                        WHERE id = ?";
        $stmt_update = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt_update, 'issi', $user_id, $platform, $app_version, $device_id);
        
        if (!mysqli_stmt_execute($stmt_update)) {
            throw new Exception('Failed to update device token');
        }
        
        mysqli_stmt_close($stmt_update);
    } else {
        // Token doesn't exist - insert new record
        $insert_query = "INSERT INTO notification_devices 
                        (user_id, expo_push_token, platform, app_version, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, NOW(), NOW())";
        $stmt_insert = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt_insert, 'isss', $user_id, $expo_push_token, $platform, $app_version);
        
        if (!mysqli_stmt_execute($stmt_insert)) {
            throw new Exception('Failed to register device token');
        }
        
        mysqli_stmt_close($stmt_insert);
    }
    
    mysqli_stmt_close($stmt);
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Device registered',
        'data' => ['ok' => true]
    ]);
    
} catch (Exception $e) {
    error_log("Register device error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to register device']);
}

mysqli_close($conn);
