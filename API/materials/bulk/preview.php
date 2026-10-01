<?php
// API: Preview a bulk material payment.
// Send multipart/form-data with manual_id + bulk_csv (CSV file), or JSON/form with manual_id + records
// (pasted text, one "first name, last name, matric no" per line).
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

if (!bulk_material_payment_ensure_schema($conn)) {
    sendApiError('Bulk payment is not available right now.', 503);
}

try {
    $manual = bulk_material_payment_load_payable_manual($conn, (int)($input['manual_id'] ?? 0), (int)$user['school']);
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}

$parsed = bulk_material_payment_preview_parse_request($_FILES['bulk_csv'] ?? [], (string)($input['records'] ?? ''));
if (!$parsed['ok']) {
    sendApiError((string)($parsed['message'] ?? 'Unable to read the student records.'), 422);
}

$rows = $parsed['rows'] ?? [];
$preview = bulk_material_payment_preview_analyze_rows($conn, $rows, $manual, [
    'school' => (int)$user['school'],
    'dept' => (int)$user['dept'],
]);
$wallet = bulk_material_payment_wallet_readiness($conn, $user);

$warnings = $wallet['warnings'];
$validationWarning = trim((string)($preview['validation_warning'] ?? ''));
if ($validationWarning !== '') {
    $warnings[] = $validationWarning;
}
$totalAmount = (int)($preview['breakdown']['total_amount'] ?? 0);

sendApiSuccess(
    $validationWarning !== ''
        ? 'Preview loaded, but student-record validation is temporarily unavailable.'
        : ((int)$preview['invalid_count'] > 0 ? 'Preview loaded. Fix the highlighted rows before payment.' : 'Preview loaded successfully.'),
    [
        'manual' => [
            'id' => (int)$manual['id'],
            'title' => (string)$manual['title'],
            'course_code' => (string)$manual['course_code'],
            'price' => (int)round((float)$manual['price']),
        ],
        'rows' => $preview['rows'],
        'errors' => $preview['errors'],
        'valid_count' => (int)$preview['valid_count'],
        'invalid_count' => (int)$preview['invalid_count'],
        'breakdown' => $preview['breakdown'],
        'wallet' => [
            'ready' => $wallet['ready'],
            'balance' => $wallet['balance'],
            'has_enough_balance' => $wallet['balance'] >= $totalAmount,
        ],
        'warnings' => array_values($warnings),
        'can_submit_payment' => (int)$preview['invalid_count'] === 0 && (int)$preview['valid_count'] > 0
            && $validationWarning === '' && $wallet['ready'],
        // Send these back unchanged to /materials/bulk/pay.php
        'payment_rows' => array_values($rows),
    ]
);
