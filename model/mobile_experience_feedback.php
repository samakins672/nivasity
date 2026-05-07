<?php
session_start();
include('config.php');
require_once __DIR__ . '/mobile_experience_prompt.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    'status' => 'error',
    'message' => 'Method not allowed'
  ]);
  exit;
}

$user_id = isset($_SESSION['nivas_userId']) ? (int) $_SESSION['nivas_userId'] : 0;
if ($user_id <= 0) {
  http_response_code(401);
  echo json_encode([
    'status' => 'error',
    'message' => 'Not authenticated'
  ]);
  exit;
}

$device_choice = isset($_POST['device_choice']) ? strtolower(trim((string) $_POST['device_choice'])) : '';
$comfort_level = isset($_POST['comfort_level']) ? trim((string) $_POST['comfort_level']) : '';
$source_page = isset($_POST['source_page']) ? trim((string) $_POST['source_page']) : 'store';

$allowed_devices = ['android', 'iphone'];
$comfort_labels = [
  'love_it' => 'Love it 🔥',
  'its_cool' => "It's cool 🙂",
  'its_okay' => "It's okay 😐",
  'kinda_stressful' => 'Kinda stressful 😕',
  'not_good_experience' => 'Not a good experience 😣'
];

if (!in_array($device_choice, $allowed_devices, true)) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'message' => 'Invalid device choice'
  ]);
  exit;
}

if (!isset($comfort_labels[$comfort_level])) {
  http_response_code(400);
  echo json_encode([
    'status' => 'error',
    'message' => 'Invalid comfort level'
  ]);
  exit;
}

if ($source_page === '') {
  $source_page = 'store';
}
$source_page = substr($source_page, 0, 64);
$campaign_key = mobile_experience_prompt_get_current_campaign_key();
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
$comfort_label = $comfort_labels[$comfort_level];

mobile_experience_prompt_ensure_schema($conn);

if (mobile_experience_prompt_has_feedback($conn, $user_id, $campaign_key)) {
  echo json_encode([
    'status' => 'success',
    'message' => 'Feedback already captured'
  ]);
  exit;
}

$query = "INSERT INTO mobile_experience_feedback (user_id, device_choice, comfort_level, comfort_label, source_page, campaign_key, user_agent, created_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'Failed to prepare feedback insert'
  ]);
  exit;
}

mysqli_stmt_bind_param($stmt, 'issssss', $user_id, $device_choice, $comfort_level, $comfort_label, $source_page, $campaign_key, $user_agent);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'Failed to save feedback'
  ]);
  exit;
}

echo json_encode([
  'status' => 'success',
  'message' => 'Feedback saved'
]);
