<?php
// API: Receipt PDF for one payment reference (same PDF and logo as the website's
// model/receipt.php download). Works for normal purchases and bulk payments.
//   GET /payment/receipt-pdf.php?ref=<ref_id>[&item_id=<manual id>]
// Returns application/pdf (attachment). Errors are JSON like other endpoints.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/functions.php';
require_once __DIR__ . '/../../model/receipt_pdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
$user_id = (int) $user['id'];

$ref = trim((string) ($_GET['ref'] ?? ''));
$itemId = isset($_GET['item_id']) && $_GET['item_id'] !== '' ? (int) $_GET['item_id'] : null;
if ($ref === '') {
    sendApiError('Missing ref', 400);
}
$safe_ref = mysqli_real_escape_string($conn, $ref);

// Ownership: the signed-in user paid this reference (same checks as the website).
$owns = false;
foreach ([
    "SELECT 1 FROM transactions WHERE ref_id = '$safe_ref' AND user_id = $user_id LIMIT 1",
    "SELECT 1 FROM manuals_bought WHERE ref_id = '$safe_ref' AND buyer = $user_id LIMIT 1",
    "SELECT 1 FROM event_tickets WHERE ref_id = '$safe_ref' AND buyer = $user_id LIMIT 1",
] as $sql) {
    $rs = mysqli_query($conn, $sql);
    if ($rs && mysqli_num_rows($rs) > 0) {
        $owns = true;
        break;
    }
}
if (!$owns) {
    sendApiError('Receipt not found on your account', 404);
}

$kind = null;
if ($itemId) {
    $chk = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$safe_ref' AND buyer = $user_id AND manual_id = $itemId LIMIT 1");
    if (!$chk || mysqli_num_rows($chk) < 1) {
        sendApiError('Receipt not found on your account', 404);
    }
    $kind = 'manual';
}

try {
    $receiptData = getReceiptDataFromRef($conn, $user_id, $ref, $kind, $itemId, ['create_bulk_export_audit' => true]);
    $pdf = receipt_pdf_render($receiptData, dirname(__DIR__, 2) . '/assets/images/nivasity-main.png');
} catch (Throwable $e) {
    error_log('[api receipt-pdf] ' . $e->getMessage());
    sendApiError('Unable to generate the receipt right now. Please try again.', 500);
}

$filename = 'nivasity-receipt-' . preg_replace('/[^A-Za-z0-9_\-]/', '', $ref) . ($itemId ? "-$itemId" : '') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Access-Control-Expose-Headers: Content-Disposition');
echo $pdf;
exit;
