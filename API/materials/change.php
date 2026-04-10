<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/material_change_service.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST'], true)) {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$userId = (int) ($user['id'] ?? 0);
$schoolId = (int) ($user['school'] ?? 0);
$userDept = (int) ($user['dept'] ?? 0);

if ($method === 'GET') {
    $oldManualId = isset($_GET['old_manual_id']) ? (int) $_GET['old_manual_id'] : 0;
    $refId = isset($_GET['ref_id']) ? trim((string) $_GET['ref_id']) : '';

    if ($oldManualId <= 0 || $refId === '') {
        sendApiError('old_manual_id and ref_id are required.', 400);
    }

    $result = material_change_get_candidate_materials($conn, $userId, $schoolId, $userDept, $oldManualId, $refId);
    if (!$result['ok']) {
        sendApiError($result['message'], (int) ($result['status_code'] ?? 400));
    }

    sendApiSuccess($result['message'], [
        'order' => $result['order'],
        'candidates' => $result['candidates'],
    ]);
}

$payload = $_POST;
$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== false && trim($rawBody) !== '') {
        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            sendApiError('Invalid JSON payload.', 400);
        }
        $payload = $decoded;
    }
}

$oldManualId = isset($payload['old_manual_id']) ? (int) $payload['old_manual_id'] : 0;
$newManualId = isset($payload['new_manual_id']) ? (int) $payload['new_manual_id'] : 0;
$refId = isset($payload['ref_id']) ? trim((string) $payload['ref_id']) : '';

if ($oldManualId <= 0 || $newManualId <= 0 || $refId === '') {
    sendApiError('old_manual_id, new_manual_id and ref_id are required.', 400);
}

$result = material_change_execute($conn, $userId, $schoolId, $userDept, $oldManualId, $newManualId, $refId, 'api');
if (!$result['ok']) {
    sendApiError($result['message'], (int) ($result['status_code'] ?? 400));
}

sendApiSuccess($result['message'], $result['data']);
?>