<?php
// API: Marketplace Orders — buyer's order list (GET)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/marketplace_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

// Auto-complete any orders that have been delivered for > 24 hours
marketplaceAutoCompleteDelivered($conn);

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$where = ["mo.buyer_id = $user_id"];

if (!empty($_GET['status'])) {
    $valid = ['pending','delivered','completed','cancelled','disputed'];
    $status = strtolower(sanitizeInput($conn, $_GET['status']));
    if (in_array($status, $valid, true)) {
        $where[] = "mo.status = '$status'";
    }
}

$where_clause = implode(' AND ', $where);

$count_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM marketplace_orders mo WHERE $where_clause");
$total   = (int)mysqli_fetch_assoc($count_q)['total'];

$result  = mysqli_query($conn, "SELECT mo.* FROM marketplace_orders mo WHERE $where_clause ORDER BY mo.created_at DESC LIMIT $limit OFFSET $offset");

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = marketplaceFormatOrder($conn, $row);
}

sendApiSuccess('Orders retrieved successfully', $orders);
?>
