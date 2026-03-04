<?php
// API: Payment Gateway Callback (Unauthenticated)
// This endpoint receives callbacks from payment gateways after payment completion
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../model/functions.php';
require_once __DIR__ . '/../../model/refund_engine.php';
require_once __DIR__ . '/../../model/mail.php';
require_once __DIR__ . '/../../model/notifications.php';
require_once __DIR__ . '/../../config/fw.php';

// Only accept GET requests (most gateways use GET for callbacks)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// Get transaction reference (no authentication required for gateway callbacks)
if (!isset($_GET['tx_ref'])) {
    sendApiError('Transaction reference is required', 400);
}

$tx_ref = sanitizeInput($conn, $_GET['tx_ref']);

// Check if transaction exists in cart
$tx_query = mysqli_query($conn, "SELECT c.*, u.email, u.first_name, u.last_name, u.matric_no, u.school
                                  FROM cart c
                                  JOIN users u ON c.user_id = u.id
                                  WHERE c.ref_id = '$tx_ref' LIMIT 1");

if (!$tx_query || mysqli_num_rows($tx_query) === 0) {
    sendApiError('Transaction not found', 404);
}

$cart_row = mysqli_fetch_assoc($tx_query);
$user_id = (int)$cart_row['user_id'];
$school_id = (int)$cart_row['school'];

// Get gateway from cart
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

// Verify transaction with gateway
$verifyResult = $gateway->verifyTransaction($tx_ref);
if (!$verifyResult['status']) {
    releaseReservationsForTx($conn, $tx_ref, 'verification_failed');
    sendApiError('Payment verification failed', 400);
}

// Check for redirect_url in metadata
$redirect_url = null;
if (isset($verifyResult['data']['metadata']['redirect_url'])) {
    $redirect_url = $verifyResult['data']['metadata']['redirect_url'];
} elseif (isset($verifyResult['data']['meta']['redirect_url'])) {
    $redirect_url = $verifyResult['data']['meta']['redirect_url'];
}

$processResult = null;
try {
    $processResult = withTxProcessingLock($conn, $tx_ref, function() use ($conn, $tx_ref, $user_id, $school_id, $gateway_slug) {
    // Check if already processed
    $processed_query = mysqli_query($conn, "SELECT * FROM transactions WHERE ref_id = '$tx_ref' LIMIT 1");
    if ($processed_query && mysqli_num_rows($processed_query) > 0) {
        $transaction = mysqli_fetch_assoc($processed_query);
        $refundApplied = getConsumedReservationTotalForTx($conn, $tx_ref);
        return [
            'already_processed' => true,
            'tx_ref' => $tx_ref,
            'amount' => (float)$transaction['amount'],
            'processed_at' => $transaction['created_at'],
            'refund_applied' => (int)$refundApplied
        ];
    }

    // Calculate amount from cart items (instead of using gateway amount which is in kobo)
    $cart_items_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$tx_ref' AND user_id = $user_id");
    $amount = 0.0;

    // First pass: calculate total amount from cart items
    $cart_items = array();
    while ($item = mysqli_fetch_assoc($cart_items_query)) {
        $cart_items[] = $item;

        if ($item['type'] === 'manual') {
            $manual_id = (int)$item['item_id'];
            $manual_query = mysqli_query($conn, "SELECT price FROM manuals WHERE id = $manual_id");
            if ($manual_query && mysqli_num_rows($manual_query) > 0) {
                $manual = mysqli_fetch_assoc($manual_query);
                $amount += (float)$manual['price'];
            }
        } elseif ($item['type'] === 'event') {
            $event_id = (int)$item['item_id'];
            $event_query = mysqli_query($conn, "SELECT price FROM events WHERE id = $event_id");
            if ($event_query && mysqli_num_rows($event_query) > 0) {
                $event = mysqli_fetch_assoc($event_query);
                $amount += (float)$event['price'];
            }
        }
    }

    // Get gateway name from cart for transaction record
    $gateway_medium = strtoupper($gateway_slug);

    // Calculate charges based on actual amount and gateway
    $calc = calculateGatewayCharges($amount, $gateway_slug);
    $charge = $calc['charge'];
    $profit = $calc['profit'];
    $total_amount = isset($calc['total_amount']) ? (float)$calc['total_amount'] : ((float)$amount + (float)$charge);
    $date = date('Y-m-d H:i:s');
    $refund_applied = 0;
    mysqli_begin_transaction($conn);
    try {
        $refund_applied = consumeReservationsCore($conn, $tx_ref);
        $insertTxSql = "INSERT INTO transactions (user_id, ref_id, amount, charge, profit, refund, status, medium, created_at) VALUES ($user_id, '$tx_ref', $total_amount, $charge, $profit, 0, 'successful', '$gateway_medium', '$date')";
        if (!mysqli_query($conn, $insertTxSql)) {
            throw new Exception('Failed to record transaction: ' . mysqli_error($conn));
        }
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }

    // Second pass: process cart items - create purchase records
    foreach ($cart_items as $item) {
        if ($item['type'] === 'manual') {
            $manual_id = (int)$item['item_id'];
            $manual_query = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $manual_id");

            if ($manual_query && mysqli_num_rows($manual_query) > 0) {
                $manual = mysqli_fetch_assoc($manual_query);
                $price = $manual['price'];
                $seller_id = (int)$manual['user_id'];

                mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, buyer, seller, ref_id, status, school_id, created_at) VALUES ($manual_id, $price, $user_id, $seller_id, '$tx_ref', 'successful', $school_id, '$date')");
            }
        } elseif ($item['type'] === 'event') {
            $event_id = (int)$item['item_id'];
            $event_query = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $event_id");

            if ($event_query && mysqli_num_rows($event_query) > 0) {
                $event = mysqli_fetch_assoc($event_query);
                $price = $event['price'];
                $seller_id = (int)$event['user_id'];

                mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, buyer, seller, ref_id, status, created_at) VALUES ($event_id, $price, $user_id, $seller_id, '$tx_ref', 'successful', '$date')");
            }
        }
    }

    // Mark cart as confirmed
    mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref'");

    // Collect cart items for email
    $manual_ids = array();
    $event_ids = array();
    $cart_items_for_email = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$tx_ref' AND user_id = $user_id");
    while ($cart_item = mysqli_fetch_assoc($cart_items_for_email)) {
        if ($cart_item['type'] === 'manual') {
            $manual_ids[] = $cart_item['item_id'];
        } elseif ($cart_item['type'] === 'event') {
            $event_ids[] = $cart_item['item_id'];
        }
    }

    // Send congratulatory email
    sendCongratulatoryEmail($conn, $user_id, $tx_ref, $manual_ids, $event_ids, $total_amount);

    // Send push notification for successful payment
    notifyUser(
        $conn,
        $user_id,
        'Payment Successful',
        "Your payment of â‚¦" . number_format($total_amount, 2) . " has been confirmed.",
        'payment',
        [
            'action' => 'order_receipt',
            'tx_ref' => $tx_ref,
            'amount' => $total_amount,
            'status' => 'success'
        ]
    );

    // Clear session cart (if session exists)
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION["nivas_cart$user_id"] = array();
    $_SESSION["nivas_cart_event$user_id"] = array();

    return [
        'already_processed' => false,
        'tx_ref' => $tx_ref,
        'amount' => (float)$total_amount,
        'processed_at' => $date,
        'refund_applied' => (int)$refund_applied
    ];
    });
} catch (Exception $e) {
    sendApiError('Payment is currently being processed. Please retry shortly.', 409);
}

