<?php
// API: The HOC's exports that the command center has granted.
//   GET /hoc/granted-exports.php            -> list
//   GET /hoc/granted-exports.php?id=<id>    -> PDF of that export with the GRANTED details
require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../model/export_pdf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = hocRequireUser($conn);
$hocId = (int) $user['id'];
$statusCol = hocAuditStatusColumn($conn);
if ($statusCol === '') {
    sendApiResponse('success', 'Granted exports loaded', ['exports' => []]);
}

$col = fn(string $c, string $fallback) => hocColumnExists($conn, 'manual_export_audits', $c) ? "a.$c" : "$fallback AS $c";
$hasGrantedBy = hocColumnExists($conn, 'manual_export_audits', 'granted_by');
$select = "
    SELECT a.id, a.code, a.manual_id, a.students_count, a.total_amount, a.downloaded_at,
           {$col('granted_at', 'NULL')}, {$col('bought_ids_json', 'NULL')},
           {$col('from_bought_id', 'NULL')}, {$col('to_bought_id', 'NULL')},
           " . ($hasGrantedBy ? "TRIM(CONCAT(COALESCE(ad.first_name, ''), ' ', COALESCE(ad.last_name, '')))" : "''") . " AS granted_by_name,
           m.title AS manual_title, m.course_code,
           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS hoc_name, u.email AS hoc_email
    FROM manual_export_audits AS a
    JOIN manuals AS m ON m.id = a.manual_id
    JOIN users AS u ON u.id = a.hoc_user_id
    " . ($hasGrantedBy ? "LEFT JOIN admins AS ad ON ad.id = a.granted_by" : "") . "
    WHERE a.hoc_user_id = $hocId AND " . hocGrantedSql("a.`$statusCol`");

$fmt = fn($dt) => $dt ? date('j M Y, g:ia', strtotime($dt)) : '-';

if (isset($_GET['id'])) {
    $auditId = (int) $_GET['id'];
    $res = mysqli_query($conn, "$select AND a.id = $auditId LIMIT 1");
    $audit = $res ? mysqli_fetch_assoc($res) : null;
    if (!$audit) {
        sendApiError('Granted export not found', 404);
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
    $pdf = manual_export_pdf_render([
        'heading' => 'GRANTED: PAYMENTS FOR ' . strtoupper((string) $audit['course_code']) . ' MANUAL',
        'meta_lines' => [
            'Verification Code: ' . $audit['code'],
            'Total Students: ' . (int) $audit['students_count'] . '    Total Amount: NGN ' . number_format((float) $audit['total_amount'], 0),
            'Date Exported: ' . $fmt($audit['downloaded_at']) . '    Date Granted: ' . $fmt($audit['granted_at']),
            'Granted By: ' . ($audit['granted_by_name'] !== '' ? $audit['granted_by_name'] : '-'),
            'HOC: ' . $audit['hoc_name'] . ($audit['hoc_email'] ? ' (' . $audit['hoc_email'] . ')' : ''),
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
    $name = 'granted-export-' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $audit['course_code']) . '-' . $audit['code'] . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

$res = mysqli_query($conn, "$select ORDER BY a.granted_at DESC, a.id DESC LIMIT 200");
$exports = [];
while ($res && ($r = mysqli_fetch_assoc($res))) {
    $exports[] = [
        'id' => (int) $r['id'],
        'code' => $r['code'],
        'manual_title' => $r['manual_title'],
        'course_code' => $r['course_code'],
        'students_count' => (int) $r['students_count'],
        'total_amount' => (int) $r['total_amount'],
        'exported_at' => $r['downloaded_at'],
        'granted_at' => $r['granted_at'],
        'granted_by' => $r['granted_by_name'] !== '' ? $r['granted_by_name'] : null,
    ];
}
sendApiResponse('success', 'Granted exports loaded', ['exports' => $exports]);
