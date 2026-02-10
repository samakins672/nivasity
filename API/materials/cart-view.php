<?php
// API: View Cart
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/functions.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

$user_id = $user['id'];
$school_id = $user['school'];

// Get cart from session
session_start();
$cart_key = "nivas_cart$user_id";

if (!isset($_SESSION[$cart_key]) || empty($_SESSION[$cart_key])) {
    sendApiSuccess('Cart retrieved successfully', [
        'items' => [],
        'subtotal' => 0,
        'charge' => 0,
        'total_amount' => 0,
        'total_items' => 0
    ]);
}

$cart_ids = array_map('intval', $_SESSION[$cart_key]);
$ids_string = implode(',', $cart_ids);

// Fetch cart items
$query = "SELECT m.*, u.first_name, u.last_name, d.name as dept_name, hf.name as host_faculty_name
          FROM manuals m
          LEFT JOIN users u ON m.user_id = u.id
          LEFT JOIN depts d ON m.dept = d.id
          LEFT JOIN faculties hf ON m.host_faculty = hf.id
          WHERE m.id IN ($ids_string) AND m.school_id = $school_id";

$result = mysqli_query($conn, $query);
$cart_items = [];
$subtotal = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $item = [
        'id' => $row['id'],
        'title' => $row['title'],
        'course_code' => $row['course_code'],
        'price' => (float)$row['price'],
        'status' => $row['status'],
        'dept_name' => $row['dept_name'],
        'host_faculty' => $row['host_faculty'],
        'host_faculty_name' => $row['host_faculty_name'],
        'level' => $row['level'] ? (int)$row['level'] : null,
        'seller_name' => $row['first_name'] . ' ' . $row['last_name']
    ];
    
    $cart_items[] = $item;
    
    // Only add to subtotal if status is 'open'
    if ($row['status'] === 'open') {
        $subtotal += (float)$row['price'];
    }
}

// Calculate charges using active gateway
$charges_result = calculateGatewayCharges($subtotal);
$charge = $charges_result['charge'] ?? 0;
$total_amount = $charges_result['total_amount'] ?? ($subtotal + $charge);

sendApiSuccess('Cart retrieved successfully', [
    'items' => $cart_items,
    'subtotal' => $subtotal,
    'charge' => $charge,
    'total_amount' => $total_amount,
    'total_items' => count($cart_items)
]);
?>
