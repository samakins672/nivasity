<?php
// API: Payments made for this student (class rep / team) that they rejected with "Not mine" in the
// last 14 days, so they can be restored (Bella offers this in the chat).
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/bulk_material_payment_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

try {
    $claims = bulk_material_payment_get_recent_rejections_for_user($conn, $user, 10);
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}

sendApiSuccess('Recently rejected claims retrieved successfully', [
    'claims' => $claims,
    'restore_days' => bulk_material_payment_claim_restore_days(),
]);
