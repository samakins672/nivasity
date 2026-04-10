<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/material_request_service.php';

header('Content-Type: application/json; charset=utf-8');

$userId = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Your session has expired. Please sign in again.',
    ]);
    exit;
}

$userRs = mysqli_query($conn, "SELECT id, school, dept, role, status FROM users WHERE id = $userId LIMIT 1");
$user = $userRs && mysqli_num_rows($userRs) > 0 ? mysqli_fetch_assoc($userRs) : null;
if (!$user) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'We could not load your account right now.',
    ]);
    exit;
}

$role = trim((string)($user['role'] ?? ''));
if (!in_array($role, ['student', 'hoc'], true)) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Only academic accounts can use material requests.',
    ]);
    exit;
}

if (trim((string)($user['status'] ?? '')) !== 'verified') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Verify your account before creating or upvoting material requests.',
    ]);
    exit;
}

$action = trim((string)($_POST['action'] ?? ''));

try {
    if ($action === 'create') {
        $result = nivasityMaterialRequestCreate($conn, $user, [
            'material_code' => $_POST['material_code'] ?? '',
            'material_title' => $_POST['material_title'] ?? '',
            'scope' => $_POST['scope'] ?? '',
            'target_faculty_id' => $_POST['target_faculty_id'] ?? 0,
            'target_faculty_ids' => $_POST['target_faculty_ids'] ?? [],
            'target_dept_ids' => $_POST['target_dept_ids'] ?? [],
        ]);

        if (($result['status'] ?? '') === 'created') {
            echo json_encode([
                'status' => 'success',
                'message' => $result['message'],
                'redirect' => 'material_requests.php?request=' . urlencode((string)$result['share_token']),
                'request_id' => (int)($result['request_id'] ?? 0),
            ]);
            exit;
        }

        if (($result['status'] ?? '') === 'duplicate') {
            echo json_encode([
                'status' => 'warning',
                'message' => $result['message'],
                'redirect' => 'material_requests.php?request=' . urlencode((string)($result['request']['share_token'] ?? '')),
            ]);
            exit;
        }

        if (($result['status'] ?? '') === 'material_exists') {
            echo json_encode([
                'status' => 'warning',
                'message' => $result['message'],
            ]);
            exit;
        }

        throw new Exception('Unable to create the material request.');
    }

    if ($action === 'upvote') {
        $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        $result = nivasityMaterialRequestAddVote($conn, $requestId, $user);
        echo json_encode([
            'status' => ($result['status'] ?? '') === 'success' ? 'success' : 'info',
            'message' => $result['message'] ?? 'Upvote processed.',
        ]);
        exit;
    }

    throw new Exception('Invalid request action supplied.');
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
    exit;
}
