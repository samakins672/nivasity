<?php
// API: Verify Payment
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../config/fw.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

// Get transaction reference
if (!isset($_GET['tx_ref'])) {
    sendApiError('Transaction reference is required', 400);
}

$tx_ref = sanitizeInput($conn, $_GET['tx_ref']);
$user_id = $user['id'];

// Check if transaction exists
$tx_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$tx_ref' AND user_id = $user_id LIMIT 1");

if (mysqli_num_rows($tx_query) === 0) {
    sendApiError('Transaction not found', 404);
}

// Get gateway from cart
$cart_row = mysqli_fetch_assoc($tx_query);
$gateway_slug = isset($cart_row['gateway']) && !empty($cart_row['gateway']) ? strtolower($cart_row['gateway']) : 'flutterwave';

// Get gateway instance
try {
    $gateway = PaymentGatewayFactory::getGateway($gateway_slug);
} catch (Exception $e) {
    try {
        $gateway = PaymentGatewayFactory::getActiveGateway();
    } catch (Exception $e2) {
        sendApiError('Payment gateway configuration error', 500);
    }
}

// Verify transaction
$verifyResult = $gateway->verifyTransaction($tx_ref);

if (!$verifyResult['status']) {
    sendApiError('Payment verification failed', 400);
}

// Check if already processed
$processed_query = mysqli_query($conn, "SELECT * FROM transactions WHERE ref_id = '$tx_ref' LIMIT 1");

if (mysqli_num_rows($processed_query) > 0) {
    $transaction = mysqli_fetch_assoc($processed_query);
    
    sendApiSuccess('Payment already processed', [
        'status' => 'success',
        'tx_ref' => $tx_ref,
        'amount' => (float)$transaction['amount'],
        'processed_at' => $transaction['created_at']
    ]);
}

// Get payment details from verification
$amount = $verifyResult['data']['amount'] ?? 0;

// Get gateway name from cart for transaction record
$gateway_medium = strtoupper($gateway_slug);

// Calculate charges based on actual amount and gateway
$calc = calculateGatewayCharges($amount, $gateway_slug);
$charge = $calc['charge'];
$profit = $calc['profit'];

// Record transaction with medium (gateway), charge and profit
$date = date('Y-m-d H:i:s');
mysqli_query($conn, "INSERT INTO transactions (user_id, ref_id, amount, charge, profit, status, medium, created_at) VALUES ($user_id, '$tx_ref', $amount, $charge, $profit, 'successful', '$gateway_medium', '$date')");

// Process cart items - mark as confirmed and create purchase records
$cart_items_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$tx_ref' AND user_id = $user_id");

while ($item = mysqli_fetch_assoc($cart_items_query)) {
    if ($item['type'] === 'manual') {
        $manual_id = $item['item_id'];
        $manual_query = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $manual_id");
        
        if (mysqli_num_rows($manual_query) > 0) {
            $manual = mysqli_fetch_assoc($manual_query);
            $price = $manual['price'];
            $seller_id = $manual['user_id'];
            $school_id = $user['school'];
            
            mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, buyer, seller, ref_id, status, school_id, created_at) VALUES ($manual_id, $price, $user_id, $seller_id, '$tx_ref', 'successful', $school_id, '$date')");
        }
    } elseif ($item['type'] === 'event') {
        $event_id = $item['item_id'];
        $event_query = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $event_id");
        
        if (mysqli_num_rows($event_query) > 0) {
            $event = mysqli_fetch_assoc($event_query);
            $price = $event['price'];
            $seller_id = $event['user_id'];
            
            mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, buyer, seller, ref_id, status, created_at) VALUES ($event_id, $price, $user_id, $seller_id, '$tx_ref', 'successful', '$date')");
        }
    }
}

// Mark cart as confirmed
mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref'");

// Clear session cart
session_start();
$_SESSION["nivas_cart$user_id"] = array();
$_SESSION["nivas_cart_event$user_id"] = array();

sendApiSuccess('Payment verified and processed successfully', [
    'status' => 'success',
    'tx_ref' => $tx_ref,
    'amount' => (float)$amount,
    'processed_at' => $date
]);
?>
