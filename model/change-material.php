<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/page_config.php';
require_once __DIR__ . '/material_change_service.php';

header('Content-Type: application/json; charset=utf-8');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($method, ['GET', 'POST'], true)) {
  http_response_code(405);
  echo json_encode([
    'status' => 'error',
    'message' => 'Method not allowed.',
  ]);
  exit;
}

if (!isset($user_id, $school_id)) {
  http_response_code(401);
  echo json_encode([
    'status' => 'error',
    'message' => 'You must be signed in to change a material.',
  ]);
  exit;
}

if (!isset($_SESSION['nivas_userRole']) || !in_array($_SESSION['nivas_userRole'], ['student', 'hoc'], true)) {
  http_response_code(403);
  echo json_encode([
    'status' => 'error',
    'message' => 'Only student accounts can change purchased materials.',
  ]);
  exit;
}

if ($method === 'GET') {
  $oldManualId = isset($_GET['old_manual_id']) ? (int) $_GET['old_manual_id'] : 0;
  $refId = isset($_GET['ref_id']) ? trim((string) $_GET['ref_id']) : '';

  if ($oldManualId <= 0 || $refId === '') {
    http_response_code(400);
    echo json_encode([
      'status' => 'error',
      'message' => 'old_manual_id and ref_id are required.',
    ]);
    exit;
  }

  $result = material_change_get_candidate_materials($conn, (int) $user_id, (int) $school_id, (int) $user_dept, $oldManualId, $refId);
  http_response_code((int) ($result['status_code'] ?? 200));
  echo json_encode([
    'status' => $result['ok'] ? 'success' : 'error',
    'message' => $result['message'],
    'data' => $result['ok'] ? [
      'order' => $result['order'],
      'candidates' => $result['candidates'],
    ] : null,
  ]);
  exit;
}

$oldManualId = isset($_POST['old_manual_id']) ? (int) $_POST['old_manual_id'] : 0;
$newManualId = isset($_POST['new_manual_id']) ? (int) $_POST['new_manual_id'] : 0;
$refId = isset($_POST['ref_id']) ? trim((string) $_POST['ref_id']) : '';

if ($oldManualId <= 0 || $newManualId <= 0 || $refId === '') {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'message' => 'old_manual_id, new_manual_id and ref_id are required.',
  ]);
  exit;
}

$result = material_change_execute($conn, (int) $user_id, (int) $school_id, (int) $user_dept, $oldManualId, $newManualId, $refId, 'web');
http_response_code((int) ($result['status_code'] ?? 200));
echo json_encode([
  'status' => $result['ok'] ? 'success' : 'error',
  'message' => $result['message'],
  'data' => $result['ok'] ? $result['data'] : null,
]);
exit;
?>