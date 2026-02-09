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

// Get end_date parameter (filter notifications before this date)
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : null;

// Validate end_date format if provided (expects YYYY-MM-DD HH:MM:SS or YYYY-MM-DD)
if ($end_date !== null && $end_date !== '') {
    // Validate date format
    $date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $end_date);
    if (!$date_obj) {
        $date_obj = DateTime::createFromFormat('Y-m-d', $end_date);
        if ($date_obj) {
            $end_date = $date_obj->format('Y-m-d') . ' 23:59:59'; // End of day
        }
    }
    if (!$date_obj) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid end_date format. Use YYYY-MM-DD or YYYY-MM-DD HH:MM:SS']);
        exit;
    }
}

try {
    // Build WHERE clause for date filtering
    $where_clause = "user_id = ?";
    $where_params = [$user_id];
    $param_types = 'i';
    
    if ($end_date !== null && $end_date !== '') {
        $where_clause .= " AND created_at <= ?";
        $where_params[] = $end_date;
        $param_types .= 's';
    }
    
    // Get unread count (with date filter if provided)
    $unread_query = "SELECT COUNT(*) as count FROM notifications WHERE $where_clause AND read_at IS NULL";
    $stmt_unread = mysqli_prepare($conn, $unread_query);
    if ($end_date !== null && $end_date !== '') {
        mysqli_stmt_bind_param($stmt_unread, $param_types, ...$where_params);
    } else {
        mysqli_stmt_bind_param($stmt_unread, 'i', $user_id);
    }
    mysqli_stmt_execute($stmt_unread);
    $unread_result = mysqli_stmt_get_result($stmt_unread);
    $unread_count = mysqli_fetch_assoc($unread_result)['count'];
    mysqli_stmt_close($stmt_unread);
    
    // Get notifications (newest first)
    $notifications_query = "SELECT id, title, body, type, data, created_at, read_at 
                           FROM notifications 
                           WHERE $where_clause 
                           ORDER BY created_at DESC 
                           LIMIT ? OFFSET ?";
    $stmt = mysqli_prepare($conn, $notifications_query);
    
    // Bind parameters based on whether end_date is provided
    if ($end_date !== null && $end_date !== '') {
        $bind_params = array_merge($where_params, [$limit, $offset]);
        $bind_types = $param_types . 'ii';
        mysqli_stmt_bind_param($stmt, $bind_types, ...$bind_params);
    } else {
        mysqli_stmt_bind_param($stmt, 'iii', $user_id, $limit, $offset);
    }
    
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
