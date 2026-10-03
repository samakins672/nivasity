<?php
// API (public, no login): verify a material export by the code printed on its PDF.
// Summary only, never student details (same as the old manual-export-verify.php).
//   GET /hoc/verify-export.php?code=A7K9Q2L8M3
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$code = strtoupper(trim((string) ($_GET['code'] ?? '')));
if ($code === '' || !preg_match('/^[A-Z0-9]{4,25}$/', $code)) {
    sendApiError('Enter the verification code printed on the export.', 400);
}

$statusCol = hocAuditStatusColumn($conn);
$hasGrantedBy = hocColumnExists($conn, 'manual_export_audits', 'granted_by');
$hasGrantedAt = hocColumnExists($conn, 'manual_export_audits', 'granted_at');
$safe = mysqli_real_escape_string($conn, $code);
$res = mysqli_query($conn, "
    SELECT a.code, a.students_count, a.total_amount, a.downloaded_at,
           " . ($statusCol !== '' ? "a.`$statusCol`" : "'pending'") . " AS export_status,
           " . ($hasGrantedAt ? 'a.granted_at' : 'NULL') . " AS granted_at,
           " . ($hasGrantedBy ? "TRIM(CONCAT(COALESCE(ad.first_name, ''), ' ', COALESCE(ad.last_name, '')))" : "''") . " AS granted_by_name,
           m.title AS manual_title, m.course_code,
           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS hoc_name,
           d.name AS dept_name, s.name AS school_name
    FROM manual_export_audits AS a
    JOIN manuals AS m ON m.id = a.manual_id
    JOIN users AS u ON u.id = a.hoc_user_id
    " . ($hasGrantedBy ? 'LEFT JOIN admins AS ad ON ad.id = a.granted_by' : '') . "
    LEFT JOIN depts AS d ON d.id = u.dept
    LEFT JOIN schools AS s ON s.id = u.school
    WHERE a.code = '$safe'
    LIMIT 1
");
$r = $res ? mysqli_fetch_assoc($res) : null;
if (!$r) {
    sendApiError('No export record found for this code.', 404);
}

$raw = strtolower(trim((string) $r['export_status']));
$granted = in_array($raw, ['granted', '1', 'true', 'yes'], true);
sendApiResponse('success', 'Export found', [
    'export' => [
        'code' => $r['code'],
        'status' => $granted ? 'granted' : 'pending',
        'manual_title' => $r['manual_title'],
        'course_code' => $r['course_code'],
        'hoc_name' => $r['hoc_name'],
        'department' => $r['dept_name'],
        'school' => $r['school_name'],
        'students_count' => (int) $r['students_count'],
        'total_amount' => (int) $r['total_amount'],
        'exported_at' => $r['downloaded_at'],
        'granted_at' => $granted ? $r['granted_at'] : null,
        'granted_by' => $granted && $r['granted_by_name'] !== '' ? $r['granted_by_name'] : null,
    ],
]);
