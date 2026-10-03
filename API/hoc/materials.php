<?php
// API: Materials for a class rep (HOC): ones with students in their department waiting for
// collection (can be exported now) and ones their department's class reps exported before.
// Paginated; tap a material in the app/portal to see its exports (granted-exports.php?manual_id=).
//   GET /hoc/materials.php?search=&page=1&limit=20
// Same list as the old website's HOC dashboard (admin/index.php). HOCs no longer manage
// materials, so the only actions are Export list and Copy share link.
require_once __DIR__ . '/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = hocRequireUser($conn);
$deptId = (int) $user['dept'];
$where = hocMaterialsWhere($conn, $user);

$search = trim((string) ($_GET['search'] ?? ''));
if ($search !== '') {
    $s = mysqli_real_escape_string($conn, $search);
    $where .= " AND (m.title LIKE '%$s%' OR m.course_code LIKE '%$s%' OR m.code LIKE '%$s%')";
}

$hasGrant = hocColumnExists($conn, 'manuals_bought', 'grant_status');
$pendingExpr = $hasGrant
    ? "SUM(CASE WHEN mb.id IS NOT NULL AND (mb.grant_status IS NULL OR LOWER(TRIM(CAST(mb.grant_status AS CHAR))) IN ('', '0', 'pending', 'false')) THEN 1 ELSE 0 END)"
    : "COUNT(mb.id)";

$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$schoolId = (int) $user['school'];
$exportsExpr = "(SELECT COUNT(*) FROM manual_export_audits AS a JOIN users AS eu ON eu.id = a.hoc_user_id
    WHERE a.manual_id = m.id AND eu.dept = $deptId AND eu.school = $schoolId)";
$lastExportExpr = "(SELECT MAX(a.downloaded_at) FROM manual_export_audits AS a JOIN users AS eu ON eu.id = a.hoc_user_id
    WHERE a.manual_id = m.id AND eu.dept = $deptId AND eu.school = $schoolId)";

$base = "
    FROM manuals AS m
    LEFT JOIN manuals_bought AS mb ON mb.manual_id = m.id AND mb.status = 'successful'
        AND mb.buyer IN (SELECT id FROM users WHERE dept = $deptId)
    WHERE $where
    GROUP BY m.id
    HAVING COALESCE($pendingExpr, 0) > 0 OR $exportsExpr > 0
";
$countRes = mysqli_query($conn, "SELECT COUNT(*) AS total FROM (SELECT m.id $base) AS t");
$total = $countRes ? (int) (mysqli_fetch_assoc($countRes)['total'] ?? 0) : 0;

$sql = "
    SELECT m.id, m.title, m.course_code, m.code, m.price, m.quantity, m.due_date, m.status, m.user_id,
           COUNT(mb.id) AS sold, COALESCE(SUM(mb.price), 0) AS sold_amount, COALESCE($pendingExpr, 0) AS pending,
           $exportsExpr AS exports_count, $lastExportExpr AS last_exported_at
    $base
    ORDER BY (COALESCE($pendingExpr, 0) > 0) DESC, COALESCE($pendingExpr, 0) DESC, $lastExportExpr DESC, m.id DESC
    LIMIT $limit OFFSET $offset
";
$res = mysqli_query($conn, $sql);
if (!$res) {
    sendApiError('Could not load materials. Please try again.', 500);
}

$today = date('Y-m-d');
$materials = [];
while ($r = mysqli_fetch_assoc($res)) {
    $due = $r['due_date'] ? date('Y-m-d', strtotime($r['due_date'])) : null;
    $materials[] = [
        'id' => (int) $r['id'],
        'title' => $r['title'],
        'course_code' => $r['course_code'],
        'code' => $r['code'],
        'price' => (int) $r['price'],
        'quantity' => (int) $r['quantity'],
        'due_date' => $due,
        'active' => $r['status'] === 'open' && ($due === null || $due >= $today),
        'managed_by_admin' => (int) $r['user_id'] === 0,
        'sold' => (int) $r['sold'],
        'sold_amount' => (int) $r['sold_amount'],
        'pending_collection' => (int) $r['pending'],
        'exports_count' => (int) $r['exports_count'],
        'last_exported_at' => $r['last_exported_at'],
    ];
}

sendApiResponse('success', 'Materials loaded', [
    'materials' => $materials,
    'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'total_pages' => (int) ceil($total / $limit)],
]);
