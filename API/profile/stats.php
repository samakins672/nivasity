<?php
// API: Get User Profile Statistics
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

// Get total materials bought (count distinct manual_id from manuals_bought)
$total_materials = 0;
$materials_query = mysqli_query($conn, "
    SELECT COUNT(DISTINCT manual_id) as total 
    FROM manuals_bought 
    WHERE buyer = $user_id AND status = 'successful'
");
if ($materials_query && mysqli_num_rows($materials_query) > 0) {
    $materials_data = mysqli_fetch_assoc($materials_query);
    $total_materials = (int)$materials_data['total'];
}

// Get total spent on materials (sum of prices excluding charges)
// Price in manuals_bought represents the item price without gateway charges
$total_spent = 0;
$spent_query = mysqli_query($conn, "
    SELECT SUM(price) as total 
    FROM manuals_bought 
    WHERE buyer = $user_id AND status = 'successful'
");
if ($spent_query && mysqli_num_rows($spent_query) > 0) {
    $spent_data = mysqli_fetch_assoc($spent_query);
    $total_spent = (float)($spent_data['total'] ?? 0);
}

// Get pending cart payments within 30 days (distinct ref_id from cart table)
$pending_orders = 0;
$pending_query = mysqli_query($conn, "
    SELECT COUNT(DISTINCT ref_id) as total 
    FROM cart 
    WHERE user_id = $user_id 
    AND status = 'pending' 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
if ($pending_query && mysqli_num_rows($pending_query) > 0) {
    $pending_data = mysqli_fetch_assoc($pending_query);
    $pending_orders = (int)$pending_data['total'];
}

// Prepare statistics data
$statsData = [
    'total_materials' => $total_materials,
    'total_spent' => $total_spent,
    'pending_orders' => $pending_orders
];

sendApiSuccess('Profile statistics retrieved successfully', $statsData);
?>
