<?php
// API: View Cart
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/functions.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

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

$cart_ids = array_values(array_unique(array_map('intval', $_SESSION[$cart_key])));
$cart_ids = array_values(array_filter($cart_ids, function ($id) {
    return $id > 0;
}));

if (empty($cart_ids)) {
    $_SESSION[$cart_key] = [];
    sendApiSuccess('Cart retrieved successfully', [
        'items' => [],
        'subtotal' => 0,
        'charge' => 0,
        'total_amount' => 0,
        'total_items' => 0
    ]);
}

$ids_string = implode(',', $cart_ids);

// Auto-prune items already paid or already confirmed in DB cart.
$resolved_ids = [];

$purchased_query = mysqli_query($conn, "SELECT DISTINCT manual_id FROM manuals_bought WHERE buyer = $user_id AND manual_id IN ($ids_string)");
if ($purchased_query) {
    while ($purchased_row = mysqli_fetch_assoc($purchased_query)) {
        $resolved_ids[] = (int)$purchased_row['manual_id'];
    }
}

$confirmed_query = mysqli_query($conn, "SELECT DISTINCT item_id FROM cart WHERE user_id = $user_id AND type = 'manual' AND status = 'confirmed' AND item_id IN ($ids_string)");
if ($confirmed_query) {
    while ($confirmed_row = mysqli_fetch_assoc($confirmed_query)) {
        $resolved_ids[] = (int)$confirmed_row['item_id'];
    }
}

if (!empty($resolved_ids)) {
    $resolved_ids = array_values(array_unique(array_map('intval', $resolved_ids)));
    $cart_ids = array_values(array_diff($cart_ids, $resolved_ids));
    $_SESSION[$cart_key] = $cart_ids;

    if (empty($cart_ids)) {
        sendApiSuccess('Cart retrieved successfully', [
            'items' => [],
            'subtotal' => 0,
            'charge' => 0,
            'total_amount' => 0,
            'total_items' => 0
        ]);
    }

    $ids_string = implode(',', $cart_ids);
}

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
$found_ids = [];

while ($row = mysqli_fetch_assoc($result)) {
    $found_ids[] = (int)$row['id'];

    $item = [
        'id' => $row['id'],
        'title' => $row['title'],
        'course_code' => $row['course_code'],
        'price' => (float)$row['price'],
        'status' => $row['status'],
        'dept' => (int)$row['dept'],
        'dept_name' => ((int)$row['dept'] === 0) ? 'All Departments' : $row['dept_name'],
        'host_faculty' => $row['host_faculty'],
        'host_faculty_name' => $row['host_faculty_name'],
        'level' => $row['level'] ? (string)$row['level'] : null,
        'seller_name' => $row['first_name'] . ' ' . $row['last_name']
    ];
    
    $cart_items[] = $item;
    
    // Only add to subtotal if status is 'open'
    if ($row['status'] === 'open') {
        $subtotal += (float)$row['price'];
    }
}

// Remove stale IDs (deleted/invalid/wrong-school) from session cart.
if (!empty($cart_ids)) {
    $stale_ids = array_values(array_diff($cart_ids, $found_ids));
    if (!empty($stale_ids)) {
        $_SESSION[$cart_key] = array_values(array_diff($cart_ids, $stale_ids));
    }
}

// Calculate charges using active gateway
$charges_result = calculateGatewayCharges($subtotal);
$charge = $charges_result['charge'] ?? 0;
$total_amount = $charges_result['total_amount'] ?? ($subtotal + $charge);
$wallet = nivasityGetUserWallet($conn, (int)$user_id);
$wallet_balance = (int)($wallet['balance'] ?? 0);

sendApiSuccess('Cart retrieved successfully', [
    'items' => $cart_items,
    'subtotal' => $subtotal,
    'charge' => $charge,
    'total_amount' => $total_amount,
    'total_items' => count($cart_items),
    'wallet' => [
        'has_wallet' => $wallet !== null,
        'balance' => $wallet_balance,
        'wallet_total_amount' => $subtotal,
        'can_pay_with_wallet' => $wallet !== null && $wallet_balance >= (int)round((float)$subtotal),
    ]
]);
?>
