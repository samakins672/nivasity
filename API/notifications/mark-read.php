<?php
/**
 * Mark Notification as Read
 * 
 * Marks a single notification as read for the authenticated user
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
if (!isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Notification id is required']);
    exit;
}

$notification_id = intval($input['id']);

try {
    // Mark as read (only if it belongs to the user and is not already read)
    $update_query = "UPDATE notifications 
                    SET read_at = NOW() 
                    WHERE id = ? AND user_id = ? AND read_at IS NULL";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, 'ii', $notification_id, $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to mark notification as read');
    }
    
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    
    if ($affected_rows > 0) {
        // Success - notification was marked as read
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Notification marked as read',
            'data' => ['ok' => true]
        ]);
    } else {
        // Notification not found, doesn't belong to user, or already read
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Notification not found or already read',
            'data' => ['ok' => true]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Mark notification as read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to mark notification as read']);
}

mysqli_close($conn);