$amount = $processResult['amount'];
$date = $processResult['processed_at'];
$refund_applied = (int)$processResult['refund_applied'];
$first_name = isset($cart_row['first_name']) ? trim((string)$cart_row['first_name']) : '';
$last_name = isset($cart_row['last_name']) ? trim((string)$cart_row['last_name']) : '';
$payer_name = trim($first_name . ' ' . $last_name);
if ($payer_name === '') {
    $payer_name = 'Customer';
}
$matric_no = isset($cart_row['matric_no']) ? trim((string)$cart_row['matric_no']) : '';
if ($matric_no === '') {
    $matric_no = 'N/A';
}
$date_ts = strtotime((string)$date);
$date_formatted = $date_ts ? date('jS F, Y', $date_ts) : date('jS F, Y');
$payer_name_with_matric = $payer_name . ' (Matric No.: ' . $matric_no . ')';

// If redirect_url is provided, redirect to it with success parameters
if ($redirect_url) {
    $redirect_target = $redirect_url .
        (strpos($redirect_url, '?') !== false ? '&' : '?') .
        'tx_ref=' . urlencode($tx_ref) .
        '&status=success' .
        '&amount=' . urlencode($amount) .
        '&refund_applied=' . urlencode($refund_applied);

    error_log("Payment Callback: Redirecting to $redirect_target for tx_ref $tx_ref");
    header("Location: $redirect_target");
    exit;
}

// If no redirect_url, return JSON success response
sendApiSuccess($processResult['already_processed'] ? 'Payment already processed' : 'Payment verified and processed successfully', [
    'status' => 'success',
    'tx_ref' => $tx_ref,
    'amount' => (float)$amount,
    'refund_applied' => (int)$refund_applied,
    'processed_at' => $date,
    'date_formatted' => $date_formatted,
    'payer_name' => $payer_name,
    'matric_no' => $matric_no,
    'payer_name_with_matric' => $payer_name_with_matric
]);
