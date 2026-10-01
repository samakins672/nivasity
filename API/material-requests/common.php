<?php
// Shared setup for /material-requests/* : same rules as the web handler (model/material_requests.php).
if (!defined('NIVASITY_MATERIAL_REQUESTS_API')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/material_request_service.php';

function materialRequestsApiUser($conn, bool $requireVerified) {
    $user = authenticateApiRequest($conn);
    requireStudentRole($user);

    if ($requireVerified && trim((string)($user['status'] ?? '')) !== 'verified') {
        sendApiError('Verify your account before creating or upvoting material requests.', 403);
    }
    if (!nivasityMaterialRequestsReady($conn)) {
        sendApiError('Material requests are not available right now.', 503);
    }

    return $user;
}

function materialRequestsApiInput() {
    $input = json_decode(file_get_contents('php://input'), true);
    return $input ?: $_POST;
}
