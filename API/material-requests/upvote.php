<?php
// API: Upvote a material request. JSON: { request_id }
define('NIVASITY_MATERIAL_REQUESTS_API', true);
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = materialRequestsApiUser($conn, true);
$input = materialRequestsApiInput();
validateRequiredFields(['request_id'], $input);

try {
    $result = nivasityMaterialRequestAddVote($conn, (int)$input['request_id'], $user);
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}

sendApiSuccess((string)($result['message'] ?? 'Upvote processed.'), [
    'status' => ($result['status'] ?? '') === 'success' ? 'upvoted' : 'already_upvoted',
    'progress' => $result['status_data'] ?? null,
]);
