<?php
// API: Pending bulk claims for the student — materials someone else paid for on their behalf,
// from HOC/student bulk payments or admin-uploaded external payments. Never semester-filtered.
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/bulk_material_payment_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$limit = isset($_GET['limit']) ? min(20, max(1, (int)$_GET['limit'])) : 5;

try {
    $claims = bulk_material_payment_get_pending_claims_for_user($conn, $user, $limit);
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}

sendApiSuccess('Pending claims retrieved successfully', [
    'claims' => $claims,
]);
