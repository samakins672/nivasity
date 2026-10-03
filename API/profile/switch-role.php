<?php
// API: Switch the signed-in user's academic role between Student and Class rep (HOC).
// Same self-service switch as the old website's Profile > "Academic Role" (model/user.php).
//   POST /profile/switch-role.php  { role: "student" | "hoc" }
// Returns the same user fields + fresh tokens as /auth/login.php.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../auth/login_payload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
$input = json_decode(file_get_contents('php://input'), true);
$target = strtolower(trim((string) ($input['role'] ?? '')));
$current = (string) ($user['role'] ?? '');

if (!in_array($current, ['student', 'hoc'], true)) {
    sendApiError('This account cannot change academic role here.', 403);
}
if (!in_array($target, ['student', 'hoc'], true)) {
    sendApiError('Please choose a valid academic role.', 400);
}
if ($target === $current) {
    sendApiError('This role is already active for your account.', 400);
}
if ($target === 'hoc' && (int) ($user['dept'] ?? 0) <= 0) {
    sendApiError('Add your department in Profile before switching to class rep.', 400);
}

$userId = (int) $user['id'];
$safe = mysqli_real_escape_string($conn, $target);
mysqli_query($conn, "UPDATE users SET role = '$safe' WHERE id = $userId LIMIT 1");
if (mysqli_errno($conn) !== 0) {
    sendApiError('Could not change your role. Please try again.', 500);
}

$fresh = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $userId LIMIT 1"));
$label = $target === 'hoc' ? 'class rep (HOC)' : 'student';
sendApiResponse('success', "You're now a $label.", nivasity_api_login_payload($conn, $fresh));
