<?php
/**
 * List Notifications (In-App Inbox)
 * 
 * Returns paginated list of notifications for the authenticated user
 * Includes unread count
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');

// Only GET requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = $user['id'];

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 50;
$offset = ($page - 1) * $limit;

try {
    // Get unread count
    $unread_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND read_at IS NULL";
    $stmt_unread = mysqli_prepare($conn, $unread_query);
    mysqli_stmt_bind_param($stmt_unread, 'i', $user_id);
    mysqli_stmt_execute($stmt_unread);
    $unread_result = mysqli_stmt_get_result($stmt_unread);
    $unread_count = mysqli_fetch_assoc($unread_result)['count'];
    mysqli_stmt_close($stmt_unread);
    
    // Get notifications (newest first)
    $notifications_query = "SELECT id, title, body, type, data, created_at, read_at 
                           FROM notifications 
                           WHERE user_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $notifications_query);
    mysqli_stmt_bind_param($stmt, 'iii', $user_id, $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        // Parse JSON data field if present
        $data = null;
        if ($row['data']) {
            $data = json_decode($row['data'], true);
        }
        
        $notifications[] = [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'body' => $row['body'],
            'type' => $row['type'],
            'data' => $data,
            'created_at' => $row['created_at'],
            'read_at' => $row['read_at']
        ];
    }
    
    mysqli_stmt_close($stmt);
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Notifications loaded',
        'data' => [
            'unread_count' => (int)$unread_count,
            'notifications' => $notifications,
            'page' => $page,
            'limit' => $limit
        ]
    ]);
    
} catch (Exception $e) {
    error_log("List notifications error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to load notifications']);
}

mysqli_close($conn);
