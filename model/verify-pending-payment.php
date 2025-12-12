<?php
/**
 * Unified Pending Payment Verification
 * 
 * Routes verification to appropriate payment gateway (Flutterwave, Paystack, or Interswitch)
 * based on the active gateway configuration at the time of verification.
 */

session_start();
require_once 'config.php';
require_once '../config/fw.php';
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
$transaction_ref = isset($_POST['transaction_ref']) ? trim($_POST['transaction_ref']) : '';
$cartRefResolved = false;
$ref_id_esc = mysqli_real_escape_string($conn, $ref_id);

if ($action === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing action parameter']);
    exit;
}

// For verify action, transaction_ref is required
if ($action === 'verify' && $transaction_ref === '') {
    echo json_encode(['status' => 'error', 'message' => 'Transaction reference is required']);
    exit;
}

// For cancel action, ref_id is required
if ($action === 'cancel' && $ref_id === '') {
    echo json_encode(['status' => 'error', 'message' => 'Reference ID is required for cancellation']);
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

// For verify action, if ref_id is empty, we'll find it from pending carts
if ($ref_id === '') {
    // First, if a transaction_ref was provided, try to match it to a pending cart for this user
    if (!empty($transaction_ref)) {
        $tx_ref_esc = mysqli_real_escape_string($conn, $transaction_ref);
        $pending_query = mysqli_query($conn, "SELECT DISTINCT ref_id FROM cart WHERE user_id = $user_id AND status = 'pending' AND ref_id = '$tx_ref_esc' LIMIT 1");
        if ($pending_query && mysqli_num_rows($pending_query) > 0) {
            $pending_row = mysqli_fetch_assoc($pending_query);
            $ref_id = $pending_row['ref_id'];
            $ref_id_esc = mysqli_real_escape_string($conn, $ref_id);
            $cartRefResolved = true;
        } else {
            // Keep the provided transaction_ref as candidate ref_id for later verification, but do not fall back to a different cart
            $ref_id = $transaction_ref;
            $ref_id_esc = mysqli_real_escape_string($conn, $ref_id);
        }
    }
}

// If still empty (no transaction_ref provided), fall back to any pending cart for this user
if ($ref_id === '') {
    // Get any pending cart ref_id for this user
    $pending_query = mysqli_query($conn, "SELECT DISTINCT ref_id FROM cart WHERE user_id = $user_id AND status = 'pending' LIMIT 1");
    if ($pending_query && mysqli_num_rows($pending_query) > 0) {
        $pending_row = mysqli_fetch_assoc($pending_query);
        $ref_id = $pending_row['ref_id'];
        $ref_id_esc = mysqli_real_escape_string($conn, $ref_id);
        $cartRefResolved = true;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No pending payments found']);
        exit;
    }
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
$skipCartProcessingUntilAfterVerification = false;
if (!$cart_query || mysqli_num_rows($cart_query) < 1) {
    if (!$cartRefResolved && !empty($transaction_ref)) {
        // We'll verify first (using the provided transaction_ref) and then attempt to resolve the cart using the verified reference
        $cart_items = [];
        $first_row = null;
        $cart_gateway = 'FLUTTERWAVE'; // default to Flutterwave for unmatched refs
        $skipCartProcessingUntilAfterVerification = true;
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Cart data not found for reference']);
        exit;
    }
} else {
    // Get first row to check status and gateway
    $first_row = mysqli_fetch_assoc($cart_query);

    // Check if cart is still pending
    $cart_status = $first_row['status'] ?? '';
    if ($cart_status !== 'pending') {
        echo json_encode(['status' => 'error', 'message' => 'This cart is no longer pending']);
        exit;
    }

    // Get gateway from cart (all items should have same gateway for same ref_id); fallback to active gateway when missing/empty
    $cart_gateway = !empty($first_row['gateway'])
        ? $first_row['gateway']
        : strtoupper(PaymentGatewayFactory::getActiveGatewayName());

    // Collect all cart items for later processing
    $cart_items = [$first_row];
    while ($row = mysqli_fetch_assoc($cart_query)) {
        $cart_items[] = $row;
    }
}

// Resolve gateway instance from cart gateway
$gateway = null;
$activeGatewayName = strtolower($cart_gateway);
if ($activeGatewayName === '') {
    $activeGatewayName = PaymentGatewayFactory::getActiveGatewayName();
}
try {
    $gateway = PaymentGatewayFactory::getGateway($activeGatewayName);
} catch (Exception $e) {
    $gateway = null;
}

// Determine which reference to use for verification
// If transaction_ref is provided, use it; otherwise use ref_id
$verificationRef = !empty($transaction_ref) ? $transaction_ref : $ref_id;

// Verify transaction with the appropriate gateway
$verificationResult = null;

if ($activeGatewayName === 'paystack') {
    if ($gateway) {
        $verificationResult = $gateway->verifyTransaction($verificationRef);
    }
    if (!$verificationResult || !isset($verificationResult['status']) || $verificationResult['status'] !== true) {
        echo json_encode(['status' => 'pending', 'message' => 'No successful payment found for this transaction reference', 'gateway' => $activeGatewayName, 'verification' => $verificationResult]);
        exit;
    }
} elseif ($activeGatewayName === 'interswitch') {
    if ($gateway) {
        $verificationResult = $gateway->verifyTransaction($verificationRef);
    }
    if (!$verificationResult || !isset($verificationResult['status']) || $verificationResult['status'] !== true) {
        echo json_encode(['status' => 'pending', 'message' => 'No successful payment found for this transaction reference', 'gateway' => $activeGatewayName, 'verification' => $verificationResult]);
        exit;
    }
} else {
    // Default: Verify with Flutterwave
    if ($gateway) {
        $verificationResult = $gateway->verifyTransaction($verificationRef);
    } else {
        // Fallback to direct Flutterwave API call
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.flutterwave.com/v3/transactions?tx_ref=' . urlencode($verificationRef),
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
        echo json_encode(['status' => 'pending', 'message' => 'No successful payment found for this transaction reference', 'gateway' => $activeGatewayName, 'verification' => $verificationResult]);
        exit;
    }
}
// Ensure the verified reference matches the cart reference to avoid crediting with unrelated payments
$verifiedRef = '';
if (isset($verificationResult['data'])) {
    if ($activeGatewayName === 'paystack') {
        $verifiedRef = $verificationResult['data']['reference'] ?? '';
    } elseif ($activeGatewayName === 'interswitch') {
        $verifiedRef = $verificationResult['data']['TransactionRef'] ?? $verificationResult['data']['transactionreference'] ?? '';
    } else {
        $verifiedRef = $verificationResult['data']['tx_ref'] ?? '';
    }
}
if (!empty($verifiedRef) && strcasecmp($verifiedRef, $ref_id) !== 0) {
    echo json_encode(['status' => 'pending', 'message' => 'Verification reference does not match cart reference', 'gateway' => $activeGatewayName, 'verification' => $verificationResult, 'cart_ref' => $ref_id, 'verified_ref' => $verifiedRef]);
    exit;
}

$cart_was_missing_before_verify = $skipCartProcessingUntilAfterVerification;

// If we deferred cart lookup because the provided transaction_ref was not found, try again using the verified reference
if ($skipCartProcessingUntilAfterVerification) {
    $lookup_ref = !empty($verifiedRef) ? $verifiedRef : $ref_id;
    $lookup_ref_esc = mysqli_real_escape_string($conn, $lookup_ref);
    $cart_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$lookup_ref_esc' AND user_id = $user_id");
    if (!$cart_query || mysqli_num_rows($cart_query) < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Cart data not found for verified reference', 'gateway' => $activeGatewayName, 'verification' => $verificationResult]);
        exit;
    }
    $first_row = mysqli_fetch_assoc($cart_query);
    $cart_status = $first_row['status'] ?? '';
    if ($cart_status !== 'pending') {
        echo json_encode(['status' => 'error', 'message' => 'This cart is no longer pending', 'gateway' => $activeGatewayName, 'verification' => $verificationResult]);
        exit;
    }
    $cart_gateway = !empty($first_row['gateway'])
        ? $first_row['gateway']
        : strtoupper(PaymentGatewayFactory::getActiveGatewayName());
    $cart_items = [$first_row];
    while ($row = mysqli_fetch_assoc($cart_query)) {
        $cart_items[] = $row;
    }
    // Update ref_id to the verified reference we matched to the cart
    $ref_id = $lookup_ref;
    $ref_id_esc = $lookup_ref_esc;
}

$manual_ids = [];
$event_ids = [];
$sum_amount = 0.0;
$status = 'successful';

// Process each cart item from the collected array
foreach ($cart_items as $row) {
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
                    echo json_encode(['status' => 'error', 'message' => 'Failed to add manual to purchases', 'gateway' => $activeGatewayName, 'verification' => $verificationResult]);
                    exit;
                }
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Manual not found for purchase', 'gateway' => $activeGatewayName, 'verification' => $verificationResult]);
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
                    echo json_encode(['status' => 'error', 'message' => 'Failed to add event ticket', 'gateway' => $activeGatewayName, 'verification' => $verificationResult]);
                    exit;
                }
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Event not found for purchase', 'gateway' => $activeGatewayName, 'verification' => $verificationResult]);
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
    'gateway' => $activeGatewayName,
    'verification' => $verificationResult
]);
