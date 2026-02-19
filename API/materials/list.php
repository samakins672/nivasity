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

// Build query - filter by school AND (user's department OR faculty-level materials)
// Exclude materials with due date passed over 24 hours ago
$where_conditions = ["m.school_id = $school_id", "m.status = 'open'", "m.due_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"];

// Get user's faculty from their department and sanitize dept
$user_faculty = null;
$user_dept_safe = null;
if ($user_dept) {
    $user_dept_safe = (int)$user_dept; // Sanitize as integer
    $dept_query = mysqli_query($conn, "SELECT faculty_id FROM depts WHERE id = $user_dept_safe LIMIT 1");
    if ($dept_query && mysqli_num_rows($dept_query) > 0) {
        $dept_row = mysqli_fetch_assoc($dept_query);
        $user_faculty = (int)$dept_row['faculty_id']; // Sanitize as integer
    }
}

// Filter by: department-specific materials OR faculty-level materials (dept=0 with matching faculty)
if ($user_dept_safe && $user_faculty) {
    $where_conditions[] = "(m.dept = $user_dept_safe OR (m.dept = 0 AND m.faculty = $user_faculty))";
} elseif ($user_dept_safe) {
    // If no faculty found, just filter by department
    $where_conditions[] = "m.dept = $user_dept_safe";
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
$query = "SELECT m.*, u.first_name, u.last_name, u.role as poster_role, d.name as dept_name, f.name as faculty_name, hf.name as host_faculty_name
          FROM manuals m
          LEFT JOIN users u ON m.user_id = u.id
          LEFT JOIN depts d ON m.dept = d.id
          LEFT JOIN faculties f ON m.faculty = f.id
          LEFT JOIN faculties hf ON m.host_faculty = hf.id
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
    
    // Determine seller name: Show "Faculty" for admin-posted materials
    $seller_name = $row['first_name'] . ' ' . $row['last_name'];
    if ($row['poster_role'] === 'hoc') {
        $seller_name = 'Faculty';
    }
    
    $materials[] = [
        'id' => $row['id'],
        'code' => $row['code'],
        'title' => $row['title'],
        'course_code' => $row['course_code'],
        'price' => (float)$row['price'],
        'quantity' => (int)$row['quantity'],
        'due_date' => $row['due_date'],
        'is_overdue' => $is_overdue,
        'dept' => (int)$row['dept'],
        'dept_name' => ((int)$row['dept'] === 0) ? 'All Departments' : $row['dept_name'],
        'faculty' => $row['faculty'],
        'faculty_name' => $row['faculty_name'],
        'host_faculty' => $row['host_faculty'],
        'host_faculty_name' => $row['host_faculty_name'],
        'level' => $row['level'] ? (string)$row['level'] : null,
        'seller_name' => $seller_name,
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
