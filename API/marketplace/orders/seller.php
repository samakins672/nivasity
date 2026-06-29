<?php
// API: Marketplace Orders — seller's order list grouped by product_type (GET)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/marketplace_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

// Auto-complete stale delivered orders
marketplaceAutoCompleteDelivered($conn);

$result = mysqli_query($conn, "
    SELECT mo.*
    FROM marketplace_orders mo
    WHERE mo.seller_id = $user_id
    ORDER BY mo.created_at DESC
    LIMIT 200
");

$buyers     = [];
$clients    = [];
$collectors = [];

while ($row = mysqli_fetch_assoc($result)) {
    $order = marketplaceFormatOrder($conn, $row);
    switch ($row['product_type']) {
        case 'service': $clients[]    = $order; break;
        case 'free':    $collectors[] = $order; break;
        default:        $buyers[]     = $order; break;
    }
}

sendApiSuccess('Seller orders retrieved', [
    'buyers'     => $buyers,
    'clients'    => $clients,
    'collectors' => $collectors,
]);
?>
