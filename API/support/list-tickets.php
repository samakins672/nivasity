<?php
// API: List Support Tickets
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

$user_id = $user['id'];

// Get query parameters
$status = isset($_GET['status']) ? sanitizeInput($conn, $_GET['status']) : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

// Build where clause
$where = "user_id = $user_id";
if ($status && in_array($status, ['open', 'closed', 'in_progress'])) {
    $where .= " AND status = '$status'";
}

// Count total tickets
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM support_tickets_v2 WHERE $where");
$total = mysqli_fetch_array($count_query)['total'];

// Fetch tickets
$query = "SELECT * FROM support_tickets_v2 WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);

$tickets = [];

while ($row = mysqli_fetch_assoc($result)) {
    $ticket_id = $row['id'];
    
    // Get message count
    $msg_count_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM support_messages_v2 WHERE ticket_id = $ticket_id");
    $msg_count = mysqli_fetch_array($msg_count_query)['count'];
    
    // Get latest message
    $latest_msg_query = mysqli_query($conn, "SELECT message, created_at FROM support_messages_v2 WHERE ticket_id = $ticket_id ORDER BY created_at DESC LIMIT 1");
    $latest_msg = mysqli_fetch_assoc($latest_msg_query);
    
    $tickets[] = [
        'id' => $row['id'],
        'code' => $row['code'],
        'subject' => $row['subject'],
        'category' => $row['category'],
        'status' => $row['status'],
        'message_count' => (int)$msg_count,
        'latest_message' => $latest_msg ? substr($latest_msg['message'], 0, 100) : null,
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

sendApiSuccess('Tickets retrieved successfully', [
    'tickets' => $tickets,
    'pagination' => [
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]
]);
?>
