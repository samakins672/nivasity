<?php
// API: List Materials/Manuals
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/material_copy_status.php';

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
$level = isset($_GET['level']) ? trim((string)$_GET['level']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 20;
$offset = ($page - 1) * $limit;

// Build query - filter by school and due date; visibility is added below
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

// Legacy visibility condition (used when m.depts is null)
$legacy_visibility_condition = "1 = 0";
if ($user_dept_safe && $user_faculty) {
    $legacy_visibility_condition = "(m.dept = $user_dept_safe OR (m.dept = 0 AND m.faculty = $user_faculty))";
} elseif ($user_dept_safe) {
    $legacy_visibility_condition = "m.dept = $user_dept_safe";
}

// New visibility condition:
// - if m.depts is set, user dept must be included in m.depts
// - if m.depts is null, fall back to legacy dept/faculty visibility
if ($user_dept_safe) {
    $normalized_depts_expr = "REPLACE(REPLACE(REPLACE(REPLACE(m.depts, '[', ''), ']', ''), '\"', ''), ' ', '')";
    $where_conditions[] = "((m.depts IS NOT NULL AND FIND_IN_SET($user_dept_safe, $normalized_depts_expr) > 0) OR (m.depts IS NULL AND ($legacy_visibility_condition)))";
} else {
    // Without a department, student cannot be matched to any department-scoped material.
    $where_conditions[] = "1 = 0";
}

if (!empty($search)) {
    $where_conditions[] = "(m.title LIKE '%$search%' OR m.course_code LIKE '%$search%')";
}
if ($level !== '' && strtolower($level) !== 'all') {
    if (!preg_match('/^[0-9A-Za-z _-]+$/', $level)) {
        sendApiError('Invalid level format', 400);
    }
    $level_safe = sanitizeInput($conn, $level);
    $where_conditions[] = "m.level = '$level_safe'";
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
$query = "SELECT m.*, u.first_name, u.last_name, d.name as dept_name, f.name as faculty_name, hf.name as host_faculty_name
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
$dept_name_cache = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Check if user already bought this material
    $non_lost_condition = material_copy_non_lost_condition($conn, 'mb');
    $bought_query = mysqli_query($conn, "SELECT 1 FROM manuals_bought AS mb WHERE mb.manual_id = {$row['id']} AND mb.buyer = $user_id AND {$non_lost_condition} LIMIT 1");
    $is_purchased = mysqli_num_rows($bought_query) > 0;
    
    // Check if due date has passed (within 24 hours)
    $due_date = strtotime($row['due_date']);
    $now = time();
    $is_overdue = ($now > $due_date);
    
    $coverage = strtolower((string)($row['coverage'] ?? ''));
    $dept_name = ((int)$row['dept'] === 0) ? 'All Departments' : $row['dept_name'];

    if ($coverage === 'school') {
        $dept_name = 'All Departments in School';
    } elseif ($coverage === 'faculty') {
        $dept_name = 'All Departments in Faculty';
    } elseif ($coverage === 'custom') {
        $depts_raw = (string)($row['depts'] ?? '');
        $normalized_depts = str_replace(['[', ']', '"', "'", ' '], '', $depts_raw);
        $depts_list = array_filter(explode(',', $normalized_depts), function ($dept_id) {
            return ctype_digit($dept_id) && (int)$dept_id > 0;
        });
        $unique_depts = array_values(array_unique($depts_list));
        $dept_count = count($unique_depts);

        if ($dept_count === 1) {
            $single_dept_id = (int)$unique_depts[0];

            if (!isset($dept_name_cache[$single_dept_id])) {
                $dept_name_query = mysqli_query($conn, "SELECT name FROM depts WHERE id = $single_dept_id LIMIT 1");
                if ($dept_name_query && mysqli_num_rows($dept_name_query) > 0) {
                    $dept_name_cache[$single_dept_id] = mysqli_fetch_assoc($dept_name_query)['name'];
                } else {
                    $dept_name_cache[$single_dept_id] = null;
                }
            }

            $dept_name = $dept_name_cache[$single_dept_id] ?: '1 Department';
        } else {
            $dept_name = $dept_count . ' Departments';
        }
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
        'dept_name' => $dept_name,
        'faculty' => $row['faculty'],
        'faculty_name' => $row['faculty_name'],
        'host_faculty' => $row['host_faculty'],
        'host_faculty_name' => $row['host_faculty_name'],
        'level' => $row['level'] ? (string)$row['level'] : null,
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
