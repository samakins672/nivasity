<?php
// API: Initialize Payment
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../model/functions.php';
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

// Calculate total amount and collect seller information
$subtotal = 0;
$cart_items = [];
$seller_totals = [];

// Process manuals
if (!empty($cart)) {
    $cart_ids = array_map('intval', $cart);
    $ids_string = implode(',', $cart_ids);
    
    $manuals_query = mysqli_query($conn, "SELECT m.*, u.ps_subaccount, u.flw_subaccount 
                                          FROM manuals m 
                                          LEFT JOIN users u ON m.user_id = u.id 
                                          WHERE m.id IN ($ids_string) AND m.school_id = $school_id AND m.status = 'open'");
    
    while ($manual = mysqli_fetch_assoc($manuals_query)) {
        $price = (float)$manual['price'];
        $seller_id = $manual['user_id'];
        $subtotal += $price;
        
        // Track seller totals for split payment
        if (!isset($seller_totals[$seller_id])) {
            $seller_totals[$seller_id] = [
                'total' => 0,
                'ps_subaccount' => $manual['ps_subaccount'] ?? null,
                'flw_subaccount' => $manual['flw_subaccount'] ?? null
            ];
        }
        $seller_totals[$seller_id]['total'] += $price;
        
        $cart_items[] = [
            'type' => 'manual',
            'id' => $manual['id'],
            'title' => $manual['title'],
            'price' => $price,
            'seller_id' => $seller_id
        ];
    }
}

// Process events
if (!empty($cart_events)) {
    $event_ids = array_map('intval', $cart_events);
    $event_ids_string = implode(',', $event_ids);
    
    $events_query = mysqli_query($conn, "SELECT e.*, u.ps_subaccount, u.flw_subaccount 
                                         FROM events e 
                                         LEFT JOIN users u ON e.user_id = u.id 
                                         WHERE e.id IN ($event_ids_string) AND e.status = 'open'");
    
    while ($event = mysqli_fetch_assoc($events_query)) {
        $price = (float)$event['price'];
        $seller_id = $event['user_id'];
        $subtotal += $price;
        
        // Track seller totals for split payment
        if (!isset($seller_totals[$seller_id])) {
            $seller_totals[$seller_id] = [
                'total' => 0,
                'ps_subaccount' => $event['ps_subaccount'] ?? null,
                'flw_subaccount' => $event['flw_subaccount'] ?? null
            ];
        }
        $seller_totals[$seller_id]['total'] += $price;
        
        $cart_items[] = [
            'type' => 'event',
            'id' => $event['id'],
            'title' => $event['title'],
            'price' => $price,
            'seller_id' => $seller_id
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

// Prepare payment parameters
$payment_data = [
    'amount' => $total_amount,
    'email' => $user['email'],
    'reference' => $tx_ref,
    'callback_url' => 'https://api.nivasity.com/payment/verify.php?tx_ref=' . $tx_ref,
    'customer_name' => $user['first_name'] . ' ' . $user['last_name'],
    'customer_phone' => $user['phone'],
    'meta' => [
        'user_id' => $user_id,
        'school_id' => $school_id
    ]
];

// Handle gateway-specific split payment configuration
if ($gatewayName === 'paystack') {
    // For Paystack: Create split payment using split API
    if (count($seller_totals) > 0) {
        // Prepare seller data for split creation
        $sellers_for_split = [];
        foreach ($seller_totals as $seller_id => $seller_data) {
            if (!empty($seller_data['ps_subaccount'])) {
                $sellers_for_split[] = [
                    'subaccount' => $seller_data['ps_subaccount'],
                    'share' => round($seller_data['total'] * 100) // Convert to kobo
                ];
            }
        }
        
        if (!empty($sellers_for_split)) {
            // Create Paystack split
            $split_data = [
                'sellers' => $sellers_for_split,
                'amount_kobo' => round($total_amount * 100),
                'bearer_type' => 'account'
            ];
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.paystack.co/split',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'name' => 'Nivasity Split ' . substr($tx_ref, -8),
                    'type' => 'flat',
                    'currency' => 'NGN',
                    'subaccounts' => $sellers_for_split,
                    'bearer_type' => 'account'
                ]),
            ]);
            
            $response = curl_exec($curl);
            $error = curl_error($curl);
            curl_close($curl);
            
            if (!$error) {
                $split_response = json_decode($response, true);
                if (isset($split_response['status']) && $split_response['status'] === true && 
                    isset($split_response['data']['split_code'])) {
                    $payment_data['split_code'] = $split_response['data']['split_code'];
                }
            }
            
            // Fallback: if split creation fails, use first subaccount
            if (!isset($payment_data['split_code']) && count($sellers_for_split) > 0) {
                $payment_data['subaccount'] = $sellers_for_split[0]['subaccount'];
                $payment_data['transaction_charge'] = $sellers_for_split[0]['share'] / 100; // Convert back to naira
            }
        }
    }
} elseif ($gatewayName === 'flutterwave') {
    // For Flutterwave: Use subaccounts array
    $subaccounts = [];
    foreach ($seller_totals as $seller_id => $seller_data) {
        if (!empty($seller_data['flw_subaccount'])) {
            $subaccounts[] = [
                'id' => $seller_data['flw_subaccount'],
                'transaction_charge_type' => 'flat_subaccount',
                'transaction_charge' => $seller_data['total']
            ];
        }
    }
    
    if (!empty($subaccounts)) {
        $payment_data['subaccounts'] = $subaccounts;
    }
}

// Initialize payment
$init_result = $gateway->initializePayment($payment_data);

if (!$init_result['status']) {
    sendApiError('Failed to initialize payment: ' . ($init_result['message'] ?? 'Unknown error'), 500);
}

sendApiSuccess('Payment initialized successfully', [
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
    'items' => $cart_items
]);
?>
