<?php
// API: Export the list of students in the HOC's department who paid for a material and are
// not yet marked as collected. Returns the same PDF as the old website (verification code,
// audit record pending grant in cc).
//   POST /hoc/export.php  { manual_id, rrr? }  -> application/pdf
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = hocRequireUser($conn);
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}
$manualId = (int) ($input['manual_id'] ?? 0);
if ($manualId <= 0) {
    sendApiError('Invalid material', 400);
}

// The website's exporter reads the signed-in HOC from the session and the form from $_POST.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['nivas_userId'] = (int) $user['id'];
$_SESSION['nivas_userRole'] = 'hoc';
$_SESSION['nivas_userSch'] = (int) $user['school'];
$_POST = [
    'manual_id' => $manualId,
    'rrr' => trim((string) ($input['rrr'] ?? '')),
    'output' => 'pdf',
];
// Verification links on the PDF point to the school's portal (/manual-export-verify).
$GLOBALS['manual_export_verify_base'] = hocVerifyBaseUrl($conn, (int) $user['school']);

chdir(__DIR__ . '/../../model');
require __DIR__ . '/../../model/export.php';
