<?php
// API: Materials the student/HOC can bulk-pay for (open, current semester, visible to their department)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/internal_wallet_service.php';
require_once __DIR__ . '/../../../model/bulk_material_payment_preview.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

if (!bulk_material_payment_ensure_schema($conn)) {
    sendApiError('Bulk payment is not available right now.', 503);
}

$manuals = bulk_material_payment_list_payable_manuals($conn, (int)$user['school'], (int)$user['dept']);

sendApiSuccess('Bulk payment materials retrieved successfully', [
    'materials' => $manuals,
    'fee_percent' => 5,
    'csv_headers' => bulk_material_payment_format_csv_header(),
    'wallet' => bulk_material_payment_wallet_readiness($conn, $user),
]);
