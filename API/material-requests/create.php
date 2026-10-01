<?php
// API: Request a missing course material.
// JSON: { material_code, material_title, scope, target_faculty_id?, target_faculty_ids?, target_dept_ids? }
// Result "status": created | duplicate (an existing request was found) | material_exists (already on sale).
define('NIVASITY_MATERIAL_REQUESTS_API', true);
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = materialRequestsApiUser($conn, true);
$input = materialRequestsApiInput();

try {
    $result = nivasityMaterialRequestCreate($conn, $user, [
        'material_code' => $input['material_code'] ?? '',
        'material_title' => $input['material_title'] ?? '',
        'scope' => $input['scope'] ?? '',
        'target_faculty_id' => $input['target_faculty_id'] ?? 0,
        'target_faculty_ids' => $input['target_faculty_ids'] ?? [],
        'target_dept_ids' => $input['target_dept_ids'] ?? [],
    ]);
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}

$status = (string)($result['status'] ?? '');
if ($status === 'created') {
    sendApiSuccess((string)$result['message'], [
        'status' => 'created',
        'request_id' => (int)($result['request_id'] ?? 0),
        'share_token' => (string)($result['share_token'] ?? ''),
    ], 201);
}
if ($status === 'duplicate') {
    sendApiSuccess((string)$result['message'], [
        'status' => 'duplicate',
        'request' => $result['request'] ?? null,
        'share_token' => (string)($result['request']['share_token'] ?? ''),
    ]);
}
if ($status === 'material_exists') {
    sendApiSuccess((string)$result['message'], [
        'status' => 'material_exists',
        'material' => $result['match'] ?? null,
    ]);
}

sendApiError('Unable to create the material request.', 422);
