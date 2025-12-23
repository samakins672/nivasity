<?php
// API: Get Ticket Details
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

// Get ticket ID or code
$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$ticket_code = isset($_GET['code']) ? sanitizeInput($conn, $_GET['code']) : null;

if (!$ticket_id && !$ticket_code) {
    sendApiError('Ticket ID or code is required', 400);
}

$user_id = $user['id'];

// Build query
if ($ticket_id) {
    $ticket_query = mysqli_query($conn, "SELECT * FROM support_tickets_v2 WHERE id = $ticket_id AND user_id = $user_id LIMIT 1");
} else {
    $ticket_query = mysqli_query($conn, "SELECT * FROM support_tickets_v2 WHERE code = '$ticket_code' AND user_id = $user_id LIMIT 1");
}

if (mysqli_num_rows($ticket_query) === 0) {
    sendApiError('Ticket not found', 404);
}

$ticket = mysqli_fetch_assoc($ticket_query);
$ticket_id = $ticket['id'];

// Get messages for this ticket
$messages_query = mysqli_query($conn, "SELECT sm.*, u.first_name, u.last_name, u.role FROM support_messages_v2 sm LEFT JOIN users u ON sm.user_id = u.id WHERE sm.ticket_id = $ticket_id ORDER BY sm.created_at ASC");

$messages = [];

while ($msg = mysqli_fetch_assoc($messages_query)) {
    $attachment = null;
    if ($msg['attachment']) {
        $attachment = json_decode($msg['attachment'], true);
    }
    
    $messages[] = [
        'id' => $msg['id'],
        'user_id' => $msg['user_id'],
        'user_name' => $msg['first_name'] ? $msg['first_name'] . ' ' . $msg['last_name'] : 'Support Team',
        'user_role' => $msg['role'] ?? 'admin',
        'message' => $msg['message'],
        'attachment' => $attachment,
        'created_at' => $msg['created_at']
    ];
}

$ticketData = [
    'id' => $ticket['id'],
    'code' => $ticket['code'],
    'subject' => $ticket['subject'],
    'category' => $ticket['category'],
    'status' => $ticket['status'],
    'messages' => $messages,
    'created_at' => $ticket['created_at'],
    'updated_at' => $ticket['updated_at']
];

sendApiSuccess('Ticket details retrieved successfully', $ticketData);
?>
