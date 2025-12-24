<?php
/**
 * Unified Pending Payment Verification
 * 
 * Routes verification to appropriate payment gateway (Flutterwave, Paystack, or Interswitch)
 * based on the active gateway configuration at the time of verification.
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/../config/fw.php';
require_once 'PaymentGatewayFactory.php';
include('functions.php');
include('mail.php');

header('Content-Type: application/json');

$user_id = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
$school_id = isset($_SESSION['nivas_userSch']) ? (int)$_SESSION['nivas_userSch'] : 0;

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$ref_id = isset($_POST['ref_id']) ? trim($_POST['ref_id']) : '';
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($ref_id === '' || $action === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

$ref_id_esc = mysqli_real_escape_string($conn, $ref_id);

// Handle cancellation
if ($action === 'cancel') {
    mysqli_query($conn, "UPDATE cart SET status = 'cancelled' WHERE ref_id = '$ref_id_esc' AND user_id = $user_id AND status = 'pending'");
    echo json_encode(['status' => 'success', 'message' => 'Payment cancelled']);
    exit;
}

if ($action !== 'verify') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

// Duplicate protection before verify
$dupe = false;
if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM transactions WHERE ref_id = '$ref_id_esc' LIMIT 1")) > 0) { 
    $dupe = true; 
}
if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$ref_id_esc' LIMIT 1")) > 0) { 
    $dupe = true; 
}
if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$ref_id_esc' LIMIT 1")) > 0) { 
    $dupe = true; 
}

if ($dupe) {
    // Mark confirmed and cleanup session
    mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$ref_id_esc'");
    
    $cart_items_rs = mysqli_query($conn, "SELECT item_id, type FROM cart WHERE ref_id = '$ref_id_esc' AND user_id = $user_id");
    if ($cart_items_rs) {
        $manual_ids = [];
        $event_ids = [];
        while ($ci = mysqli_fetch_assoc($cart_items_rs)) {
            if ($ci['type'] === 'manual') { $manual_ids[] = (int)$ci['item_id']; }
            elseif ($ci['type'] === 'event') { $event_ids[] = (int)$ci['item_id']; }
        }
        $cartManualKey = "nivas_cart$user_id";
        $cartEventKey = "nivas_cart_event$user_id";
        if (!empty($manual_ids) && isset($_SESSION[$cartManualKey])) {
            $_SESSION[$cartManualKey] = array_values(array_diff($_SESSION[$cartManualKey], $manual_ids));
        }
        if (!empty($event_ids) && isset($_SESSION[$cartEventKey])) {
            $_SESSION[$cartEventKey] = array_values(array_diff($_SESSION[$cartEventKey], $event_ids));
        }
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Already processed']);
    exit;
}

// Fetch cart rows for this ref and user to get gateway information
$cart_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$ref_id_esc' AND user_id = $user_id");
if (!$cart_query || mysqli_num_rows($cart_query) < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Cart data not found for reference']);
    exit;
}

// Get gateway from cart (all items should have same gateway for same ref_id)
$first_row = mysqli_fetch_assoc($cart_query);
$cart_gateway = $first_row['gateway'] ?? 'FLUTTERWAVE';
mysqli_data_seek($cart_query, 0); // Reset pointer to beginning

// Resolve gateway instance from cart gateway
$gateway = null;
$activeGatewayName = strtolower($cart_gateway);
try {
    $gateway = PaymentGatewayFactory::getGateway($activeGatewayName);
} catch (Exception $e) {
    $gateway = null;
}

// Verify transaction with the appropriate gateway
$verificationResult = null;

if ($activeGatewayName === 'paystack') {
    if ($gateway) {
        $verificationResult = $gateway->verifyTransaction($ref_id);
    }
    if (!$verificationResult || !isset($verificationResult['status']) || $verificationResult['status'] !== true) {
        echo json_encode(['status' => 'pending', 'message' => 'No successful payment found for ref']);
        exit;
    }
} elseif ($activeGatewayName === 'interswitch') {
    if ($gateway) {
        $verificationResult = $gateway->verifyTransaction($ref_id);
    }
    if (!$verificationResult || !isset($verificationResult['status']) || $verificationResult['status'] !== true) {
        echo json_encode(['status' => 'pending', 'message' => 'No successful payment found for ref']);
        exit;
    }
} else {
    // Default: Verify with Flutterwave
    if ($gateway) {
        $verificationResult = $gateway->verifyTransaction($ref_id);
    } else {
        // Fallback to direct Flutterwave API call
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.flutterwave.com/v3/transactions?tx_ref=' . urlencode($ref_id),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . FLW_SECRET_KEY,
            ],
        ]);
        $resp = curl_exec($curl);
        curl_close($curl);
        
        $data = json_decode($resp, true);
        if ($data && isset($data['status']) && $data['status'] === 'success' && 
            isset($data['data'][0]['status']) && $data['data'][0]['status'] === 'successful') {
            $verificationResult = ['status' => true, 'data' => $data['data'][0]];
        }
    }
    
    if (!$verificationResult || !isset($verificationResult['status']) || $verificationResult['status'] !== true) {
        echo json_encode(['status' => 'pending', 'message' => 'No successful payment found for ref']);
        exit;
    }
}

$manual_ids = [];
$event_ids = [];
$sum_amount = 0.0;
$status = 'successful';

// Process each cart item
while ($row = mysqli_fetch_assoc($cart_query)) {
    $item_id = (int)$row['item_id'];
    $type = $row['type'];
    
    if ($type === 'manual') {
        $manual = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $item_id AND school_id = $school_id");
        if ($manual && mysqli_num_rows($manual) > 0) {
            $m = mysqli_fetch_assoc($manual);
            $price = (float)$m['price'];
            $seller = (int)$m['user_id'];
            $sum_amount += $price;
            $manual_ids[] = $item_id;
            
            // Check for duplicate before inserting
            $exists = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$ref_id_esc' AND manual_id = $item_id LIMIT 1");
            if (mysqli_num_rows($exists) === 0) {
                mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) VALUES ($item_id, $price, $seller, $user_id, '$ref_id_esc', '$status', $school_id)");
                if (mysqli_affected_rows($conn) < 1) {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to add manual to purchases']);
                    exit;
                }
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Manual not found for purchase']);
            exit;
        }
    } elseif ($type === 'event') {
        $event = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $item_id");
        if ($event && mysqli_num_rows($event) > 0) {
            $e = mysqli_fetch_assoc($event);
            $price = (float)$e['price'];
            $seller = (int)$e['user_id'];
            $sum_amount += $price;
            $event_ids[] = $item_id;
            
            // Check for duplicate before inserting
            $exists = mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$ref_id_esc' AND event_id = $item_id LIMIT 1");
            if (mysqli_num_rows($exists) === 0) {
                mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) VALUES ($item_id, $price, $seller, $user_id, '$ref_id_esc', '$status')");
                if (mysqli_affected_rows($conn) < 1) {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to add event ticket']);
                    exit;
                }
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Event not found for purchase']);
            exit;
        }
    }
}

// Calculate charges using gateway-specific pricing (rounded to whole numbers)
$calc = calculateGatewayCharges($sum_amount, strtolower($cart_gateway));
$total_amount = round((float)$calc['total_amount']);
$charge = round((float)$calc['charge']);
$profit = round((float)$calc['profit']);

// Record transaction with gateway/medium information
$medium = mysqli_real_escape_string($conn, strtoupper($cart_gateway));
mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) VALUES ('$ref_id_esc', $user_id, $total_amount, $charge, $profit, '$status', '$medium')");

// Send congratulatory email
sendCongratulatoryEmail($conn, $user_id, $ref_id, $manual_ids, $event_ids, $total_amount);

// Update cart status
mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$ref_id_esc'");

// Remove purchased items from session carts
$cartManualKey = "nivas_cart$user_id";
$cartEventKey = "nivas_cart_event$user_id";
if (!empty($manual_ids) && isset($_SESSION[$cartManualKey])) {
    $_SESSION[$cartManualKey] = array_values(array_diff($_SESSION[$cartManualKey], $manual_ids));
}
if (!empty($event_ids) && isset($_SESSION[$cartEventKey])) {
    $_SESSION[$cartEventKey] = array_values(array_diff($_SESSION[$cartEventKey], $event_ids));
}

echo json_encode([
    'status' => 'success', 
    'message' => 'Payment confirmed and items delivered',
    'gateway' => $activeGatewayName
]);
