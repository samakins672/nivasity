<?php
// API: Quick login with a one-time code created in cc_dashboard (Customer Management >
// Students > Quick Login). Same rules as the website's demo.php: the code must be active and
// unexpired, the account verified/active, and the code is used up on success.
// Returns the same payload as /auth/login.php (user fields + access/refresh tokens).
//   POST /auth/quick-login.php  { "code": "<64-char code>" }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/login_payload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$code = trim((string) ($input['code'] ?? ''));
if ($code === '' || !preg_match('/^[A-Za-z0-9]{16,128}$/', $code)) {
    sendApiError('This login link is invalid. Ask your administrator for a new one.', 400);
}

$now = date('Y-m-d H:i:s');
$stmt = mysqli_prepare(
    $conn,
    "SELECT qlc.id AS code_id, u.*
       FROM quick_login_codes qlc
       JOIN users u ON qlc.student_id = u.id
      WHERE qlc.code = ?
        AND qlc.status = 'active'
        AND qlc.expiry_datetime > ?
        AND u.status NOT IN ('unverified', 'denied', 'deactivated')
      LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'ss', $code, $now);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    sendApiError('This login link has expired or was already used. Ask your administrator for a new one.', 403);
}
if ($user['role'] !== 'student' && $user['role'] !== 'hoc') {
    sendApiError('Quick login is only for student accounts.', 403);
}

// Use the code up (only one request can win if the link is opened twice at once).
$codeId = (int) $user['code_id'];
mysqli_query($conn, "UPDATE quick_login_codes SET status = 'used' WHERE id = $codeId AND status = 'active'");
if (mysqli_affected_rows($conn) !== 1) {
    sendApiError('This login link was already used. Ask your administrator for a new one.', 403);
}

$user_id_int = (int) $user['id'];
mysqli_query($conn, "UPDATE users SET last_login = '$now' WHERE id = $user_id_int");

sendApiSuccess('Signed in', nivasity_api_login_payload($conn, $user));
