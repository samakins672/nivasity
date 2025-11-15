<?php
session_start();
include('config.php');

header('Content-Type: application/json');

$statusRes = 'error';
$messageRes = 'Invalid request.';

try {
  $userId = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
  if (!$userId) {
    throw new RuntimeException('You must be signed in to reply to support tickets.');
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new RuntimeException('Invalid request method.');
  }

  $ticketCode = isset($_POST['ticket_code']) ? trim($_POST['ticket_code']) : '';
  $messageText = isset($_POST['message']) ? trim($_POST['message']) : '';

  if ($ticketCode === '' || $messageText === '') {
    throw new RuntimeException('Message and ticket reference are required.');
  }

  $ticketCodeDb = mysqli_real_escape_string($conn, $ticketCode);

  // Ensure the ticket belongs to the current user
  $ticketSql = "
    SELECT id
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

  $currentDateTime = date("Y-m-d H:i:s");
  $messageDb = mysqli_real_escape_string($conn, $messageText);

  // Insert the reply message
  $messageSql = "
    INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at)
    VALUES ($ticketId, 'user', $userId, NULL, '$messageDb', 0, '$currentDateTime')
  ";
  if (!mysqli_query($conn, $messageSql)) {
    throw new RuntimeException('Could not save your message. Please try again later.');
  }

  $messageId = (int)mysqli_insert_id($conn);

  // Handle optional file attachment
  if (!empty($_FILES['attachment']['name'])) {
    $originalFileName = $_FILES['attachment']['name'];
    $tempName = $_FILES['attachment']['tmp_name'];
    $mimeType = isset($_FILES['attachment']['type']) ? $_FILES['attachment']['type'] : null;
    $fileSize = isset($_FILES['attachment']['size']) ? (int)$_FILES['attachment']['size'] : null;

    if ($originalFileName && $tempName) {
      $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
      $safeExtension = $extension ? preg_replace('/[^a-zA-Z0-9]/', '', $extension) : '';

      $storedName = "support_{$userId}_{$ticketCode}_{$messageId}";
      if ($safeExtension !== '') {
        $storedName .= "." . $safeExtension;
      }

      $uploadDir = "../assets/images/supports/";
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }

      $destination = $uploadDir . $storedName;

      if (move_uploaded_file($tempName, $destination)) {
        $storedFilePath = "assets/images/supports/" . $storedName;

        $filePathDb = mysqli_real_escape_string($conn, $storedFilePath);
        $fileNameDb = mysqli_real_escape_string($conn, $originalFileName);
        $mimeTypeDb = $mimeType ? mysqli_real_escape_string($conn, $mimeType) : null;

        $attachmentSql = "
          INSERT INTO support_ticket_attachments (message_id, file_path, file_name, mime_type, file_size)
          VALUES ($messageId, '$filePathDb', '$fileNameDb', " .
            ($mimeTypeDb ? "'$mimeTypeDb'" : "NULL") . ", " .
            ($fileSize !== null ? $fileSize : "NULL") . ")
        ";
        mysqli_query($conn, $attachmentSql);
      }
    }
  }

  // Update the ticket's last_message_at
  $updateTicketSql = "
    UPDATE support_tickets_v2
    SET last_message_at = '$currentDateTime'
    WHERE id = $ticketId
  ";
  mysqli_query($conn, $updateTicketSql);

  $statusRes = 'success';
  $messageRes = 'Reply sent successfully.';
} catch (Throwable $e) {
  $statusRes = 'error';
  $messageRes = $e->getMessage();
}

echo json_encode([
  'status' => $statusRes,
  'message' => $messageRes,
]);

?>
