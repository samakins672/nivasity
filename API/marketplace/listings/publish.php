<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
validateRequiredFields(['id'], $input);
$listing_id = (int)$input['id'];

$q = mysqli_query($conn, "SELECT id, status FROM marketplace_listings WHERE id = $listing_id AND seller_id = $user_id LIMIT 1");
if (!$q || mysqli_num_rows($q) === 0) sendApiError('Listing not found', 404);

$listing = mysqli_fetch_assoc($q);
if ($listing['status'] === 'sold') sendApiError('Cannot publish a sold listing', 400);

mysqli_query($conn, "UPDATE marketplace_listings SET status = 'active' WHERE id = $listing_id AND seller_id = $user_id");
sendApiSuccess('Listing published');
?>
