<?php
// API: Initialize Payment
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../config/fw.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

$user_id = $user['id'];
$school_id = $user['school'];

// Get cart from session
session_start();
$cart_key = "nivas_cart$user_id";
$cart_event_key = "nivas_cart_event$user_id";

$cart = isset($_SESSION[$cart_key]) ? $_SESSION[$cart_key] : [];
$cart_events = isset($_SESSION[$cart_event_key]) ? $_SESSION[$cart_event_key] : [];

if (empty($cart) && empty($cart_events)) {
    sendApiError('Cart is empty', 400);
}

// Calculate total amount
$total_amount = 0;
$cart_items = [];

// Process manuals
if (!empty($cart)) {
    $cart_ids = array_map('intval', $cart);
    $ids_string = implode(',', $cart_ids);
    
    $manuals_query = mysqli_query($conn, "SELECT * FROM manuals WHERE id IN ($ids_string) AND school_id = $school_id AND status = 'open'");
    
    while ($manual = mysqli_fetch_assoc($manuals_query)) {
        $total_amount += (float)$manual['price'];
        $cart_items[] = [
            'type' => 'manual',
            'id' => $manual['id'],
            'title' => $manual['title'],
            'price' => (float)$manual['price']
        ];
    }
}

// Process events
if (!empty($cart_events)) {
    $event_ids = array_map('intval', $cart_events);
    $event_ids_string = implode(',', $event_ids);
    
    $events_query = mysqli_query($conn, "SELECT * FROM events WHERE id IN ($event_ids_string) AND status = 'open'");
    
    while ($event = mysqli_fetch_assoc($events_query)) {
        $total_amount += (float)$event['price'];
        $cart_items[] = [
            'type' => 'event',
            'id' => $event['id'],
            'title' => $event['title'],
            'price' => (float)$event['price']
        ];
    }
}

if ($total_amount <= 0) {
    sendApiError('Invalid cart amount', 400);
}

// Generate transaction reference
$tx_ref = 'NIVAS_' . time() . '_' . $user_id . '_' . uniqid();

// Save cart to database
$date = date('Y-m-d H:i:s');
foreach ($cart as $manual_id) {
    mysqli_query($conn, "INSERT INTO cart (ref_id, user_id, item_id, type, status, created_at) VALUES ('$tx_ref', $user_id, $manual_id, 'manual', 'pending', '$date')");
}

foreach ($cart_events as $event_id) {
    mysqli_query($conn, "INSERT INTO cart (ref_id, user_id, item_id, type, status, created_at) VALUES ('$tx_ref', $user_id, $event_id, 'event', 'pending', '$date')");
}

// Get active payment gateway
try {
    $gateway = PaymentGatewayFactory::getActiveGateway();
    $gatewayName = $gateway->getGatewayName();
} catch (Exception $e) {
    sendApiError('Payment gateway configuration error: ' . $e->getMessage(), 500);
}

// Initialize payment
$payment_data = [
    'tx_ref' => $tx_ref,
    'amount' => $total_amount,
    'currency' => 'NGN',
    'customer' => [
        'email' => $user['email'],
        'name' => $user['first_name'] . ' ' . $user['last_name'],
        'phone' => $user['phone']
    ],
    'callback_url' => 'https://api.nivasity.com/payment/verify.php?tx_ref=' . $tx_ref,
    'meta' => [
        'user_id' => $user_id,
        'school_id' => $school_id
    ]
];

$init_result = $gateway->initializePayment($payment_data);

if (!$init_result['status']) {
    sendApiError('Failed to initialize payment: ' . ($init_result['message'] ?? 'Unknown error'), 500);
}

sendApiSuccess('Payment initialized successfully', [
    'tx_ref' => $tx_ref,
    'payment_url' => $init_result['data']['payment_url'] ?? null,
    'gateway' => $gatewayName,
    'amount' => $total_amount,
    'items' => $cart_items
]);
?>
