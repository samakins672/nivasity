<?php
// API: Reply to Ticket
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/mail.php';
require_once __DIR__ . '/../../model/functions.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

$user_id = $user['id'];

// Get ticket ID and message
$ticket_id = isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : null;
$message = isset($_POST['message']) ? sanitizeInput($conn, $_POST['message']) : '';

if (!$ticket_id || empty($message)) {
    sendApiError('Ticket ID and message are required', 400);
}

// Verify ticket belongs to user
$ticket_query = mysqli_query($conn, "SELECT * FROM support_tickets_v2 WHERE id = $ticket_id AND user_id = $user_id LIMIT 1");

if (mysqli_num_rows($ticket_query) === 0) {
    sendApiError('Ticket not found', 404);
}

$ticket = mysqli_fetch_assoc($ticket_query);

// Check if ticket is closed
if ($ticket['status'] === 'closed') {
    sendApiError('Cannot reply to a closed ticket. Please create a new ticket.', 400);
}

// Handle optional attachment
$storedFilePath = null;
$originalFileName = null;
$mimeType = null;
$fileSize = null;

if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $originalFileName = $_FILES['attachment']['name'];
    $tempName = $_FILES['attachment']['tmp_name'];
    $mimeType = $_FILES['attachment']['type'] ?? null;
    $fileSize = $_FILES['attachment']['size'] ?? null;
    $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array(strtolower($extension), $allowedExtensions)) {
        sendApiError('Invalid attachment type. Only PDF and image files (JPG, JPEG, PNG) are allowed.', 400);
    }
    
    $safeExtension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);
    $storedName = "support_{$user_id}_{$ticket['code']}_" . time() . "." . $safeExtension;
    
    $uploadDir = __DIR__ . "/../../assets/images/supports/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $destination = $uploadDir . $storedName;
    
    if (move_uploaded_file($tempName, $destination)) {
        $storedFilePath = "assets/images/supports/" . $storedName;
    }
}

// Add message to database
$date = date('Y-m-d H:i:s');
mysqli_query($conn, "INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, body, is_internal, created_at) VALUES ($ticket_id, 'user', $user_id, '$message', 0, '$date')");
$message_id = mysqli_insert_id($conn);

// Create attachment if provided
if ($storedFilePath && $message_id > 0) {
    mysqli_query($conn, "INSERT INTO support_ticket_attachments (message_id, file_path, file_name, created_at) VALUES ($message_id, '$storedFilePath', '$originalFileName', '$date')");
}

// Update ticket updated_at
mysqli_query($conn, "UPDATE support_tickets_v2 SET updated_at = '$date' WHERE id = $ticket_id");

// Send notification email to support
$supportEmail = 'support@nivasity.com';
$supportSubject = "Reply on Ticket #{$ticket['code']}: {$ticket['subject']}";
$e_message = str_replace('\r\n', '<br>', $message);

$attachmentInfo = $storedFilePath ? "<br><br>File attached: <a href='https://funaab.nivasity.com/{$storedFilePath}'>View Attachment</a>" : '';

$supportMessage = "User: {$user['first_name']} {$user['last_name']} (User id: $user_id) has replied to ticket #{$ticket['code']}<br><br>Message: <br>$e_message{$attachmentInfo}";

sendMail($supportSubject, $supportMessage, $supportEmail);

sendApiSuccess('Reply added successfully', [
    'ticket_id' => $ticket_id,
    'message' => $message,
    'created_at' => $date
], 201);
?>
