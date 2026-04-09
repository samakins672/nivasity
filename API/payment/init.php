<?php
// API: Initialize Payment
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../model/functions.php';
require_once __DIR__ . '/../../model/refund_engine.php';
require_once __DIR__ . '/../../model/payment_freeze.php';
require_once __DIR__ . '/../../config/fw.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

// Check if payments are frozen
if (is_payment_frozen()) {
    $freeze_info = get_payment_freeze_info();
    $message = ($freeze_info && isset($freeze_info['message'])) 
        ? $freeze_info['message'] 
        : 'Payments are currently paused. Please try again later.';
    sendApiError($message, 403);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

$user_id = $user['id'];
$school_id = $user['school'];

// Get optional redirect URL from request body
$input = json_decode(file_get_contents('php://input'), true);
$redirect_url = isset($input['redirect_url']) ? trim($input['redirect_url']) : null;

// Log redirect URL if provided
if ($redirect_url) {
    error_log("Payment Init: redirect_url received from user $user_id: " . $redirect_url);
}

// Validate redirect_url if provided
if ($redirect_url && !filter_var($redirect_url, FILTER_VALIDATE_URL)) {
    error_log("Payment Init: Invalid redirect_url format from user $user_id: " . $redirect_url);
    sendApiError('Invalid redirect_url format', 400);
}

// Get cart from session
session_start();
$cart_key = "nivas_cart$user_id";
$cart_event_key = "nivas_cart_event$user_id";

$cart = isset($_SESSION[$cart_key]) ? $_SESSION[$cart_key] : [];
$cart_events = isset($_SESSION[$cart_event_key]) ? $_SESSION[$cart_event_key] : [];

if (empty($cart) && empty($cart_events)) {
    sendApiError('Cart is empty', 400);
}

// Calculate total amount and collect cart items
$subtotal = 0;
$cart_items = [];

// Process manuals
if (!empty($cart)) {
    $cart_ids = array_map('intval', $cart);
    $ids_string = implode(',', $cart_ids);
    
    $manuals_query = mysqli_query($conn, "SELECT m.* 
                                          FROM manuals m 
                                          WHERE m.id IN ($ids_string) AND m.school_id = $school_id AND m.status = 'open'");
    
    while ($manual = mysqli_fetch_assoc($manuals_query)) {
        $price = (float)$manual['price'];
        $subtotal += $price;
        
        $cart_items[] = [
            'type' => 'manual',
            'id' => $manual['id'],
            'title' => $manual['title'],
            'price' => $price,
            'seller_id' => $manual['user_id']
        ];
    }
}

// Process events
if (!empty($cart_events)) {
    $event_ids = array_map('intval', $cart_events);
    $event_ids_string = implode(',', $event_ids);
    
    $events_query = mysqli_query($conn, "SELECT e.* 
                                         FROM events e 
                                         WHERE e.id IN ($event_ids_string) AND e.status = 'open'");
    
    while ($event = mysqli_fetch_assoc($events_query)) {
        $price = (float)$event['price'];
        $subtotal += $price;
        
        $cart_items[] = [
            'type' => 'event',
            'id' => $event['id'],
            'title' => $event['title'],
            'price' => $price,
            'seller_id' => $event['user_id']
        ];
    }
}

if ($subtotal <= 0) {
    sendApiError('Invalid cart amount', 400);
}

// Calculate charges using active gateway
$charges_result = calculateGatewayCharges($subtotal);
$charge = $charges_result['charge'] ?? 0;
$total_amount = $charges_result['total_amount'] ?? ($subtotal + $charge);

// Generate transaction reference
$tx_ref = 'nivas_'. $user_id . '_' . time();

// Get active payment gateway (need this before saving to cart)
try {
    $gateway = PaymentGatewayFactory::getActiveGateway();
    $gatewayName = $gateway->getGatewayName();
} catch (Exception $e) {
    sendApiError('Payment gateway configuration error: ' . $e->getMessage(), 500);
}

// Save cart to database with gateway information
$date = date('Y-m-d H:i:s');
$gateway_upper = strtoupper($gatewayName);
foreach ($cart as $manual_id) {
    mysqli_query($conn, "INSERT INTO cart (ref_id, user_id, item_id, type, status, gateway, created_at) VALUES ('$tx_ref', $user_id, $manual_id, 'manual', 'pending', '$gateway_upper', '$date')");
}

foreach ($cart_events as $event_id) {
    mysqli_query($conn, "INSERT INTO cart (ref_id, user_id, item_id, type, status, gateway, created_at) VALUES ('$tx_ref', $user_id, $event_id, 'event', 'pending', '$gateway_upper', '$date')");
}

// Always use API callback endpoint as callback (unauthenticated, some gateways don't support deep links)
$callback_url = 'https://api.nivasity.com/payment/callback.php?tx_ref=' . $tx_ref;

// Log the callback URL
error_log("Payment Init: callback_url for tx_ref $tx_ref (user $user_id): " . $callback_url);

// Store redirect_url in metadata for use after verification
// Note: Paystack uses "metadata", Flutterwave uses "meta"
$meta_data = [
    'user_id' => $user_id,
    'school_id' => $school_id
];

if ($redirect_url) {
    $meta_data['redirect_url'] = $redirect_url;
    error_log("Payment Init: redirect_url stored in metadata for tx_ref $tx_ref: " . $redirect_url);
}

$payment_data = [
    'amount' => $total_amount,
    'email' => $user['email'],
    'reference' => $tx_ref,
    'callback_url' => $callback_url,
    'customer_name' => $user['first_name'] . ' ' . $user['last_name'],
    'customer_phone' => $user['phone']
];

// Add metadata field based on gateway
// Paystack uses "metadata", Flutterwave uses "meta"
if ($gatewayName === 'paystack') {
    $payment_data['metadata'] = $meta_data;
} else {
    // Flutterwave and others use "meta"
    $payment_data['meta'] = $meta_data;
}

// Run housekeeping for stale reserved rows.
releaseExpiredReservations($conn, 60);

$school_share_before = (int)round((float)$subtotal);

$refund_reserved = 0;
if ($school_share_before > 0) {
    $refund_reserved = reserveRefundForSchoolShare(
        $conn,
        $tx_ref,
        $school_id,
        $user_id,
        strtoupper($gatewayName),
        $school_share_before,
        'api'
    );
}
$school_share_after = max(0, $school_share_before - $refund_reserved);

// Initialize payment
$init_result = $gateway->initializePayment($payment_data);

if (!$init_result['status']) {
    releaseReservationsForTx($conn, $tx_ref, 'init_failed');
    sendApiError('Failed to initialize payment: ' . ($init_result['message'] ?? 'Unknown error'), 500);
}

$response_data = [
    'tx_ref' => $tx_ref,
    'payment_url' =>
        $init_result['data']['authorization_url']
        ?? $init_result['data']['link']
        ?? $init_result['data']['payment_url']
        ?? null,
    'gateway' => $gatewayName,
    'subtotal' => $subtotal,
    'charge' => $charge,
    'total_amount' => $total_amount,
    'internal_settlement_mode' => true,
    'refund_reserved' => (int)$refund_reserved,
    'school_share_before' => (int)$school_share_before,
    'school_share_after' => (int)$school_share_after,
    'items' => $cart_items
];

// Include redirect_url in response if provided
if ($redirect_url) {
    $response_data['redirect_url'] = $redirect_url;
}

sendApiSuccess('Payment initialized successfully', $response_data);
?>
