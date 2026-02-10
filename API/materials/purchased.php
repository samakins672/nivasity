<?php
// API: List Purchased Materials
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

// Count total purchased
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM manuals_bought WHERE buyer = $user_id");
$total = mysqli_fetch_array($count_query)['total'];

// Fetch purchased materials
$query = "SELECT mb.*, m.title, m.course_code, m.dept, m.level, m.host_faculty, d.name as dept_name, hf.name as host_faculty_name, u.first_name, u.last_name
          FROM manuals_bought mb
          JOIN manuals m ON mb.manual_id = m.id
          LEFT JOIN depts d ON m.dept = d.id
          LEFT JOIN faculties hf ON m.host_faculty = hf.id
          LEFT JOIN users u ON m.user_id = u.id
          WHERE mb.buyer = $user_id
          ORDER BY mb.created_at DESC
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);
$purchased = [];

while ($row = mysqli_fetch_assoc($result)) {
    $purchased[] = [
        'id' => $row['manual_id'],
        'title' => $row['title'],
        'course_code' => $row['course_code'],
        'price' => (float)$row['price'],
        'dept' => $row['dept'],
        'dept_name' => ($row['dept'] == 0) ? 'All Departments' : $row['dept_name'],
        'host_faculty' => $row['host_faculty'],
        'host_faculty_name' => $row['host_faculty_name'],
        'level' => $row['level'] ? (string)$row['level'] : null,
        'seller_name' => $row['first_name'] . ' ' . $row['last_name'],
        'ref_id' => $row['ref_id'],
        'purchased_at' => $row['created_at']
    ];
}

sendApiSuccess('Purchased materials retrieved successfully', [
    'materials' => $purchased,
    'pagination' => [
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]
]);
?>
