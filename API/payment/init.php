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

// Calculate total amount and collect seller information
$subtotal = 0;
$cart_items = [];
$seller_totals = [];

// Process manuals
if (!empty($cart)) {
    $cart_ids = array_map('intval', $cart);
    $ids_string = implode(',', $cart_ids);
    
    $manuals_query = mysqli_query($conn, "SELECT m.* 
                                          FROM manuals m 
                                          WHERE m.id IN ($ids_string) AND m.school_id = $school_id AND m.status = 'open'");
    
    while ($manual = mysqli_fetch_assoc($manuals_query)) {
        $price = (float)$manual['price'];
        $seller_id = $manual['user_id'];
        $subtotal += $price;
        
        // Track seller totals for split payment
        if (!isset($seller_totals[$seller_id])) {
            $seller_totals[$seller_id] = [
                'total' => 0,
                'seller_id' => $seller_id,
                'school_id' => $school_id
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
    
    $events_query = mysqli_query($conn, "SELECT e.* 
                                         FROM events e 
                                         WHERE e.id IN ($event_ids_string) AND e.status = 'open'");
    
    while ($event = mysqli_fetch_assoc($events_query)) {
        $price = (float)$event['price'];
        $seller_id = $event['user_id'];
        $subtotal += $price;
        
        // Track seller totals for split payment
        if (!isset($seller_totals[$seller_id])) {
            $seller_totals[$seller_id] = [
                'total' => 0,
                'seller_id' => $seller_id,
                'school_id' => $school_id
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

// Always use API verify endpoint as callback (some gateways don't support deep links)
$callback_url = 'https://api.nivasity.com/payment/verify.php?tx_ref=' . $tx_ref;

// Log the callback URL
error_log("Payment Init: callback_url for tx_ref $tx_ref (user $user_id): " . $callback_url);

// Store redirect_url in metadata for use after verification
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
    'customer_phone' => $user['phone'],
    'meta' => $meta_data
];

// Handle gateway-specific split payment configuration
if ($gatewayName === 'paystack') {
    // For Paystack: Create split payment using split API with caching
    if (count($seller_totals) > 0) {
        // Prepare seller data for split creation
        $sellers_for_split = [];
        foreach ($seller_totals as $seller_id => $seller_data) {
            // Get seller's Paystack subaccount from settlement_accounts table
            $ps_subaccount = getSettlementSubaccount($conn, $seller_id, $seller_data['school_id'], 'paystack');
            
            if (!empty($ps_subaccount)) {
                $sellers_for_split[] = [
                    'subaccount' => $ps_subaccount,
                    'share' => round($seller_data['total'] * 100) // Convert to kobo
                ];
            }
        }
        
        if (!empty($sellers_for_split)) {
            // Sort by subaccount for consistent cache key
            usort($sellers_for_split, function($a, $b) { 
                return strcmp($a['subaccount'], $b['subaccount']); 
            });
            
            // Build cache key from sorted sellers
            $cache_key = md5(json_encode($sellers_for_split));
            $cacheFile = __DIR__ . '/../../model/paystack_split_cache.json';
            $cache = [];
            
            // Check cache
            if (file_exists($cacheFile)) {
                $cache = json_decode(file_get_contents($cacheFile), true) ?: [];
            }
            
            if (isset($cache[$cache_key]) && !empty($cache[$cache_key]['split_code'])) {
                // Use cached split code
                $payment_data['split_code'] = $cache[$cache_key]['split_code'];
            } else {
                // Create new Paystack split
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
                        'name' => 'Nivasity Split ' . substr($cache_key, 0, 8),
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
                        $split_code = $split_response['data']['split_code'];
                        $payment_data['split_code'] = $split_code;
                        
                        // Cache the split code
                        $cache[$cache_key] = [
                            'split_code' => $split_code, 
                            'created_at' => time()
                        ];
                        @file_put_contents($cacheFile, json_encode($cache));
                    }
                }
                
                // Fallback: if split creation fails, use first subaccount
                if (!isset($payment_data['split_code']) && count($sellers_for_split) > 0) {
                    $payment_data['subaccount'] = $sellers_for_split[0]['subaccount'];
                    $payment_data['transaction_charge'] = $sellers_for_split[0]['share'] / 100; // Convert back to naira
                }
            }
        }
    }
} elseif ($gatewayName === 'flutterwave') {
    // For Flutterwave: Use subaccounts array
    $subaccounts = [];
    foreach ($seller_totals as $seller_id => $seller_data) {
        // Get seller's Flutterwave subaccount from settlement_accounts table
        $flw_subaccount = getSettlementSubaccount($conn, $seller_id, $seller_data['school_id'], 'flutterwave');
        
        if (!empty($flw_subaccount)) {
            $subaccounts[] = [
                'id' => $flw_subaccount,
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
    'items' => $cart_items
];

// Include redirect_url in response if provided
if ($redirect_url) {
    $response_data['redirect_url'] = $redirect_url;
}

sendApiSuccess('Payment initialized successfully', $response_data);
?>
