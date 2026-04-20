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
require_once __DIR__ . '/../config/fw.php';
require_once 'PaymentGatewayFactory.php';
include('mail.php');
include('functions.php');
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/refund_engine.php';
require_once __DIR__ . '/internal_wallet_service.php';

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

// Check event type - only process charge.success events
$event_type = $payload['event'] ?? '';
if ($event_type !== 'charge.success') {
    // Acknowledge receipt but don't process other event types
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Event type ignored: ' . $event_type]);
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

$webhookIntent = nivasityDeterminePaystackWebhookIntent($conn, $payload);

// Respond immediately to prevent retries
http_response_code(200);
echo json_encode(['status' => 'ok', 'message' => 'Webhook received']);

// Flush output buffers to send response immediately
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

if ($webhookIntent === 'wallet_funding') {
    $wallet = nivasityResolveWalletFromPaystackPayload($conn, $payload['data'] ?? []);
    if ($wallet) {
        try {
            $fundingResult = nivasityApplyWalletFundingTransaction($conn, $wallet, $payload['data'], 'webhook', $event_type);
            sendMail(
                'Paystack Webhook: Wallet Funding',
                'Wallet funding ' . ($fundingResult['status'] ?? 'processed') . ' for user ' . (int)$wallet['user_id'] . ' ref ' . ($payload['data']['reference'] ?? ''),
                'webhook@nivasity.com'
            );
        } catch (Throwable $e) {
            sendMail('Paystack Webhook: Wallet Funding Error', $e->getMessage(), 'webhook@nivasity.com');
        }
    }
    exit;
}

if ($webhookIntent === 'unknown') {
    sendMail('Paystack Webhook: Unknown charge.success', 'Unable to classify webhook payload for ref ' . $tx_ref, 'webhook@nivasity.com');
    exit;
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
        $refundApplied = consumeReservationsForSettledTx($conn, $tx_ref);
        try {
            nivasityEnsureSchoolPayableForPurchase($conn, [
                'source_ref_id' => $tx_ref,
                'source_medium' => 'PAYSTACK',
                'source_channel' => 'webhook',
                'refund_amount' => $refundApplied,
                'metadata' => [
                    'handler' => 'model/handle-ps-webhook.php',
                    'repair_reason' => 'duplicate_already_processed',
                ],
            ]);
        } catch (Throwable $repairError) {
            sendMail('Paystack Webhook: Ledger Repair Error', $repairError->getMessage() . ' Ref: ' . $tx_ref, 'webhook@nivasity.com');
        }
        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$safe_ref'");
        sendMail('Paystack Webhook: Duplicate', 'Duplicate delivery for ref ' . $tx_ref, 'webhook@nivasity.com');
        continue;
    }
    
    // Fetch data from cart
    $cart_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$tx_ref'");
    
    if (!$cart_query || mysqli_num_rows($cart_query) < 1) {
        sendMail('Paystack Webhook: Cart not found', 'Cart data not found for ref ' . $tx_ref, 'webhook@nivasity.com');
        continue;
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
    $gatewayName = $gateway->getGatewayName();
    $calc = calculateGatewayCharges($total_amount, $gatewayName);
    $charge = $calc['charge'];
    $profit = $calc['profit'];
    $total_amount = $calc['total_amount'];

    try {
        mysqli_begin_transaction($conn);
        $refund_applied = consumeReservationsCore($conn, $tx_ref);
        $existingTx = mysqli_query($conn, "SELECT id FROM transactions WHERE ref_id = '$tx_ref' ORDER BY id DESC LIMIT 1");
        if ($existingTx && mysqli_num_rows($existingTx) > 0) {
            $updateTxSql = "UPDATE transactions
                            SET user_id = $user_id, amount = $total_amount, charge = $charge, profit = $profit, refund = $refund_applied, status = 'successful', medium = 'PAYSTACK', payment_channel = 'gateway', transaction_context = 'purchase'
                            WHERE ref_id = '$tx_ref'";
            if (!mysqli_query($conn, $updateTxSql)) {
                throw new Exception('Failed to repair transaction: ' . mysqli_error($conn));
            }
        } else {
            $insertTxSql = "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, refund, status, medium, payment_channel, transaction_context) VALUES ('$tx_ref', $user_id, $total_amount, $charge, $profit, $refund_applied, 'successful', 'PAYSTACK', 'gateway', 'purchase')";
            if (!mysqli_query($conn, $insertTxSql)) {
                throw new Exception('Failed to record transaction: ' . mysqli_error($conn));
            }
        }
        nivasityRecordSchoolPayable($conn, [
            'school_id' => $school,
            'source_ref_id' => $tx_ref,
            'payer_user_id' => $user_id,
            'source_medium' => 'PAYSTACK',
            'source_channel' => 'webhook',
            'item_subtotal' => $total_amount - $charge,
            'collected_total' => $total_amount,
            'charge_amount' => $charge,
            'refund_amount' => $refund_applied,
            'metadata' => [
                'handler' => 'model/handle-ps-webhook.php',
                'already_repaired_tx' => $existingTx && mysqli_num_rows($existingTx) > 0,
            ],
        ]);
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        sendMail('Paystack Webhook: Transaction Error', $e->getMessage() . ' Ref: ' . $tx_ref, 'webhook@nivasity.com');
        continue;
    }
    
    sendCongratulatoryEmail($conn, $user_id, $tx_ref, $manual_ids, $event_ids, $total_amount);
    
    // Send push notification to user
    notifyUser($conn, $user_id, 
        'Payment Successful', 
        "Your payment of ₦" . number_format($total_amount, 2) . " has been confirmed.", 
        'payment', 
        ['action' => 'order_receipt', 'tx_ref' => $tx_ref, 'amount' => $total_amount, 'status' => 'successful']
    );
    
    mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref'");
    sendMail('Paystack Webhook: Success', 'Processed ref ' . $tx_ref . ' for user ' . $user_id . ' amount NGN ' . number_format($total_amount, 2), 'webhook@nivasity.com');
}

// All done - response already sent
?>
