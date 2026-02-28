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
$query = "INSERT INTO cart (ref_id, user_id, item_id, type, status, gateway) VALUES ";
$values = [];

foreach ($items as $item) {
    $item_id = isset($item['item_id']) ? (int)$item['item_id'] : 0;
    $type = isset($item['type']) ? mysqli_real_escape_string($conn, (string)$item['type']) : '';
    if ($item_id <= 0 || ($type !== 'manual' && $type !== 'event')) {
        continue;
    }
    $gateway_value = $gateway ? "'$gateway'" : "NULL";
    $values[] = "('$ref_id', $user_id, $item_id, '$type', 'pending', $gateway_value)";
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
releaseExpiredReservations($conn, 30);

// Build seller totals from server-trusted item records.
$seller_totals = [];
foreach ($items as $item) {
    $item_id = isset($item['item_id']) ? (int)$item['item_id'] : 0;
    $type = isset($item['type']) ? (string)$item['type'] : '';
    if ($item_id <= 0) { continue; }

    if ($type === 'manual') {
        $manual_q = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $item_id AND school_id = $school_id LIMIT 1");
        if ($manual_q && mysqli_num_rows($manual_q) > 0) {
            $manual = mysqli_fetch_assoc($manual_q);
            $seller_id = (int)$manual['user_id'];
            $price = (int)round((float)$manual['price']);
            if (!isset($seller_totals[$seller_id])) {
                $seller_totals[$seller_id] = 0;
            }
            $seller_totals[$seller_id] += $price;
        }
    } elseif ($type === 'event') {
        $event_q = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $item_id LIMIT 1");
        if ($event_q && mysqli_num_rows($event_q) > 0) {
            $event = mysqli_fetch_assoc($event_q);
            $seller_id = (int)$event['user_id'];
            $price = (int)round((float)$event['price']);
            if (!isset($seller_totals[$seller_id])) {
                $seller_totals[$seller_id] = 0;
            }
            $seller_totals[$seller_id] += $price;
        }
    }
}

$subaccount_shares = [];
foreach ($seller_totals as $seller_id => $seller_total) {
    $seller_subaccount = getSettlementSubaccount($conn, $seller_id, $school_id, $gateway_slug);
    if (!empty($seller_subaccount)) {
        if (!isset($subaccount_shares[$seller_subaccount])) {
            $subaccount_shares[$seller_subaccount] = 0;
        }
        $subaccount_shares[$seller_subaccount] += (int)$seller_total;
    }
}

$school_subaccount_code = getSchoolSettlementSubaccount($conn, $school_id, $gateway_slug);
$school_share_before = 0;
if (!empty($school_subaccount_code) && isset($subaccount_shares[$school_subaccount_code])) {
    $school_share_before = (int)$subaccount_shares[$school_subaccount_code];
}

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

if (!empty($school_subaccount_code) && isset($subaccount_shares[$school_subaccount_code])) {
    $subaccount_shares[$school_subaccount_code] = $school_share_after;
    if ($subaccount_shares[$school_subaccount_code] <= 0) {
        unset($subaccount_shares[$school_subaccount_code]);
    }
}

$adjusted_subaccounts = [];
if ($gateway_slug === 'flutterwave') {
    foreach ($subaccount_shares as $sub_code => $share_naira) {
        $share_naira = (int)round((float)$share_naira);
        if ($share_naira <= 0) { continue; }
        $adjusted_subaccounts[] = [
            'id' => $sub_code,
            'transaction_charge_type' => 'flat_subaccount',
            'transaction_charge' => $share_naira
        ];
    }
} else {
    foreach ($subaccount_shares as $sub_code => $share_naira) {
        $share_naira = (int)round((float)$share_naira);
        if ($share_naira <= 0) { continue; }
        $adjusted_subaccounts[] = [
            'id' => $sub_code,
            'total' => $share_naira
        ];
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Cart saved successfully',
    'gateway' => $gateway_slug,
    'refund_reserved' => (int)$refund_reserved,
    'school_share_before' => (int)$school_share_before,
    'school_share_after' => (int)$school_share_after,
    'adjusted_subaccounts' => $adjusted_subaccounts
]);

mysqli_close($conn);
?>
