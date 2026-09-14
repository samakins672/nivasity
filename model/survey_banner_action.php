<?php
session_start();
include('config.php');
require_once __DIR__ . '/survey_banner.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
  exit;
}

$user_id = isset($_SESSION['nivas_userId']) ? (int) $_SESSION['nivas_userId'] : 0;
if ($user_id <= 0) {
  http_response_code(401);
  echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
  exit;
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';
$survey_id = isset($_POST['survey_id']) ? (int) $_POST['survey_id'] : 0;

if ($survey_id <= 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid survey']);
  exit;
}

if ($action === 'dismiss') {
  $ok = surveyBannerDismiss($conn, $survey_id, $user_id);
  echo json_encode(['status' => $ok ? 'success' : 'error']);
  exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
