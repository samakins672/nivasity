<?php
/**
 * Unified Payment Handler
 * 
 * Routes payment processing to the appropriate gateway based on configuration
 * This handler supports both payment initialization and verification
 */

session_start();
require_once 'config.php';
require_once '../config/fw.php';
require_once 'PaymentGatewayFactory.php';
include('mail.php');
include('functions.php');

$statusRes = "success";
$messageRes = "Payment processed successfully!";

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$cart_ = $_SESSION["nivas_cart$user_id"];
$cart_2 = $_SESSION["nivas_cart_event$user_id"]; // Cart for events

// Handle payment verification (callback from gateway)
if (isset($_GET['transaction_id']) || isset($_GET['reference']) || isset($_GET['tx_ref'])) {
    $tx_ref = $_GET['tx_ref'] ?? $_GET['reference'] ?? '';
    
    if (empty($tx_ref)) {
        header('Location: /?payment=unsuccessful');
        exit;
    }

    // Resolve gateway/medium from cart entry (default to FLUTTERWAVE)
    $cart_gateway_raw = 'FLUTTERWAVE';
    $tx_ref_esc = mysqli_real_escape_string($conn, $tx_ref);
    $cart_gateway_q = mysqli_query($conn, "SELECT gateway FROM cart WHERE ref_id = '$tx_ref_esc' LIMIT 1");
    if ($cart_gateway_q && mysqli_num_rows($cart_gateway_q) > 0) {
        $cg_row = mysqli_fetch_assoc($cart_gateway_q);
        if (!empty($cg_row['gateway'])) {
            $cart_gateway_raw = $cg_row['gateway'];
        }
    }
    $resolvedGatewaySlug = strtolower($cart_gateway_raw);

    // Get gateway instance based on cart gateway (fallback to active)
    $gateway = null;
    $gatewayName = null;
    try {
        $gateway = PaymentGatewayFactory::getGateway($resolvedGatewaySlug);
        $gatewayName = $gateway->getGatewayName();
    } catch (Exception $e) {
        try {
            $gateway = PaymentGatewayFactory::getActiveGateway();
            $gatewayName = $gateway->getGatewayName();
        } catch (Exception $e2) {
            $statusRes = "error";
            $messageRes = "Payment gateway configuration error: " . $e2->getMessage();
            header('Content-Type: application/json');
            echo json_encode(['status' => $statusRes, 'message' => $messageRes]);
            exit;
        }
    }
    
    // Verify transaction with the gateway (Flutterwave prefers transaction_id)
    $verifyParam = $tx_ref;
    if ($gatewayName === 'flutterwave' && isset($_GET['transaction_id'])) {
        $verifyParam = $_GET['transaction_id'];
    }
    $verifyResult = $gateway->verifyTransaction($verifyParam);
    
    if (!$verifyResult['status']) {
        if (!isset($_GET['callback'])) {
            header('Location: /?payment=unsuccessful');
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Payment verification failed']);
        }
        exit;
    }
    
    $status = 'successful';
    
    // Duplicate protection: if already processed, just confirm cart and exit
    $safe_ref = mysqli_real_escape_string($conn, $tx_ref);
    $dupe = false;
    if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM transactions WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    
    if ($dupe) {
        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$safe_ref'");
        $_SESSION["nivas_cart$user_id"] = array();
        $_SESSION["nivas_cart_event$user_id"] = array();
        if (!isset($_GET['callback'])) {
            header('Location: /?payment=successful');
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Already processed']);
        }
        exit;
    }
    
    $total_amount = 0;
    
    // Process manuals in cart
    foreach ($cart_ as $manual_id) {
        $manual = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $manual_id AND school_id = $school_id");
        
        if ($manual && mysqli_num_rows($manual) > 0) {
            $row = mysqli_fetch_assoc($manual);
            $price = $row['price'];
            $total_amount = $total_amount + $price;
            $seller = $row['user_id'];
            
            mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) VALUES ($manual_id, $price, $seller, $user_id, '$tx_ref', '$status', $school_id)");
            
            if (mysqli_affected_rows($conn) < 1) {
                $statusRes = "error";
                $messageRes = "Internal Server Error. Please try again later!";
                break;
            }
        } else {
            $statusRes = "error";
            $messageRes = "Unable to fetch details from manuals. Please try again later!";
            break;
        }
    }
    
    // Process event tickets in cart
    foreach ($cart_2 as $event_id) {
        $event = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $event_id");
        
        if ($event && mysqli_num_rows($event) > 0) {
            $row = mysqli_fetch_assoc($event);
            $price = $row['price'];
            $total_amount = $total_amount + $price;
            $seller = $row['user_id'];
            
            mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) VALUES ($event_id, $price, $seller, $user_id, '$tx_ref', '$status')");
            
            if (mysqli_affected_rows($conn) < 1) {
                $statusRes = "error";
                $messageRes = "Internal Server Error while adding event ticket. Please try again later!";
                break;
            }
        } else {
            $statusRes = "error";
            $messageRes = "Unable to fetch details from events. Please try again later!";
            break;
        }
    }
    
    // Calculate charges using the gateway-specific logic
    $calc = calculateGatewayCharges($total_amount, $gatewayName);
    $charge = $calc['charge'];
    $profit = $calc['profit'];
    $total_amount = $calc['total_amount'];
    
    sendCongratulatoryEmail($conn, $user_id, $tx_ref, $cart_, $cart_2, $total_amount);
    
    // Save transaction with gateway medium
    $medium = mysqli_real_escape_string($conn, strtoupper($gatewayName));
    mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) VALUES ('$tx_ref', $user_id, $total_amount, $charge, $profit, '$status', '$medium')");
    
    mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref'");
    
    // Empty the cart variables for both manuals and events
    $_SESSION["nivas_cart$user_id"] = array();
    $_SESSION["nivas_cart_event$user_id"] = array();
    
    if (!isset($_GET['callback'])) {
        header('Location: /?payment=successful');
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => $statusRes, 'message' => $messageRes]);
    }
    exit;
}

// If no verification request, return error
header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>
