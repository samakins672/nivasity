<?php
// API: Create Support Ticket
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

// Handle multipart form data (for file upload)
$subject = isset($_POST['subject']) ? sanitizeInput($conn, $_POST['subject']) : '';
$message = isset($_POST['message']) ? sanitizeInput($conn, $_POST['message']) : '';
$category = isset($_POST['category']) ? sanitizeInput($conn, $_POST['category']) : 'Technical and Other Issues';

// Validate required fields
if (empty($subject) || empty($message)) {
    sendApiError('Subject and message are required', 400);
}

// Generate unique ticket code
$ticketCode = generateVerificationCode(8);
while (!isCodeUnique($ticketCode, $conn, 'support_tickets_v2')) {
    $ticketCode = generateVerificationCode(8);
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
    $storedName = "support_{$user_id}_{$ticketCode}." . $safeExtension;
    
    $uploadDir = __DIR__ . "/../../assets/images/supports/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $destination = $uploadDir . $storedName;
    
    if (move_uploaded_file($tempName, $destination)) {
        $storedFilePath = "assets/images/supports/" . $storedName;
    }
}

// Create ticket in database
$date = date('Y-m-d H:i:s');
mysqli_query($conn, "INSERT INTO support_tickets_v2 (user_id, code, subject, category, status, created_at) VALUES ($user_id, '$ticketCode', '$subject', '$category', 'open', '$date')");
$ticket_id = mysqli_insert_id($conn);

if ($ticket_id === 0) {
    sendApiError('Failed to create ticket', 500);
}

// Create first message
mysqli_query($conn, "INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, body, created_at) VALUES ($ticket_id, 'user', $user_id, '$message', '$date')");
$message_id = mysqli_insert_id($conn);

// Create attachment if provided
if ($storedFilePath && $message_id > 0) {
    mysqli_query($conn, "INSERT INTO support_attachments (message_id, file_path, original_name, created_at) VALUES ($message_id, '$storedFilePath', '$originalFileName', '$date')");
}

// Send email to support
$supportEmail = 'support@nivasity.com';
$supportSubject = "Important: New Support Request - Ticket #$ticketCode";
$e_message = str_replace('\r\n', '<br>', $message);

$attachmentInfo = $storedFilePath ? "<br><br>File attached: <a href='https://funaab.nivasity.com/{$storedFilePath}'>View Attachment</a>" : '';

$supportMessage = "User: {$user['first_name']} {$user['last_name']} (User id: $user_id)<br>Email: <a href='mailto:{$user['email']}'>{$user['email']}</a><br>Category: $category<br>Message: <br>$e_message{$attachmentInfo}";

sendMail($supportSubject, $supportMessage, $supportEmail);

// Send confirmation email to user
$userSubject = "Support Request Received - Ticket #$ticketCode";
$userMessage = "Hello {$user['first_name']},<br><br>We've received your support request and our team will review it shortly. Your ticket number is <b>#$ticketCode</b>.<br><br>We'll get back to you as soon as possible.<br><br>Best regards,<br><b>Nivasity Support Team</b>";

sendBrevoMail($userSubject, $userMessage, $user['email']);

sendApiSuccess('Support ticket created successfully', [
    'ticket_id' => $ticket_id,
    'ticket_code' => $ticketCode,
    'subject' => $subject,
    'category' => $category,
    'status' => 'open',
    'created_at' => $date
], 201);
?>
