<?php
// Seller marks order as delivered → starts 24h auto-complete countdown
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/marketplace_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
validateRequiredFields(['order_id'], $input);
$order_id = (int)$input['order_id'];

$q = mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id AND seller_id = $user_id LIMIT 1");
if (!$q || mysqli_num_rows($q) === 0) sendApiError('Order not found', 404);

$order = mysqli_fetch_assoc($q);
if ($order['status'] !== 'pending') {
    sendApiError('Only pending orders can be marked as delivered', 400);
}

marketplaceAddOrderTimeline($conn, $order_id, 'delivered', 'Seller marked as delivered — auto-completes in 24h if buyer does not respond');

mysqli_query($conn, "UPDATE marketplace_orders SET status = 'delivered', delivered_at = NOW() WHERE id = $order_id");

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id LIMIT 1"));
sendApiSuccess('Order marked as delivered', marketplaceFormatOrder($conn, $row));
?>
