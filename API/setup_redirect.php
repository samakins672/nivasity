<?php
// API: Redirect setup and verification links from api.nivasity.com to the appropriate student school domain
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../model/tenant.php';

$verify = isset($_GET['verify']) ? trim((string)$_GET['verify']) : '';
$type = isset($_GET['type']) ? trim((string)$_GET['type']) : 'student';

$page = 'setup.html';
if ($type === 'org') {
    $page = 'setup_org.html';
} elseif ($type === 'visitor') {
    $page = 'verify.html';
}

if ($verify === '') {
    header('Location: ' . nivasity_default_domain());
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT user_id FROM verification_code WHERE code = ? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $verify);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        $userId = (int)$row['user_id'];
        mysqli_stmt_close($stmt);

        // Fetch user's school and role
        $userStmt = mysqli_prepare($conn, "SELECT role, school FROM users WHERE id = ? LIMIT 1");
        if ($userStmt) {
            mysqli_stmt_bind_param($userStmt, 'i', $userId);
            mysqli_stmt_execute($userStmt);
            $userResult = mysqli_stmt_get_result($userStmt);
            if ($userResult && mysqli_num_rows($userResult) === 1) {
                $user = mysqli_fetch_assoc($userResult);
                mysqli_stmt_close($userStmt);

                if ($user['role'] === 'org_admin') {
                    $page = 'setup_org.html';
                } elseif ($user['role'] === 'visitor') {
                    $page = 'verify.html';
                }

                $schoolId = (int)$user['school'];
                $targetUrl = nivasity_get_school_url($conn, $schoolId, $page . '?verify=' . urlencode($verify));
                header('Location: ' . $targetUrl, true, 302);
                exit();
            }
            mysqli_stmt_close($userStmt);
        }
    } else {
        mysqli_stmt_close($stmt);
    }
}

// Fallback if code not found in DB
header('Location: ' . nivasity_default_domain() . '/' . $page . '?verify=' . urlencode($verify), true, 302);
exit();
