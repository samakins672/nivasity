<?php
// API: Accept or reject a pending bulk claim.
// JSON: { student_row_id, action: "confirm" | "reject", source: <claim source from pending.php> }
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
validateRequiredFields(['student_row_id', 'action'], $input);

try {
    $result = bulk_material_payment_resolve_claim_for_user(
        $conn,
        (int)$input['student_row_id'],
        $user,
        trim((string)$input['action']),
        trim((string)($input['source'] ?? ''))
    );
    $remaining = bulk_material_payment_get_pending_claims_for_user($conn, $user, 5);
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}

sendApiSuccess((string)($result['message'] ?? 'Bulk claim updated successfully.'), [
    'remaining_claims' => $remaining,
    'manuals_bought_id' => (int)($result['manuals_bought_id'] ?? 0),
]);
