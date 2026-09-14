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

if ($action === 'submit') {
  $first_name = trim((string) ($_POST['first_name'] ?? ''));
  $last_name = trim((string) ($_POST['last_name'] ?? ''));
  $email = trim((string) ($_POST['email'] ?? ''));
  $phone = trim((string) ($_POST['phone'] ?? ''));
  $responses = isset($_POST['responses']) ? (string) $_POST['responses'] : '{}';

  $decodedResponses = json_decode($responses, true);
  if (!is_array($decodedResponses)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid responses payload']);
    exit;
  }
  $responsesJson = json_encode($decodedResponses, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

  $ok = surveyBannerSubmitResponse($conn, $survey_id, $user_id, $first_name, $last_name, $email, $phone, $responsesJson);

  if (!$ok) {
    $dbError = mysqli_error($conn);
    if (strpos($dbError, 'Duplicate entry') !== false) {
      // Already answered from elsewhere; treat as success so the widget closes.
      surveyBannerDismiss($conn, $survey_id, $user_id);
      echo json_encode(['status' => 'success', 'message' => 'Already recorded']);
      exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please fill in the required fields with a valid email.']);
    exit;
  }

  echo json_encode(['status' => 'success']);
  exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
