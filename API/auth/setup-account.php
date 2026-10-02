<?php
// API: Finish account setup from the emailed verification link (setup.html?verify=CODE).
// Same rules as the website's setup.html (model/verify.php + model/user.php "setup"), but tied
// to the emailed code instead of a user_id sent by the browser.
//   GET  /auth/setup-account.php?verify=CODE
//        -> { status: "pending", first_name, school_id, departments: [{id,name}] }
//           or { status: "verified" } when the account is already set up
//   POST /auth/setup-account.php { verify, dept, adm_year, matric_no }
//        -> marks the account verified and signs the student in (same payload as login)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/login_payload.php';
require_once __DIR__ . '/../../model/mail.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$input = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: $_POST) : $_GET;
$code = trim((string) ($input['verify'] ?? ''));
if ($code === '') {
    sendApiError('This setup link is incomplete. Sign in to get a new one.', 400);
}

$stmt = mysqli_prepare($conn, "SELECT u.* FROM verification_code vc JOIN users u ON u.id = vc.user_id WHERE vc.code = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $code);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user) {
    sendApiError('This setup link is invalid or has already been used. Sign in to get a new one.', 404);
}
if ($user['status'] !== 'unverified') {
    sendApiSuccess('Your account is already set up. Sign in to continue.', ['status' => 'verified']);
}

$schoolId = (int) $user['school'];

if ($method === 'GET') {
    $departments = [];
    $rs = mysqli_query($conn, "SELECT id, name FROM depts WHERE status = 'active' AND school_id = $schoolId ORDER BY name");
    while ($rs && ($row = mysqli_fetch_assoc($rs))) {
        $departments[] = ['id' => (int) $row['id'], 'name' => $row['name']];
    }
    sendApiSuccess('Finish setting up your account', [
        'status' => 'pending',
        'first_name' => $user['first_name'],
        'school_id' => $schoolId,
        'departments' => $departments,
    ]);
}

// POST: complete setup
$dept = (int) ($input['dept'] ?? 0);
$admYear = trim((string) ($input['adm_year'] ?? ''));
$matric = trim((string) ($input['matric_no'] ?? ''));
if ($dept <= 0 || $admYear === '' || $matric === '') {
    sendApiError('Choose your department and admission year, and enter your matric number.', 400);
}
if (!preg_match('/^\d{4}\/\d{4}$/', $admYear)) {
    sendApiError('Choose a valid admission year.', 400);
}

$deptCheck = mysqli_prepare($conn, "SELECT name FROM depts WHERE id = ? AND school_id = ? AND status = 'active' LIMIT 1");
mysqli_stmt_bind_param($deptCheck, 'ii', $dept, $schoolId);
mysqli_stmt_execute($deptCheck);
$deptRow = mysqli_fetch_assoc(mysqli_stmt_get_result($deptCheck));
mysqli_stmt_close($deptCheck);
if (!$deptRow) {
    sendApiError('Choose a department from the list.', 400);
}

$userId = (int) $user['id'];
$dup = mysqli_prepare($conn, "SELECT id FROM users WHERE matric_no = ? AND school = ? AND id <> ? LIMIT 1");
mysqli_stmt_bind_param($dup, 'sii', $matric, $schoolId, $userId);
mysqli_stmt_execute($dup);
$taken = mysqli_fetch_assoc(mysqli_stmt_get_result($dup));
mysqli_stmt_close($dup);
if ($taken) {
    sendApiError('This matric number is already on another Nivasity account. If that account is yours, sign in to it instead. Otherwise contact support with your student ID card.', 409);
}

$upd = mysqli_prepare($conn, "UPDATE users SET dept = ?, adm_year = ?, matric_no = ?, status = 'verified' WHERE id = ? AND status = 'unverified'");
mysqli_stmt_bind_param($upd, 'issi', $dept, $admYear, $matric, $userId);
mysqli_stmt_execute($upd);
$updated = mysqli_stmt_affected_rows($upd);
mysqli_stmt_close($upd);
if ($updated < 1) {
    sendApiError('Could not finish setting up your account. Please try again.', 500);
}

// The link is used up
$del = mysqli_prepare($conn, "DELETE FROM verification_code WHERE code = ?");
mysqli_stmt_bind_param($del, 's', $code);
mysqli_stmt_execute($del);
mysqli_stmt_close($del);

if ($user['role'] === 'hoc') {
    $body = "<b>New HOC Information</b><br>Name: {$user['first_name']} {$user['last_name']}<br>Department: {$deptRow['name']}"
        . "<br>Matric Number: " . htmlspecialchars($matric) . "<br>Phone number: {$user['phone']}<br>Email: {$user['email']}<br>Status: verified";
    @sendMail('New HOC profile completed', $body, 'support@nivasity.com');
}

$fresh = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $userId LIMIT 1"));
sendApiSuccess('Your account is ready!', nivasity_api_login_payload($conn, $fresh));
