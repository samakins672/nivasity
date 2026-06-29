<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/marketplace_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
validateRequiredFields(['id'], $input);
$listing_id = (int)$input['id'];

$q = mysqli_query($conn, "SELECT * FROM marketplace_listings WHERE id = $listing_id AND seller_id = $user_id LIMIT 1");
if (!$q || mysqli_num_rows($q) === 0) sendApiError('Listing not found', 404);

$updates = [];

if (isset($input['title'])) {
    $updates[] = "title = '" . sanitizeInput($conn, $input['title']) . "'";
}
if (isset($input['description'])) {
    $updates[] = "description = '" . sanitizeInput($conn, $input['description']) . "'";
}
if (isset($input['price'])) {
    $price = max(0, (float)$input['price']);
    $updates[] = "price = $price";
}
if (isset($input['stock'])) {
    $updates[] = 'stock = ' . max(0, (int)$input['stock']);
}
if (isset($input['flash_until'])) {
    $fu = sanitizeInput($conn, $input['flash_until']);
    $updates[] = "flash_until = " . ($fu ? "'$fu'" : 'NULL');
}

if (empty($updates)) sendApiError('No fields to update', 400);

$set_clause = implode(', ', $updates);
mysqli_query($conn, "UPDATE marketplace_listings SET $set_clause WHERE id = $listing_id AND seller_id = $user_id");

$row_q = mysqli_query($conn, "SELECT * FROM marketplace_listings WHERE id = $listing_id LIMIT 1");
$row   = mysqli_fetch_assoc($row_q);

sendApiSuccess('Listing updated', marketplaceFormatListing($conn, $row));
?>
