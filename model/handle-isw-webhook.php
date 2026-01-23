<?php
/**
 * Interswitch Webhook Handler
 * 
 * Handles incoming webhooks/callbacks from Interswitch following the Flutterwave flow structure
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once __DIR__ . '/../config/fw.php';
require_once 'PaymentGatewayFactory.php';
include('mail.php');
include('functions.php');
require_once __DIR__ . '/notifications.php';

// Get Interswitch gateway instance
try {
    $gateway = PaymentGatewayFactory::getGateway('interswitch');
} catch (Exception $e) {
    sendMail('Interswitch Webhook: Gateway Error', $e->getMessage(), 'webhook@nivasity.com');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gateway configuration error']);
    exit;
}

// Interswitch sends callback parameters as GET/POST
$tx_ref = $_GET['txnref'] ?? $_POST['txnref'] ?? '';
$responseCode = $_GET['resp'] ?? $_POST['resp'] ?? '';
$amount = $_GET['amount'] ?? $_POST['amount'] ?? 0;

if (empty($tx_ref)) {
    sendMail('Interswitch Webhook: Missing ref', 'No transaction reference provided', 'webhook@nivasity.com');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing transaction reference']);
    exit;
}

// Only process successful transactions (response code 00)
if ($responseCode !== '00') {
    sendMail('Interswitch Webhook: Not successful', 'Response code: ' . $responseCode . ' Ref: ' . $tx_ref, 'webhook@nivasity.com');
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Ignored non-success webhook']);
    exit;
}

// Verify transaction with Interswitch API
$verifyResult = $gateway->verifyTransaction($tx_ref);

if (!$verifyResult['status']) {
    sendMail('Interswitch Webhook: Verification failed', 'Ref: ' . $tx_ref, 'webhook@nivasity.com');
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Transaction verification failed']);
    exit;
}

// Duplicate protection
$safe_ref = mysqli_real_escape_string($conn, $tx_ref);
$dupe = false;
if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM transactions WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }

if ($dupe) {
    mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$safe_ref'");
    sendMail('Interswitch Webhook: Duplicate', 'Duplicate delivery for ref ' . $tx_ref, 'webhook@nivasity.com');
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Already processed']);
    exit;
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

// Calculate charges using gateway-specific logic (Interswitch uses standard pricing)
$gatewayName = $gateway->getGatewayName();
$calc = calculateGatewayCharges($total_amount, $gatewayName);
$charge = $calc['charge'];
$profit = $calc['profit'];
$total_amount = $calc['total_amount'];

mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) VALUES ('$tx_ref', $user_id, $total_amount, $charge, $profit, 'successful', 'INTERSWITCH')");

sendCongratulatoryEmail($conn, $user_id, $tx_ref, $manual_ids, $event_ids, $total_amount);

// Send push notification to user
notifyUser($conn, $user_id, 
    'Payment Successful', 
    "Your payment of ₦" . number_format($total_amount, 2) . " has been confirmed.", 
    'payment', 
    ['action' => 'order_receipt', 'tx_ref' => $tx_ref, 'amount' => $total_amount, 'status' => 'successful']
);

mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref'");
sendMail('Interswitch Webhook: Success', 'Processed ref ' . $tx_ref . ' for user ' . $user_id . ' amount NGN ' . number_format($total_amount, 2), 'webhook@nivasity.com');

http_response_code(200);
echo json_encode(['status' => $statusRes, 'message' => $messageRes]);
?>
