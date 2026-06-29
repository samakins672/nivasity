<?php
// Buyer marks order as completed → releases escrow to seller
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

$q = mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id AND buyer_id = $user_id LIMIT 1");
if (!$q || mysqli_num_rows($q) === 0) sendApiError('Order not found', 404);

$order = mysqli_fetch_assoc($q);
if (!in_array($order['status'], ['pending','delivered'], true)) {
    sendApiError('This order cannot be completed in its current state', 400);
}

marketplaceReleaseEscrow($conn, $order_id);
marketplaceAddOrderTimeline($conn, $order_id, 'completed', 'Buyer confirmed receipt — funds released to seller');

mysqli_query($conn, "UPDATE marketplace_orders SET status = 'completed', completed_at = NOW() WHERE id = $order_id");

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id LIMIT 1"));
sendApiSuccess('Order completed successfully', marketplaceFormatOrder($conn, $row));
?>
