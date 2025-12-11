<?php
/**
 * Paystack Webhook Handler
 * 
 * Handles incoming webhooks from Paystack following the Flutterwave flow structure
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once '../config/fw.php';
require_once 'PaymentGatewayFactory.php';
include('mail.php');
include('functions.php');

// Parse incoming webhook
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$headers = function_exists('getallheaders') ? getallheaders() : [];

// Get Paystack gateway instance
try {
    $gateway = PaymentGatewayFactory::getGateway('paystack');
} catch (Exception $e) {
    sendMail('Paystack Webhook: Gateway Error', $e->getMessage(), 'webhook@nivasity.com');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gateway configuration error']);
    exit;
}

// Verify webhook signature
if (!$gateway->verifyWebhookSignature($headers, $raw)) {
    sendMail('Paystack Webhook: Invalid signature', 'Signature verification failed', 'webhook@nivasity.com');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
    exit;
}

// Extract transaction reference from payload
$tx_ref = '';
$status = '';
if (is_array($payload) && isset($payload['data'])) {
    $tx_ref = $payload['data']['reference'] ?? '';
    $status = $payload['data']['status'] ?? '';
    
    if ($tx_ref && $status !== 'success') {
        sendMail('Paystack Webhook: Not successful', 'Status: ' . $status . ' Ref: ' . $tx_ref, 'webhook@nivasity.com');
        http_response_code(200);
        echo json_encode(['status' => 'ok', 'message' => 'Ignored non-success webhook']);
        exit;
    }
}

// Retrieve cart items for this reference
$cartItems = [];
if ($tx_ref) {
    $cartItems[] = $tx_ref;
} else {
    $query = "SELECT DISTINCT ref_id FROM cart";
    $result = mysqli_query($conn, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $cartItems[] = $row['ref_id'];
    }
}

// Process each reference
foreach ($cartItems as $refId) {
    // Verify transaction with Paystack
    $verifyResult = $gateway->verifyTransaction($refId);
    
    if (!$verifyResult['status']) {
        continue;
    }
    
    $tx_ref = $refId;
    
    // Duplicate protection
    $safe_ref = mysqli_real_escape_string($conn, $tx_ref);
    $dupe = false;
    if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM transactions WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    
    if ($dupe) {
        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$safe_ref'");
        sendMail('Paystack Webhook: Duplicate', 'Duplicate delivery for ref ' . $tx_ref, 'webhook@nivasity.com');
        continue;
    }
    
    // Fetch data from cart
    $cart_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$tx_ref'");
    
    if (!$cart_query || mysqli_num_rows($cart_query) < 1) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Cart data not found']);
        exit;
    }
    
    $cart_items = [];
    while ($row = mysqli_fetch_assoc($cart_query)) {
        $cart_items[] = $row;
    }
    
    $statusRes = "success";
    $messageRes = "All items successfully added!";
    $total_amount = 0;
    $manual_ids = [];
    $event_ids = [];
    
    // Process each cart item
    foreach ($cart_items as $item) {
        $item_id = $item['item_id'];
        $type = $item['type'];
        $user_id = $item['user_id'];
        
        if ($type === 'manual') {
            $manual = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $item_id");
            $row = mysqli_fetch_assoc($manual);
            
            $price = $row['price'];
            $total_amount = $total_amount + $price;
            $seller = $row['user_id'];
            
            $user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
            $school = $user['school'];
            
            $manual_ids[] = $item_id;
            
            mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) VALUES ($item_id, $price, $seller, $user_id, '$tx_ref', 'successful', $school)");
        } elseif ($type === 'event') {
            $event = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $item_id");
            $row = mysqli_fetch_assoc($event);
            
            $price = $row['price'];
            $total_amount = $total_amount + $price;
            $seller = $row['user_id'];
            
            $event_ids[] = $item_id;
            
            mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) VALUES ($item_id, $price, $seller, $user_id, '$tx_ref', 'successful')");
        }
        
        if (mysqli_affected_rows($conn) < 1) {
            $statusRes = "error";
            $messageRes = "Failed to add items.";
            break;
        }
    }
    
    // Calculate charges using Paystack-specific logic (with ₦100 exception for > ₦2500)
    $calc = calculateGatewayCharges($total_amount, 'paystack');
    $charge = $calc['charge'];
    $profit = $calc['profit'];
    $total_amount = $calc['total_amount'];
    
    mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status) VALUES ('$tx_ref', $user_id, $total_amount, $charge, $profit, 'successful')");
    
    sendCongratulatoryEmail($conn, $user_id, $tx_ref, $manual_ids, $event_ids, $total_amount);
    mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref'");
    sendMail('Paystack Webhook: Success', 'Processed ref ' . $tx_ref . ' for user ' . $user_id . ' amount NGN ' . number_format($total_amount, 2), 'webhook@nivasity.com');
    
    http_response_code(200);
    echo json_encode(['status' => $statusRes, 'message' => $messageRes]);
}

http_response_code(200);
echo json_encode(['status' => 'ok', 'message' => 'Webhook processed']);
?>
