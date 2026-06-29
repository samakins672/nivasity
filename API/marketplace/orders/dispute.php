<?php
// Buyer raises a dispute on an order
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/marketplace_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
validateRequiredFields(['order_id', 'reason'], $input);
$order_id = (int)$input['order_id'];
$reason   = sanitizeInput($conn, trim($input['reason']));

$q = mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id AND buyer_id = $user_id LIMIT 1");
if (!$q || mysqli_num_rows($q) === 0) sendApiError('Order not found', 404);

$order = mysqli_fetch_assoc($q);
if (!in_array($order['status'], ['pending','delivered'], true)) {
    sendApiError('Disputes can only be raised on active orders', 400);
}

marketplaceAddOrderTimeline($conn, $order_id, 'disputed', 'Dispute raised by buyer: ' . $reason);
mysqli_query($conn, "UPDATE marketplace_orders SET status = 'disputed' WHERE id = $order_id");

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id LIMIT 1"));
sendApiSuccess('Dispute raised — our team will review within 24h', marketplaceFormatOrder($conn, $row));
?>
