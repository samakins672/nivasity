<?php
// API: Mark Purchased Material Copy As Lost
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/material_copy_status.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

validateRequiredFields(['bought_id'], $input);

$result = material_copy_mark_lost($conn, (int)$user['id'], (int)$user['school'], (int)$input['bought_id']);

if (!$result['ok']) {
    sendApiError($result['message'], (int)($result['status_code'] ?? 400));
}

sendApiSuccess($result['message'], $result['data'] ?? null, (int)($result['status_code'] ?? 200));
?>