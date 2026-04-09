<?php
session_start();
require_once 'config.php';
require_once 'payment_freeze.php';
require_once 'functions.php';
require_once 'refund_engine.php';

header('Content-Type: application/json');

// Check if payments are frozen
if (is_payment_frozen()) {
    $freeze_info = get_payment_freeze_info();
    echo json_encode([
        'success' => false, 
        'message' => $freeze_info ? $freeze_info['message'] : 'Payments are currently paused. Please try again later.',
        'payment_frozen' => true
    ]);
    exit;
}

// Read the JSON data from the request body
$data = json_decode(file_get_contents('php://input'), true);

// Check if data is valid
if ($data === null) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data received']);
    exit;
}

// Retrieve the data from the request
$ref_id = isset($data['ref_id']) ? mysqli_real_escape_string($conn, (string)$data['ref_id']) : '';
$user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
$gateway = isset($data['gateway']) ? mysqli_real_escape_string($conn, strtoupper((string)$data['gateway'])) : 'FLUTTERWAVE';
$payment_channel = isset($data['payment_channel']) ? strtolower(trim((string)$data['payment_channel'])) : 'gateway';
$payment_channel = $payment_channel === 'wallet' ? 'wallet' : 'gateway';
$gateway_slug = strtolower($gateway);

if ($ref_id === '' || $user_id <= 0 || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate if user_id exists in the users table
$query = "SELECT id, school FROM users WHERE id = $user_id LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Error: user_id does not exist in the users table.']);
    exit;
}
$user_row = mysqli_fetch_assoc($result);
$school_id = isset($user_row['school']) ? (int)$user_row['school'] : 0;

// Prepare the query to insert data into the cart table
$query = "INSERT INTO cart (ref_id, user_id, item_id, type, status, gateway, payment_channel) VALUES ";
$values = [];

foreach ($items as $item) {
    $item_id = isset($item['item_id']) ? (int)$item['item_id'] : 0;
    $type = isset($item['type']) ? mysqli_real_escape_string($conn, (string)$item['type']) : '';
    if ($item_id <= 0 || ($type !== 'manual' && $type !== 'event')) {
        continue;
    }
    $gateway_value = $gateway ? "'$gateway'" : "NULL";
    $values[] = "('$ref_id', $user_id, $item_id, '$type', 'pending', $gateway_value, '$payment_channel')";
}

if (empty($values)) {
    echo json_encode(['success' => false, 'message' => 'No valid cart items to save']);
    exit;
}

$query .= implode(", ", $values);

if (!mysqli_query($conn, $query)) {
    echo json_encode(['success' => false, 'message' => 'Internal Server Error while adding cart items. Please try again later!']);
    exit;
}

// Housekeeping of stale reservations.
releaseExpiredReservations($conn, 60);

// Build subtotal from server-trusted item records.
$cart_subtotal = 0;
foreach ($items as $item) {
    $item_id = isset($item['item_id']) ? (int)$item['item_id'] : 0;
    $type = isset($item['type']) ? (string)$item['type'] : '';
    if ($item_id <= 0) { continue; }

    if ($type === 'manual') {
        $manual_q = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $item_id AND school_id = $school_id LIMIT 1");
        if ($manual_q && mysqli_num_rows($manual_q) > 0) {
            $manual = mysqli_fetch_assoc($manual_q);
            $price = (int)round((float)$manual['price']);
            $cart_subtotal += $price;
        }
    } elseif ($type === 'event') {
        $event_q = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $item_id LIMIT 1");
        if ($event_q && mysqli_num_rows($event_q) > 0) {
            $event = mysqli_fetch_assoc($event_q);
            $price = (int)round((float)$event['price']);
            $cart_subtotal += $price;
        }
    }
}

$school_share_before = (int)$cart_subtotal;

$refund_reserved = 0;
if ($school_share_before > 0) {
    $refund_reserved = reserveRefundForSchoolShare(
        $conn,
        $ref_id,
        $school_id,
        $user_id,
        $gateway,
        $school_share_before,
        'web'
    );
}
$school_share_after = max(0, $school_share_before - $refund_reserved);

echo json_encode([
    'success' => true,
    'message' => 'Cart saved successfully',
    'gateway' => $gateway_slug,
    'internal_settlement_mode' => true,
    'refund_reserved' => (int)$refund_reserved,
    'school_share_before' => (int)$school_share_before,
    'school_share_after' => (int)$school_share_after
]);

mysqli_close($conn);
?>
