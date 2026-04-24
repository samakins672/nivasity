<?php
session_start();
include('config.php');
require_once __DIR__ . '/bulk_material_payment_service.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    'status' => 'error',
    'message' => 'Method not allowed.',
  ]);
  exit;
}

$userId = isset($_SESSION['nivas_userId']) ? (int) $_SESSION['nivas_userId'] : 0;
if ($userId <= 0) {
  http_response_code(401);
  echo json_encode([
    'status' => 'error',
    'message' => 'Your session has expired. Please sign in again.',
  ]);
  exit;
}

$userQuery = mysqli_query($conn, "SELECT * FROM users WHERE id = {$userId} LIMIT 1");
if (!$userQuery || mysqli_num_rows($userQuery) < 1) {
  http_response_code(401);
  echo json_encode([
    'status' => 'error',
    'message' => 'User account not found.',
  ]);
  exit;
}

$user = mysqli_fetch_assoc($userQuery) ?: [];
$role = (string) ($user['role'] ?? '');
if (!in_array($role, ['student', 'hoc'], true)) {
  http_response_code(403);
  echo json_encode([
    'status' => 'error',
    'message' => 'Only student-type accounts can review bulk claims.',
  ]);
  exit;
}

$studentRowId = isset($_POST['student_row_id']) ? (int) $_POST['student_row_id'] : 0;
$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

try {
  $result = bulk_material_payment_resolve_claim_for_user($conn, $studentRowId, $user, $action);
  $remainingClaims = bulk_material_payment_get_pending_claims_for_user($conn, $user, 5);
  echo json_encode([
    'status' => 'success',
    'message' => (string) ($result['message'] ?? 'Bulk claim updated successfully.'),
    'data' => [
      'remaining_claims' => $remainingClaims,
      'manuals_bought_id' => (int) ($result['manuals_bought_id'] ?? 0),
    ],
  ]);
} catch (Throwable $e) {
  http_response_code(422);
  echo json_encode([
    'status' => 'error',
    'message' => $e->getMessage(),
  ]);
}
