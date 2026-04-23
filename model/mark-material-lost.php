<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/page_config.php';
require_once __DIR__ . '/material_copy_status.php';

header('Content-Type: application/json; charset=utf-8');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'POST');
if ($method !== 'POST') {
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
    'message' => 'You must be signed in to manage purchased materials.',
  ]);
  exit;
}

if (!isset($_SESSION['nivas_userRole']) || !in_array($_SESSION['nivas_userRole'], ['student', 'hoc'], true)) {
  http_response_code(403);
  echo json_encode([
    'status' => 'error',
    'message' => 'Only student accounts can mark material copies as lost.',
  ]);
  exit;
}

$boughtId = isset($_POST['bought_id']) ? (int) $_POST['bought_id'] : 0;
if ($boughtId <= 0) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'message' => 'bought_id is required.',
  ]);
  exit;
}

$result = material_copy_mark_lost($conn, (int) $user_id, (int) $school_id, $boughtId);
http_response_code((int) ($result['status_code'] ?? 200));
echo json_encode([
  'status' => $result['ok'] ? 'success' : 'error',
  'message' => $result['message'],
  'data' => $result['data'] ?? null,
]);
exit;
?>