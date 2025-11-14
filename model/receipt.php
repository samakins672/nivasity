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
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : null; // 'pdf' optional
$kind = isset($_GET['kind']) ? strtolower(trim($_GET['kind'])) : null; // 'manual' | 'event'
$itemId = isset($_GET['item_id']) ? (int)$_GET['item_id'] : null;
// When generating client-side PDF, return inline HTML snippet instead of full email wrapper
$pdfInline = isset($_GET['pdf']) && (int)$_GET['pdf'] === 1;
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

// If client-side PDF requested, return bare receipt HTML for better rendering
if ($pdfInline) {
  header('Content-Type: text/html; charset=utf-8');
  // No attachment header to allow fetch to read content
  echo $body;
  exit;
}

// Server-side PDF generation (if Dompdf is available)
if ($action === 'download' && $format === 'pdf') {
  $autoloaders = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../vendor/dompdf/dompdf/autoload.inc.php',
    __DIR__ . '/../../vendor/autoload.php',
  ];
  foreach ($autoloaders as $path) {
    if (file_exists($path)) {
      require_once $path;
    }
  }
  if (class_exists('Dompdf\\Dompdf')) {
    $filenamePdf = 'receipt-' . preg_replace('/[^A-Za-z0-9_\-]/', '', $ref) . '.pdf';

    $logoUrl = 'https://funaab.nivasity.com/assets/images/nivasity-main.png';
    $html = '<!doctype html><html><head><meta charset="utf-8">'
          . '<style>'
          . '*{box-sizing:border-box;} body{font-family: DejaVu Sans, Arial, Helvetica, sans-serif; color:#333; background:#fff; margin:0;}'
          . '.pdf-container{width:178mm; margin:0 auto; padding:6mm;}'
          . '.header{display:flex; align-items:center; margin-bottom:8px;}'
          . '.header img{height:42px; display:block; max-width:100%;}'
          . 'table{width:100%; border-collapse: collapse; table-layout: fixed;}'
          . 'th,td{font-size:13px; word-wrap:break-word;}'
          . 'th:nth-child(3),td:nth-child(3){width:32mm; text-align:right; white-space:nowrap;}'
          . 'h2,h3{color:#7a3b73; margin:0 0 8px;}'
          . '</style></head><body>'
          . '<div class="pdf-container">'
          . '<div class="header"><img src="' . htmlspecialchars($logoUrl) . '" alt="Nivasity"></div>'
          . '<div class="content">' . $body . '</div>'
          . '</div>'
          . '</body></html>';

    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filenamePdf . '"');
    echo $dompdf->output();
    exit;
  }
}

// Default behavior: download styled HTML email
$wrapped = buildEmailTemplate($body);
$filename = 'receipt-' . preg_replace('/[^A-Za-z0-9_\-]/', '', $ref) . '.html';
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $wrapped;
exit;
