<?php
// API: Get All Faculties
require_once __DIR__ . '/../config.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// No authentication required for reference data
// This is public information needed before registration/login

// Get query parameters
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
$offset = ($page - 1) * $limit;

// Validate required parameter
if (!$school_id) {
    sendApiError('school_id parameter is required', 400);
}

// Build query - only active faculties for the specified school
$where_conditions = ["status = 'active'", "school_id = $school_id"];
$where_clause = implode(' AND ', $where_conditions);

// Count total active faculties for the school
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM faculties WHERE $where_clause");
$total = mysqli_fetch_array($count_query)['total'];

// Fetch active faculties
$query = "SELECT id, name, school_id, created_at 
          FROM faculties 
          WHERE $where_clause 
          ORDER BY name ASC 
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);

if (!$result) {
    sendApiError('Database query failed', 500);
}

$faculties = [];

while ($row = mysqli_fetch_assoc($result)) {
    $faculties[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'school_id' => (int)$row['school_id'],
        'created_at' => $row['created_at']
    ];
}

sendApiSuccess('Faculties retrieved successfully', [
    'faculties' => $faculties,
    'pagination' => [
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]
]);
?>
