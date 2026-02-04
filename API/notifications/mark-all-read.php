<?php
/**
 * Mark All Notifications as Read
 * 
 * Marks all unread notifications as read for the authenticated user
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

try {
    // Mark all unread notifications as read
    $update_query = "UPDATE notifications 
                    SET read_at = NOW() 
                    WHERE user_id = ? AND read_at IS NULL";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to mark notifications as read');
    }
    
    $affected_rows = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => "Marked {$affected_rows} notifications as read",
        'data' => [
            'ok' => true,
            'count' => $affected_rows
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Mark all notifications as read error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to mark notifications as read']);
}

mysqli_close($conn);
