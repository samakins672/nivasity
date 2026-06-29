<?php
// Buyer submits a review for a completed order
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
validateRequiredFields(['order_id', 'rating'], $input);

$order_id = (int)$input['order_id'];
$rating   = max(1, min(5, (int)$input['rating']));
$comment  = isset($input['comment']) ? sanitizeInput($conn, trim($input['comment'])) : '';

$q = mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id AND buyer_id = $user_id AND status = 'completed' LIMIT 1");
if (!$q || mysqli_num_rows($q) === 0) {
    sendApiError('Order not found or not yet completed', 404);
}
$order = mysqli_fetch_assoc($q);

// Prevent duplicate review
$dup_q = mysqli_query($conn, "SELECT id FROM marketplace_reviews WHERE order_id = $order_id LIMIT 1");
if ($dup_q && mysqli_num_rows($dup_q) > 0) {
    sendApiError('You have already reviewed this order', 400);
}

$listing_id = (int)$order['listing_id'];
$seller_id  = (int)$order['seller_id'];
$comment_safe = mysqli_real_escape_string($conn, $comment);

mysqli_query($conn, "
    INSERT INTO marketplace_reviews (order_id, listing_id, reviewer_id, seller_id, rating, comment)
    VALUES ($order_id, $listing_id, $user_id, $seller_id, $rating, '$comment_safe')
");

if (mysqli_affected_rows($conn) === 0) sendApiError('Failed to submit review', 500);

sendApiSuccess('Review submitted — thank you!');
?>
