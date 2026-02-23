<?php
session_start();
include('config.php');

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
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
$comfort_label = $comfort_labels[$comfort_level];

$create_table_sql = "CREATE TABLE IF NOT EXISTS `mobile_experience_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_choice` enum('android','iphone') NOT NULL,
  `comfort_level` enum('love_it','its_cool','its_okay','kinda_stressful','not_good_experience') NOT NULL,
  `comfort_label` varchar(64) NOT NULL,
  `source_page` varchar(64) NOT NULL DEFAULT 'store',
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mef_user_id` (`user_id`),
  KEY `idx_mef_device_choice` (`device_choice`),
  KEY `idx_mef_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if (!mysqli_query($conn, $create_table_sql)) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'Failed to prepare feedback table'
  ]);
  exit;
}

$query = "INSERT INTO mobile_experience_feedback (user_id, device_choice, comfort_level, comfort_label, source_page, user_agent, created_at)
          VALUES (?, ?, ?, ?, ?, ?, NOW())";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'Failed to prepare feedback insert'
  ]);
  exit;
}

mysqli_stmt_bind_param($stmt, 'isssss', $user_id, $device_choice, $comfort_level, $comfort_label, $source_page, $user_agent);
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
