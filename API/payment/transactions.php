<?php
// API: Get Transaction History
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

$user_id = $user['id'];

// Get query parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

// Count total transactions for materials only
// Only include transactions that have manual purchases
$count_query = mysqli_query($conn, "SELECT COUNT(DISTINCT t.id) as total 
    FROM transactions t 
    INNER JOIN manuals_bought mb ON t.ref_id = mb.ref_id 
    WHERE t.user_id = $user_id AND mb.buyer = $user_id");
$total = mysqli_fetch_array($count_query)['total'];

// Fetch transactions that have manual purchases only
$query = "SELECT DISTINCT t.* FROM transactions t 
    INNER JOIN manuals_bought mb ON t.ref_id = mb.ref_id 
    WHERE t.user_id = $user_id AND mb.buyer = $user_id 
    ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $query);

$transactions = [];

while ($row = mysqli_fetch_assoc($result)) {
    $tx_ref = $row['ref_id'];
    
    // Get items for this transaction (manuals only)
    $items = [];
    
    // Get manuals
    $manuals_query = mysqli_query($conn, "SELECT mb.*, m.title, m.course_code, m.dept, m.level, m.host_faculty, d.name as dept_name, hf.name as host_faculty_name FROM manuals_bought mb JOIN manuals m ON mb.manual_id = m.id LEFT JOIN depts d ON m.dept = d.id LEFT JOIN faculties hf ON m.host_faculty = hf.id WHERE mb.ref_id = '$tx_ref' AND mb.buyer = $user_id");
    while ($manual = mysqli_fetch_assoc($manuals_query)) {
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
            'level' => $manual['level'] ? (string)$manual['level'] : null
        ];
    }
    
    $transactions[] = [
        'id' => $row['id'],
        'ref_id' => $row['ref_id'],
        'amount' => (float)$row['amount'],
        'status' => $row['status'],
        'gateway_ref' => $row['gateway_ref'] ?? null,
        'items' => $items,
        'created_at' => $row['created_at']
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
