<?php
// API: List Materials/Manuals
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
$school_id = $user['school'];
$user_dept = $user['dept'] ?? null;

// Get query parameters
$search = isset($_GET['search']) ? sanitizeInput($conn, $_GET['search']) : '';
$dept_filter = isset($_GET['dept']) ? (int)$_GET['dept'] : null;
$faculty_filter = isset($_GET['faculty']) ? (int)$_GET['faculty'] : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = ["m.school_id = $school_id", "m.status = 'open'"];

if (!empty($search)) {
    $where_conditions[] = "(m.title LIKE '%$search%' OR m.course_code LIKE '%$search%')";
}

if ($dept_filter) {
    $where_conditions[] = "m.dept = $dept_filter";
}

if ($faculty_filter) {
    $where_conditions[] = "m.faculty = $faculty_filter";
}

$where_clause = implode(' AND ', $where_conditions);

// Count total
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM manuals m WHERE $where_clause");
$total = mysqli_fetch_array($count_query)['total'];

// Fetch manuals
$query = "SELECT m.*, u.first_name, u.last_name, d.name as dept_name, f.name as faculty_name
          FROM manuals m
          LEFT JOIN users u ON m.user_id = u.id
          LEFT JOIN depts d ON m.dept = d.id
          LEFT JOIN faculties f ON m.faculty = f.id
          WHERE $where_clause
          ORDER BY m.created_at DESC
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);
$materials = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Check if user already bought this material
    $bought_query = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE manual_id = {$row['id']} AND buyer = $user_id LIMIT 1");
    $is_purchased = mysqli_num_rows($bought_query) > 0;
    
    $materials[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'course_code' => $row['course_code'],
        'price' => (float)$row['price'],
        'quantity' => (int)$row['quantity'],
        'due_date' => $row['due_date'],
        'dept' => $row['dept'],
        'dept_name' => $row['dept_name'],
        'faculty' => $row['faculty'],
        'faculty_name' => $row['faculty_name'],
        'seller_name' => $row['first_name'] . ' ' . $row['last_name'],
        'is_purchased' => $is_purchased,
        'created_at' => $row['created_at']
    ];
}

sendApiSuccess('Materials retrieved successfully', [
    'materials' => $materials,
    'pagination' => [
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]
]);
?>
