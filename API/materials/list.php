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
$sort = isset($_GET['sort']) ? strtolower($_GET['sort']) : 'recommended';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

// Build query - filter by school AND user's department
// Exclude materials with due date passed over 24 hours ago
$where_conditions = ["m.school_id = $school_id", "m.status = 'open'", "m.due_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"];

// Filter by user's department
if ($user_dept) {
    $where_conditions[] = "m.dept = $user_dept";
}

if (!empty($search)) {
    $where_conditions[] = "(m.title LIKE '%$search%' OR m.course_code LIKE '%$search%')";
}

$where_clause = implode(' AND ', $where_conditions);

// Count total
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM manuals m WHERE $where_clause");
$total = mysqli_fetch_array($count_query)['total'];

// Determine sort order
$order_by = "m.due_date ASC"; // Default: latest due date (recommended)
switch ($sort) {
    case 'low-high':
        $order_by = "m.price ASC";
        break;
    case 'high-low':
        $order_by = "m.price DESC";
        break;
    case 'recommended':
    default:
        $order_by = "m.due_date ASC"; // Soonest due date first (most urgent)
        break;
}

// Fetch manuals
$query = "SELECT m.*, u.first_name, u.last_name, d.name as dept_name, f.name as faculty_name
          FROM manuals m
          LEFT JOIN users u ON m.user_id = u.id
          LEFT JOIN depts d ON m.dept = d.id
          LEFT JOIN faculties f ON m.faculty = f.id
          WHERE $where_clause
          ORDER BY $order_by
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);
$materials = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Check if user already bought this material
    $bought_query = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE manual_id = {$row['id']} AND buyer = $user_id LIMIT 1");
    $is_purchased = mysqli_num_rows($bought_query) > 0;
    
    // Check if due date has passed (within 24 hours)
    $due_date = strtotime($row['due_date']);
    $now = time();
    $is_overdue = ($now > $due_date);
    
    $materials[] = [
        'id' => $row['id'],
        'code' => $row['code'],
        'title' => $row['title'],
        'course_code' => $row['course_code'],
        'price' => (float)$row['price'],
        'quantity' => (int)$row['quantity'],
        'due_date' => $row['due_date'],
        'is_overdue' => $is_overdue,
        'dept' => $row['dept'],
        'dept_name' => $row['dept_name'],
        'faculty' => $row['faculty'],
        'faculty_name' => $row['faculty_name'],
        'level' => $row['level'] !== null ? (int)$row['level'] : null,
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
