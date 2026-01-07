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
$messages_query = mysqli_query($conn, "SELECT sm.*, u.first_name, u.last_name, u.role, a.first_name as admin_first_name, a.last_name as admin_last_name FROM support_ticket_messages sm LEFT JOIN users u ON sm.user_id = u.id LEFT JOIN admins a ON sm.admin_id = a.id WHERE sm.ticket_id = $ticket_id ORDER BY sm.created_at ASC");

$messages = [];

while ($msg = mysqli_fetch_assoc($messages_query)) {
    $attachment = null;
    // Check for attachments in separate table
    $attachment_query = mysqli_query($conn, "SELECT file_path, original_name FROM support_ticket_attachments WHERE message_id = {$msg['id']} LIMIT 1");
    if ($attachment_row = mysqli_fetch_assoc($attachment_query)) {
        $attachment = [
            'path' => $attachment_row['file_path'],
            'original_name' => $attachment_row['original_name']
        ];
    }
    
    // Determine sender name based on sender_type
    $sender_name = 'Support Team';
    $sender_role = 'admin';
    if ($msg['sender_type'] === 'user' && $msg['user_id']) {
        $sender_name = ($msg['first_name'] && $msg['last_name']) ? $msg['first_name'] . ' ' . $msg['last_name'] : 'User';
        $sender_role = $msg['role'] ?? 'student';
    } elseif ($msg['sender_type'] === 'admin' && $msg['admin_id']) {
        $sender_name = ($msg['admin_first_name'] && $msg['admin_last_name']) ? $msg['admin_first_name'] . ' ' . $msg['admin_last_name'] : 'Support Team';
        $sender_role = 'admin';
    }
    
    $messages[] = [
        'id' => $msg['id'],
        'user_id' => $msg['user_id'],
        'user_name' => $sender_name,
        'user_role' => $sender_role,
        'message' => $msg['body'],
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
