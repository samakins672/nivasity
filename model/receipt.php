<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mail.php';

header_remove('X-Powered-By');

function respond_json($status, $message = '', $http = 200) {
  http_response_code($http);
  header('Content-Type: application/json');
  echo json_encode(['status' => $status, 'message' => $message]);
  exit;
}

if (!isset($_SESSION['nivas_userId'])) {
  // Not logged in
  if (isset($_GET['action']) && strtolower($_GET['action']) === 'download') {
    http_response_code(302);
    header('Location: ../signin.html');
    exit;
  }
  respond_json('error', 'Not authenticated', 401);
}

$user_id = (int)$_SESSION['nivas_userId'];

$ref = isset($_GET['ref']) ? trim($_GET['ref']) : '';
$action = isset($_GET['action']) ? strtolower($_GET['action']) : 'download';
$kind = isset($_GET['kind']) ? strtolower(trim($_GET['kind'])) : null; // 'manual' | 'event'
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
if ($ref === '') {
  if ($action === 'download') {
    http_response_code(400);
    echo 'Missing ref';
    exit;
  }
  respond_json('error', 'Missing ref', 400);
}

// Verify ownership: prefer transactions table
$safe_ref = mysqli_real_escape_string($conn, $ref);
$owns = false;
$tx_chk = mysqli_query($conn, "SELECT 1 FROM transactions WHERE ref_id = '$safe_ref' AND user_id = $user_id LIMIT 1");
if ($tx_chk && mysqli_num_rows($tx_chk) > 0) { $owns = true; }
if (!$owns) {
  $mb_chk = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$safe_ref' AND buyer = $user_id LIMIT 1");
  if ($mb_chk && mysqli_num_rows($mb_chk) > 0) { $owns = true; }
}
if (!$owns) {
  $et_chk = mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$safe_ref' AND buyer = $user_id LIMIT 1");
  if ($et_chk && mysqli_num_rows($et_chk) > 0) { $owns = true; }
}
if (!$owns) {
  if ($action === 'download') {
    http_response_code(403);
    echo 'Forbidden';
    exit;
  }
  respond_json('error', 'Forbidden', 403);
}

// If single item requested, verify that the specific item belongs to the user under this ref
if ($kind && $itemId) {
  if ($kind === 'manual') {
    $chk = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$safe_ref' AND buyer = $user_id AND manual_id = $itemId LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) < 1) {
      if ($action === 'download') { http_response_code(403); echo 'Forbidden'; exit; }
      respond_json('error', 'Forbidden', 403);
    }
  } elseif ($kind === 'event') {
    $chk = mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$safe_ref' AND buyer = $user_id AND event_id = $itemId LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) < 1) {
      if ($action === 'download') { http_response_code(403); echo 'Forbidden'; exit; }
      respond_json('error', 'Forbidden', 403);
    }
  }
}

// Build receipt body
$body = buildReceiptHtmlFromRef($conn, $user_id, $ref, $kind, $itemId);

if ($action === 'email') {
  // Resolve recipient email
  $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT email FROM users WHERE id = $user_id"));
  $to = $u && isset($u['email']) ? $u['email'] : '';
  if (!$to) {
    respond_json('error', 'Email not found for user', 400);
  }
  $subject = 'Payment Receipt - Thank You for Your Purchase';
  $res = sendMail($subject, $body, $to);
  if ($res === 'success') {
    respond_json('success', 'Receipt has been sent to your email');
  }
  respond_json('error', 'Failed to send email', 500);
}

// Default: download as an HTML file using the email template styling
$wrapped = buildEmailTemplate($body);
$filename = 'receipt-' . preg_replace('/[^A-Za-z0-9_\-]/', '', $ref) . '.html';
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $wrapped;
exit;
