<?php
// API: Materials a class rep (HOC) can export, with sales in their department.
//   GET /hoc/materials.php?search=
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

$sql = "
    SELECT m.id, m.title, m.course_code, m.code, m.price, m.quantity, m.due_date, m.status, m.user_id,
           COUNT(mb.id) AS sold, COALESCE(SUM(mb.price), 0) AS sold_amount, COALESCE($pendingExpr, 0) AS pending
    FROM manuals AS m
    LEFT JOIN manuals_bought AS mb ON mb.manual_id = m.id AND mb.status = 'successful'
        AND mb.buyer IN (SELECT id FROM users WHERE dept = $deptId)
    WHERE $where
    GROUP BY m.id
    ORDER BY (COALESCE($pendingExpr, 0) > 0) DESC, COUNT(mb.id) DESC, (m.due_date >= CURDATE()) DESC, m.due_date DESC, m.id DESC
    LIMIT 300
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
    ];
}

sendApiResponse('success', 'Materials loaded', ['materials' => $materials]);
