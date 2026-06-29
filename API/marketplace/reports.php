<?php
// API: Submit a report (user | listing | order)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
validateRequiredFields(['type', 'target_id', 'reason'], $input);

$type      = strtolower(sanitizeInput($conn, $input['type']));
$target_id = (int)$input['target_id'];
$reason    = strtolower(sanitizeInput($conn, $input['reason']));
$details   = isset($input['details']) ? sanitizeInput($conn, trim($input['details'])) : '';

$valid_types   = ['user','listing','order'];
$valid_reasons = ['spam','fake','scam','inappropriate','harassment','other'];

if (!in_array($type, $valid_types, true))     sendApiError('Invalid report type', 400);
if (!in_array($reason, $valid_reasons, true)) sendApiError('Invalid reason', 400);
if ($target_id <= 0)                          sendApiError('Invalid target ID', 400);

// Prevent self-reporting
if ($type === 'user' && $target_id === $user_id) {
    sendApiError('You cannot report yourself', 400);
}

// Rate-limit: max 3 reports per user per day for the same target
$today_q = mysqli_query($conn, "
    SELECT COUNT(*) AS c FROM marketplace_reports
    WHERE reporter_id = $user_id
      AND type = '$type'
      AND target_id = $target_id
      AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
if ((int)mysqli_fetch_assoc($today_q)['c'] >= 3) {
    sendApiError('You have already submitted multiple reports for this item recently', 429);
}

$details_safe = mysqli_real_escape_string($conn, $details);
mysqli_query($conn, "
    INSERT INTO marketplace_reports (reporter_id, type, target_id, reason, details)
    VALUES ($user_id, '$type', $target_id, '$reason', '$details_safe')
");

if (mysqli_affected_rows($conn) === 0) sendApiError('Failed to submit report', 500);

sendApiSuccess('Report submitted — thank you for keeping the marketplace safe');
?>
