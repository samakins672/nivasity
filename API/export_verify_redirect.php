<?php
// API: Redirect manual-export-verify links from api.nivasity.com to the school's portal domain
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../model/tenant.php';

$rawCode = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$code = strtoupper($rawCode);
$schoolId = 0;

if ($code !== '') {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COALESCE(NULLIF(m.school_id, 0), u.school, 0) AS school_id
         FROM manual_export_audits AS a
         LEFT JOIN manuals AS m ON m.id = a.manual_id
         LEFT JOIN users AS u ON u.id = a.hoc_user_id
         WHERE a.code = ?
         LIMIT 1"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $code);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && ($row = mysqli_fetch_assoc($res))) {
            $schoolId = (int)($row['school_id'] ?? 0);
        }
        mysqli_stmt_close($stmt);
    }
}

if ($schoolId <= 0) {
    $schoolId = 1;
}

$path = 'manual-export-verify' . ($code !== '' ? '?code=' . urlencode($code) : '');
$targetUrl = nivasity_get_school_url($conn, $schoolId, $path);
header('Location: ' . $targetUrl, true, 302);
exit();

