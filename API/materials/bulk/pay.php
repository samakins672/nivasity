<?php
// API: Pay for a bulk material batch from the wallet.
// JSON: { manual_id, rows: <payment_rows from preview>, wallet_pin }
// Rows are validated again server-side; any invalid row blocks the payment.
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/internal_wallet_service.php';
require_once __DIR__ . '/../../../model/bulk_material_payment_preview.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}
validateRequiredFields(['manual_id', 'wallet_pin'], $input);

if (!bulk_material_payment_ensure_schema($conn)) {
    sendApiError('Bulk payment is not available right now.', 503);
}

try {
    $manual = bulk_material_payment_load_payable_manual($conn, (int)$input['manual_id'], (int)$user['school']);
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}

$rawRows = $input['rows'] ?? [];
if (is_string($rawRows)) {
    $rawRows = json_decode($rawRows, true);
}
$rows = bulk_material_payment_decode_preview_rows(json_encode(is_array($rawRows) ? $rawRows : []));
if (empty($rows)) {
    sendApiError('Preview the student list again before paying from your wallet.', 422);
}

$preview = bulk_material_payment_preview_analyze_rows($conn, $rows, $manual, [
    'school' => (int)$user['school'],
    'dept' => (int)$user['dept'],
]);
if (trim((string)($preview['validation_warning'] ?? '')) !== '') {
    sendApiError((string)$preview['validation_warning'], 503);
}
if ((int)$preview['invalid_count'] > 0 || (int)$preview['valid_count'] < 1) {
    sendApiError('Fix the preview errors before paying from your wallet.', 422);
}

$wallet = bulk_material_payment_wallet_readiness($conn, $user);
if (!$wallet['ready']) {
    sendApiError($wallet['warnings'][0] ?? 'Complete the wallet prerequisites before paying for this batch.', 422);
}

try {
    $result = bulk_material_payment_process_wallet_batch(
        $conn,
        [
            'id' => (int)$user['id'],
            'school' => (int)$user['school'],
            'dept' => (int)$user['dept'],
        ],
        $manual,
        $rows,
        trim((string)$input['wallet_pin']),
        'api'
    );
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}

sendApiSuccess('Bulk payment completed successfully.', $result);
