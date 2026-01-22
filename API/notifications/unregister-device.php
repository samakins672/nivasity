<?php
/**
 * Unregister Device from Push Notifications
 * 
 * Soft-disables an Expo push token for the authenticated user
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = $user['id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($input['expo_push_token']) || empty($input['expo_push_token'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'expo_push_token is required']);
    exit;
}

$expo_push_token = trim($input['expo_push_token']);

try {
    // Soft-disable the token (set disabled_at)
    $update_query = "UPDATE notification_devices 
                    SET disabled_at = NOW(), updated_at = NOW() 
                    WHERE expo_push_token = ? AND user_id = ? AND disabled_at IS NULL";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, 'si', $expo_push_token, $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to unregister device');
    }
    
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    
    if ($affected_rows > 0) {
        // Success response
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Device unregistered',
            'data' => ['ok' => true]
        ]);
    } else {
        // Token not found or already disabled
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Device not found or already unregistered',
            'data' => ['ok' => true]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Unregister device error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to unregister device']);
}

mysqli_close($conn);
