<?php
// API: Every export made by any HOC of the signed-in HOC's department (pending or granted),
// with who exported it. Class reps of a department share these lists.
//   GET /hoc/granted-exports.php?page=1&limit=20   -> list (newest first)
//   GET /hoc/granted-exports.php?id=<id>    -> PDF of that export (with grant details once granted)
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../model/export_pdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = hocRequireUser($conn);
$hocId = (int) $user['id'];
$deptId = (int) $user['dept'];
$schoolId = (int) $user['school'];
$statusCol = hocAuditStatusColumn($conn);
$statusSelect = $statusCol !== '' ? "a.`$statusCol`" : "'pending'";

$col = fn(string $c, string $fallback) => hocColumnExists($conn, 'manual_export_audits', $c) ? "a.$c" : "$fallback AS $c";
$hasGrantedBy = hocColumnExists($conn, 'manual_export_audits', 'granted_by');
$select = "
    SELECT a.id, a.code, a.manual_id, a.hoc_user_id, a.students_count, a.total_amount, a.downloaded_at,
           $statusSelect AS export_status,
           {$col('granted_at', 'NULL')}, {$col('bought_ids_json', 'NULL')},
           {$col('from_bought_id', 'NULL')}, {$col('to_bought_id', 'NULL')},
           " . ($hasGrantedBy ? "TRIM(CONCAT(COALESCE(ad.first_name, ''), ' ', COALESCE(ad.last_name, '')))" : "''") . " AS granted_by_name,
           m.title AS manual_title, m.course_code,
           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS hoc_name, u.email AS hoc_email
    FROM manual_export_audits AS a
    JOIN manuals AS m ON m.id = a.manual_id
    JOIN users AS u ON u.id = a.hoc_user_id
    " . ($hasGrantedBy ? "LEFT JOIN admins AS ad ON ad.id = a.granted_by" : "") . "
    WHERE u.dept = $deptId AND u.school = $schoolId";
$isGranted = fn($row) => in_array(strtolower(trim((string) $row['export_status'])), ['granted', '1', 'true', 'yes'], true);

$fmt = fn($dt) => $dt ? date('j M Y, g:ia', strtotime($dt)) : '-';

if (isset($_GET['id'])) {
    $auditId = (int) $_GET['id'];
    $res = mysqli_query($conn, "$select AND a.id = $auditId LIMIT 1");
    $audit = $res ? mysqli_fetch_assoc($res) : null;
    if (!$audit) {
        sendApiError('Export not found', 404);
    }
    $rows = hocGrantedExportRows($conn, $audit, (int) $user['dept']);
    $pdfRows = [];
    foreach ($rows as $i => $r) {
        $pdfRows[] = [
            'sn' => (string) ($i + 1),
            'name' => (string) $r['name'],
            'matric_no' => (string) $r['matric_no'],
            'adm_year' => (string) $r['adm_year'],
            'price' => number_format((float) $r['price'], 0),
        ];
    }
    $granted = $isGranted($audit);
    $meta = [
        'Verification Code: ' . $audit['code'] . '    Status: ' . ($granted ? 'GRANTED' : 'PENDING GRANT'),
        'Total Students: ' . (int) $audit['students_count'] . '    Total Amount: NGN ' . number_format((float) $audit['total_amount'], 0),
        'Date Exported: ' . $fmt($audit['downloaded_at']) . ($granted ? '    Date Granted: ' . $fmt($audit['granted_at']) : ''),
    ];
    if ($granted) {
        $meta[] = 'Granted By: ' . ($audit['granted_by_name'] !== '' ? $audit['granted_by_name'] : '-');
    }
    $pdf = manual_export_pdf_render([
        'heading' => ($granted ? 'GRANTED: ' : '') . 'PAYMENTS FOR ' . strtoupper((string) $audit['course_code']) . ' MANUAL',
        'meta_lines' => [
            ...$meta,
            'Exported by HOC: ' . $audit['hoc_name'] . ($audit['hoc_email'] ? ' (' . $audit['hoc_email'] . ')' : ''),
            'You can verify this export at ' . hocVerifyBaseUrl($conn, (int) $user['school']) . '/manual-export-verify?code=' . urlencode($audit['code']),
        ],
        'headers' => [
            ['key' => 'sn', 'label' => 'S/N', 'x' => 50, 'width' => 24],
            ['key' => 'name', 'label' => 'NAMES', 'x' => 80, 'width' => 205],
            ['key' => 'matric_no', 'label' => 'MATRIC NO', 'x' => 290, 'width' => 82],
            ['key' => 'adm_year', 'label' => 'ADMISSION YEAR', 'x' => 378, 'width' => 72],
            ['key' => 'price', 'label' => 'PRICE PAID', 'x' => 545, 'width' => 56, 'align' => 'right'],
        ],
        'rows' => $pdfRows,
    ], __DIR__ . '/../../assets/images/nivasity-main.png');
    $name = ($granted ? 'granted-export-' : 'export-') . preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $audit['course_code']) . '-' . $audit['code'] . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$countRes = mysqli_query($conn, "SELECT COUNT(*) AS total FROM manual_export_audits AS a JOIN users AS u ON u.id = a.hoc_user_id WHERE u.dept = $deptId AND u.school = $schoolId");
$total = $countRes ? (int) (mysqli_fetch_assoc($countRes)['total'] ?? 0) : 0;
$res = mysqli_query($conn, "$select ORDER BY a.downloaded_at DESC, a.id DESC LIMIT $limit OFFSET $offset");
$exports = [];
while ($res && ($r = mysqli_fetch_assoc($res))) {
    $exports[] = [
        'id' => (int) $r['id'],
        'code' => $r['code'],
        'manual_title' => $r['manual_title'],
        'course_code' => $r['course_code'],
        'students_count' => (int) $r['students_count'],
        'total_amount' => (int) $r['total_amount'],
        'status' => $isGranted($r) ? 'granted' : 'pending',
        'exported_at' => $r['downloaded_at'],
        'exported_by' => $r['hoc_name'],
        'is_mine' => (int) $r['hoc_user_id'] === $hocId,
        'granted_at' => $isGranted($r) ? $r['granted_at'] : null,
        'granted_by' => $isGranted($r) && $r['granted_by_name'] !== '' ? $r['granted_by_name'] : null,
    ];
}
sendApiResponse('success', 'Exports loaded', [
    'exports' => $exports,
    'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => (int) ceil($total / $limit)],
]);
