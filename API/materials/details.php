<?php
// API: Get Material Details
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

// Get material ID or code
$manual_id = null;
$manual_code = null;

if (isset($_GET['id'])) {
    $manual_id = (int)$_GET['id'];
} elseif (isset($_GET['code'])) {
    $manual_code = sanitizeInput($conn, $_GET['code']);
} else {
    sendApiError('Material ID or code is required', 400);
}

$user_id = $user['id'];
$school_id = $user['school'];

// Build WHERE clause based on lookup parameter
if ($manual_id) {
    $where_condition = "m.id = $manual_id";
} else {
    $where_condition = "m.code = '$manual_code'";
}

// Fetch material details
$query = "SELECT m.*, u.first_name, u.last_name, u.phone, u.email, d.name as dept_name, f.name as faculty_name
          FROM manuals m
          LEFT JOIN users u ON m.user_id = u.id
          LEFT JOIN depts d ON m.dept = d.id
          LEFT JOIN faculties f ON m.faculty = f.id
          WHERE $where_condition AND m.school_id = $school_id
          LIMIT 1";

$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    sendApiError('Material not found', 404);
}

$material = mysqli_fetch_assoc($result);

// Use the actual material ID from the result for further queries
$material_id = $material['id'];

// Check if user already bought this material
$bought_query = mysqli_query($conn, "SELECT * FROM manuals_bought WHERE manual_id = $material_id AND buyer = $user_id LIMIT 1");
$is_purchased = mysqli_num_rows($bought_query) > 0;

$purchase_info = null;
if ($is_purchased) {
    $purchase = mysqli_fetch_assoc($bought_query);
    $purchase_info = [
        'purchased_at' => $purchase['created_at'],
        'ref_id' => $purchase['ref_id']
    ];
}

$materialData = [
    'id' => $material['id'],
    'code' => $material['code'],
    'title' => $material['title'],
    'course_code' => $material['course_code'],
    'price' => (float)$material['price'],
    'quantity' => (int)$material['quantity'],
    'due_date' => $material['due_date'],
    'status' => $material['status'],
    'dept' => $material['dept'],
    'dept_name' => $material['dept_name'],
    'faculty' => $material['faculty'],
    'faculty_name' => $material['faculty_name'],
    'seller' => [
        'id' => $material['user_id'],
        'name' => $material['first_name'] . ' ' . $material['last_name'],
        'phone' => $material['phone'],
        'email' => $material['email']
    ],
    'is_purchased' => $is_purchased,
    'purchase_info' => $purchase_info,
    'created_at' => $material['created_at']
];

sendApiSuccess('Material details retrieved successfully', $materialData);
?>
