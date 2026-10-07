<?php
// API: Undo "Not mine" on a payment made for this student, within 14 days.
// JSON: { student_row_id, source: "bulk" | "external_manual" }
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/bulk_material_payment_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}
validateRequiredFields(['student_row_id'], $input);

try {
    $result = bulk_material_payment_restore_rejected_claim_for_user(
        $conn,
        (int) $input['student_row_id'],
        $user,
        trim((string) ($input['source'] ?? 'bulk'))
    );
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}

sendApiSuccess((string) $result['message'], null);
