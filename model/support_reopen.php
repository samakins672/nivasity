<?php
session_start();
include('config.php');

header('Content-Type: application/json');

$statusRes = 'error';
$messageRes = 'Invalid request.';

try {
  $userId = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
  if (!$userId) {
    throw new RuntimeException('You must be signed in to manage support tickets.');
  }

  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new RuntimeException('Invalid request method.');
  }

  $ticketCode = isset($_POST['ticket_code']) ? trim($_POST['ticket_code']) : '';
  if ($ticketCode === '') {
    throw new RuntimeException('Missing ticket reference.');
  }

  $ticketCodeDb = mysqli_real_escape_string($conn, $ticketCode);

  // Ensure the ticket belongs to the current user
  $ticketSql = "
    SELECT id, status
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
  $currentStatus = $ticket['status'];

  if ($currentStatus !== 'closed') {
    throw new RuntimeException('Only closed tickets can be reopened.');
  }

  $currentDateTime = date("Y-m-d H:i:s");

  // Update ticket status and last_message_at
  $updateSql = "
    UPDATE support_tickets_v2
    SET status = 'open', last_message_at = '$currentDateTime'
    WHERE id = $ticketId
    LIMIT 1
  ";
  if (!mysqli_query($conn, $updateSql)) {
    throw new RuntimeException('Could not reopen this ticket. Please try again later.');
  }

  // Optional system message to mark reopening
  $systemBody = 'Ticket reopened by user.';
  $systemBodyDb = mysqli_real_escape_string($conn, $systemBody);
  $messageSql = "
    INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at)
    VALUES ($ticketId, 'system', NULL, NULL, '$systemBodyDb', 0, '$currentDateTime')
  ";
  mysqli_query($conn, $messageSql);

  $statusRes = 'success';
  $messageRes = 'Ticket reopened. You can reply again.';
} catch (Throwable $e) {
  $statusRes = 'error';
  $messageRes = $e->getMessage();
}

echo json_encode([
  'status' => $statusRes,
  'message' => $messageRes,
]);

?>

