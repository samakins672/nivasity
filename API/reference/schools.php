<?php
// API: Get All Schools
require_once __DIR__ . '/../config.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// No authentication required for reference data
// This is public information needed before registration/login

// Get query parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
$offset = ($page - 1) * $limit;

// Build query - only active schools
$where_conditions = ["status = 'active'"];
$where_clause = implode(' AND ', $where_conditions);
$has_domain_column = function_exists('nivasity_db_has_column') ? nivasity_db_has_column($conn, 'schools', 'domain') : false;

// Count total active schools
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM schools WHERE $where_clause");
$total = mysqli_fetch_array($count_query)['total'];

// Fetch active schools
$select_columns = $has_domain_column ? 'id, name, code, domain, created_at' : 'id, name, code, created_at';
$query = "SELECT $select_columns 
          FROM schools 
          WHERE $where_clause 
          ORDER BY name ASC 
          LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $query);

if (!$result) {
    sendApiError('Database query failed', 500);
}

$schools = [];

while ($row = mysqli_fetch_assoc($result)) {
    $school_data = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'code' => $row['code'],
        'created_at' => $row['created_at']
    ];

    if ($has_domain_column) {
        $school_data['domain'] = $row['domain'];
    }

    $schools[] = $school_data;
}

sendApiSuccess('Schools retrieved successfully', [
    'schools' => $schools,
    'pagination' => [
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ]
]);
?>
