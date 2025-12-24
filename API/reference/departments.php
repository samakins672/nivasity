<?php
// API: Get All Departments
require_once __DIR__ . '/../config.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// No authentication required for reference data
// This is public information needed before registration/login

// Get query parameters
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;
$faculty_id = isset($_GET['faculty_id']) ? (int)$_GET['faculty_id'] : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 100;
$offset = ($page - 1) * $limit;

// Validate required parameter
if (!$school_id) {
    sendApiError('school_id parameter is required', 400);
}

// Build query - only active departments for the specified school
$where_conditions = ["status = 'active'", "school_id = $school_id"];

// Optional faculty filter
if ($faculty_id) {
    $where_conditions[] = "faculty_id = $faculty_id";
}

$where_clause = implode(' AND ', $where_conditions);

// Count total active departments for the school (and optional faculty)
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM depts WHERE $where_clause");
$total = mysqli_fetch_array($count_query)['total'];

// Fetch active departments with faculty information
$query = "SELECT d.id, d.name, d.school_id, d.faculty_id, f.name as faculty_name, d.created_at 
          FROM depts d 
          LEFT JOIN faculties f ON d.faculty_id = f.id 
          WHERE $where_clause 
          ORDER BY d.name ASC 
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);

if (!$result) {
    sendApiError('Database query failed', 500);
}

$departments = [];

while ($row = mysqli_fetch_assoc($result)) {
    $departments[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'school_id' => (int)$row['school_id'],
        'faculty_id' => $row['faculty_id'] ? (int)$row['faculty_id'] : null,
        'faculty_name' => $row['faculty_name'],
        'created_at' => $row['created_at']
    ];
}

sendApiSuccess('Departments retrieved successfully', [
    'departments' => $departments,
    'pagination' => [
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]
]);
?>
