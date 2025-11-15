<?php
session_start();
include('config.php');

header('Content-Type: application/json');

$statusRes = 'error';
$messageRes = 'Invalid request.';
$payload = [];

try {
  $userId = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
  if (!$userId) {
    throw new RuntimeException('You must be signed in to view support tickets.');
  }

  $ticketCode = '';
  if (isset($_GET['ticket_code'])) {
    $ticketCode = $_GET['ticket_code'];
  } elseif (isset($_POST['ticket_code'])) {
    $ticketCode = $_POST['ticket_code'];
  }

  $ticketCode = trim($ticketCode);
  if ($ticketCode === '') {
    throw new RuntimeException('Missing ticket reference.');
  }

  $ticketCodeDb = mysqli_real_escape_string($conn, $ticketCode);

  // Ensure this ticket belongs to the current user
  $ticketSql = "
    SELECT id, code, subject, status, priority, category, last_message_at, created_at
    FROM support_tickets_v2
    WHERE code = '$ticketCodeDb' AND user_id = $userId
    LIMIT 1
  ";
  $ticketQuery = mysqli_query($conn, $ticketSql);
  if (!$ticketQuery || mysqli_num_rows($ticketQuery) === 0) {
    throw new RuntimeException('Ticket not found.');
  }

  $ticket = mysqli_fetch_assoc($ticketQuery);
  $ticketId = (int)$ticket['id'];

  // Load all non-internal messages for this ticket
  $messagesSql = "
    SELECT id, sender_type, user_id, admin_id, body, is_internal, created_at
    FROM support_ticket_messages
    WHERE ticket_id = $ticketId AND is_internal = 0
    ORDER BY created_at ASC, id ASC
  ";
  $messagesQuery = mysqli_query($conn, $messagesSql);

  $messages = [];
  if ($messagesQuery) {
    while ($row = mysqli_fetch_assoc($messagesQuery)) {
      $messageId = (int)$row['id'];

      // Fetch attachments for this message
      $attachments = [];
      $attachSql = "
        SELECT id, file_path, file_name, mime_type, file_size, created_at
        FROM support_ticket_attachments
        WHERE message_id = $messageId
        ORDER BY id ASC
      ";
      $attachQuery = mysqli_query($conn, $attachSql);
      if ($attachQuery) {
        while ($att = mysqli_fetch_assoc($attachQuery)) {
          $attachments[] = [
            'id' => (int)$att['id'],
            'filePath' => $att['file_path'],
            'fileName' => $att['file_name'],
            'mimeType' => $att['mime_type'],
            'fileSize' => $att['file_size'],
            'createdAt' => $att['created_at'],
          ];
        }
      }

      $messages[] = [
        'id' => $messageId,
        'senderType' => $row['sender_type'],
        'senderUserId' => $row['user_id'] !== null ? (int)$row['user_id'] : null,
        'senderAdminId' => $row['admin_id'] !== null ? (int)$row['admin_id'] : null,
        'message' => $row['body'],
        'createdAt' => $row['created_at'],
        'attachments' => $attachments,
      ];
    }
  }

  $statusRes = 'success';
  $messageRes = 'Loaded.';
  $payload = [
    'ticket' => [
      'code' => $ticket['code'],
      'subject' => $ticket['subject'],
      'status' => $ticket['status'],
      'priority' => $ticket['priority'],
      'category' => $ticket['category'],
      'lastMessageAt' => $ticket['last_message_at'],
      'createdAt' => $ticket['created_at'],
    ],
    'messages' => $messages,
  ];
} catch (Throwable $e) {
  $statusRes = 'error';
  $messageRes = $e->getMessage();
}

echo json_encode([
  'status' => $statusRes,
  'message' => $messageRes,
  'data' => $payload,
]);

?>
