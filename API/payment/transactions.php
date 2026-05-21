<?php
// API: Get Transaction History
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

function transaction_history_is_external_ref($refId) {
    return stripos(trim((string)$refId), 'manual_ext_') === 0;
}

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

$user_id = $user['id'];
$first_name = isset($user['first_name']) ? trim((string)$user['first_name']) : '';
$last_name = isset($user['last_name']) ? trim((string)$user['last_name']) : '';
$payer_name = trim($first_name . ' ' . $last_name);
if ($payer_name === '') {
    $payer_name = 'Customer';
}
$matric_no = isset($user['matric_no']) ? trim((string)$user['matric_no']) : '';
if ($matric_no === '') {
    $matric_no = 'N/A';
}
$payer_name_with_matric = $payer_name . ' (Matric No.: ' . $matric_no . ')';

// Get query parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

// Count total material-purchase references, including externally recorded purchases.
$count_query = mysqli_query($conn, "SELECT COUNT(DISTINCT mb.ref_id) as total 
        FROM manuals_bought mb 
        WHERE mb.buyer = $user_id AND mb.status = 'successful'");
$total = mysqli_fetch_array($count_query)['total'];

// Fetch material purchase history grouped by reference, with transaction data when available.
$query = "SELECT
                COALESCE(MAX(t.id), 0) AS id,
                mb.ref_id,
                COALESCE(MAX(t.amount), SUM(mb.price)) AS amount,
                COALESCE(MAX(t.refund), 0) AS refund,
                COALESCE(NULLIF(MAX(t.status), ''), 'successful') AS status,
                COALESCE(
                    NULLIF(MAX(t.medium), ''),
                    CASE WHEN MAX(CASE WHEN mb.ref_id LIKE 'manual_ext_%' THEN 1 ELSE 0 END) = 1 THEN 'EXTERNAL' ELSE 'MANUAL' END
                ) AS medium,
                COALESCE(
                    NULLIF(MAX(t.payment_channel), ''),
                    CASE WHEN MAX(CASE WHEN mb.ref_id LIKE 'manual_ext_%' THEN 1 ELSE 0 END) = 1 THEN 'external_manual' ELSE 'manual_purchase' END
                ) AS payment_channel,
                COALESCE(NULLIF(MAX(t.transaction_context), ''), 'purchase') AS transaction_context,
                MAX(t.gateway_ref) AS gateway_ref,
                MAX(COALESCE(t.created_at, mb.created_at)) AS created_at,
                MAX(CASE WHEN mb.ref_id LIKE 'manual_ext_%' THEN 1 ELSE 0 END) AS is_external_payment
        FROM manuals_bought mb
        LEFT JOIN transactions t ON t.ref_id = mb.ref_id AND t.user_id = mb.buyer
        WHERE mb.buyer = $user_id AND mb.status = 'successful'
        GROUP BY mb.ref_id
        ORDER BY MAX(COALESCE(t.created_at, mb.created_at)) DESC
        LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);

$transactions = [];

while ($row = mysqli_fetch_assoc($result)) {
    $tx_ref = $row['ref_id'];
    $created_at = isset($row['created_at']) ? $row['created_at'] : '';
    $created_at_ts = strtotime((string)$created_at);
    $date_formatted = $created_at_ts ? date('jS F, Y', $created_at_ts) : date('jS F, Y');
    $is_external_payment = ((int)($row['is_external_payment'] ?? 0)) === 1 || transaction_history_is_external_ref($tx_ref);
    
    // Get items for this transaction (manuals only)
    $items = [];
    
    // Get manuals
    $manuals_query = mysqli_query($conn, "SELECT mb.*, m.title, m.course_code, m.dept, m.level, m.host_faculty, d.name as dept_name, hf.name as host_faculty_name FROM manuals_bought mb JOIN manuals m ON mb.manual_id = m.id LEFT JOIN depts d ON m.dept = d.id LEFT JOIN faculties hf ON m.host_faculty = hf.id WHERE mb.ref_id = '$tx_ref' AND mb.buyer = $user_id AND mb.status = 'successful'");
    while ($manual = mysqli_fetch_assoc($manuals_query)) {
        $item_is_external = transaction_history_is_external_ref($manual['ref_id'] ?? '');
        $items[] = [
            'type' => 'manual',
            'id' => $manual['manual_id'],
            'title' => $manual['title'],
            'course_code' => $manual['course_code'],
            'price' => (float)$manual['price'],
            'dept' => (int)$manual['dept'],
            'dept_name' => ((int)$manual['dept'] === 0) ? 'All Departments' : $manual['dept_name'],
            'host_faculty' => $manual['host_faculty'],
            'host_faculty_name' => $manual['host_faculty_name'],
            'level' => $manual['level'] ? (string)$manual['level'] : null,
            'is_external_payment' => $item_is_external,
            'payment_source' => $item_is_external ? 'external' : 'nivasity',
            'payment_source_label' => $item_is_external ? 'Paid outside Nivasity' : 'Paid on Nivasity',
            'payment_source_note' => $item_is_external ? 'This material was paid outside Nivasity.' : ''
        ];
    }
    
    $transactions[] = [
        'id' => $row['id'],
        'ref_id' => $row['ref_id'],
        'amount' => (float)$row['amount'],
        'refund' => isset($row['refund']) ? (float)$row['refund'] : 0,
        'status' => $row['status'],
        'medium' => $row['medium'] ?? null,
        'payment_channel' => $row['payment_channel'] ?? 'gateway',
        'transaction_context' => $row['transaction_context'] ?? 'purchase',
        'gateway_ref' => $row['gateway_ref'] ?? null,
        'items' => $items,
        'created_at' => $created_at,
        'date_formatted' => $date_formatted,
        'is_external_payment' => $is_external_payment,
        'payment_source' => $is_external_payment ? 'external' : 'nivasity',
        'payment_source_label' => $is_external_payment ? 'Paid outside Nivasity' : 'Paid on Nivasity',
        'payment_source_note' => $is_external_payment ? 'This material was paid outside Nivasity.' : '',
        'payer_name' => $payer_name,
        'matric_no' => $matric_no,
        'payer_name_with_matric' => $payer_name_with_matric
    ];
}

sendApiSuccess('Transactions retrieved successfully', [
    'transactions' => $transactions,
    'pagination' => [
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]
]);
?>
