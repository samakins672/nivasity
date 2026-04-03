<?php
// API: Verify Payment
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../model/functions.php';
require_once __DIR__ . '/../../model/refund_engine.php';
require_once __DIR__ . '/../../model/mail.php';
require_once __DIR__ . '/../../model/notifications.php';
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
$user_id = (int)$user['id'];

// Check if transaction exists
$tx_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$tx_ref' AND user_id = $user_id LIMIT 1");
if (!$tx_query || mysqli_num_rows($tx_query) === 0) {
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
    $processResult = withTxProcessingLock($conn, $tx_ref, function() use ($conn, $tx_ref, $user_id, $user, $gateway_slug) {
    // Treat refs with a successful transaction and confirmed cart rows as already processed.
    $processed_query = mysqli_query($conn, "SELECT id, amount, created_at FROM transactions WHERE ref_id = '$tx_ref' ORDER BY id DESC LIMIT 1");
    $tx_exists = $processed_query && mysqli_num_rows($processed_query) > 0;
    $transaction = $tx_exists ? mysqli_fetch_assoc($processed_query) : null;
    $manual_count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM manuals_bought WHERE ref_id = '$tx_ref' AND buyer = $user_id"));
    $event_count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM event_tickets WHERE ref_id = '$tx_ref' AND buyer = $user_id"));
    $delivery_count = (int)($manual_count_row['c'] ?? 0) + (int)($event_count_row['c'] ?? 0);
    $cart_count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM cart WHERE ref_id = '$tx_ref' AND user_id = $user_id"));
    $cart_count = (int)($cart_count_row['c'] ?? 0);
    $confirmed_cart_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM cart WHERE ref_id = '$tx_ref' AND user_id = $user_id AND status = 'confirmed'"));
    $confirmed_cart_count = (int)($confirmed_cart_row['c'] ?? 0);
    $is_already_processed = $tx_exists && (($delivery_count > 0 && ($cart_count <= 0 || $delivery_count >= $cart_count)) || $confirmed_cart_count > 0);

    if ($is_already_processed) {
        $refundApplied = consumeReservationsForSettledTx($conn, $tx_ref);
        return [
            'already_processed' => true,
            'tx_ref' => $tx_ref,
            'amount' => $transaction && isset($transaction['amount']) ? (float)$transaction['amount'] : 0,
            'processed_at' => $transaction && !empty($transaction['created_at']) ? $transaction['created_at'] : date('Y-m-d H:i:s'),
            'refund_applied' => (int)$refundApplied
        ];
    }

    // Calculate amount from cart items (instead of using gateway amount which is in kobo).
    $cart_items_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$tx_ref' AND user_id = $user_id");
    if (!$cart_items_query || mysqli_num_rows($cart_items_query) < 1) {
        return [
            'already_processed' => false,
            'status' => 'error',
            'message' => 'Cart data not found for transaction reference'
        ];
    }
    $amount = 0.0;

    // First pass: calculate total amount from cart items
    $cart_items = array();
    while ($item = mysqli_fetch_assoc($cart_items_query)) {
        $cart_items[] = $item; // Store for second pass

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

    if (empty($cart_items)) {
        return [
            'already_processed' => false,
            'status' => 'error',
            'message' => 'No cart items found for transaction reference'
        ];
    }

    // Second pass: process cart items - create purchase records
    $items_processed = 0;
    foreach ($cart_items as $item) {
        if ($item['type'] === 'manual') {
            $manual_id = (int)$item['item_id'];
            $manual_query = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $manual_id");

            if (!$manual_query || mysqli_num_rows($manual_query) < 1) {
                return [
                    'already_processed' => false,
                    'status' => 'error',
                    'message' => 'Unable to resolve purchased material for fulfillment'
                ];
            }

            $manual = mysqli_fetch_assoc($manual_query);
            $price = (float)$manual['price'];
            $seller_id = (int)$manual['user_id'];
            $school_id = (int)$user['school'];
            $exists = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$tx_ref' AND manual_id = $manual_id AND buyer = $user_id LIMIT 1");
            if (!$exists) {
                return [
                    'already_processed' => false,
                    'status' => 'error',
                    'message' => 'Unable to validate purchased material delivery status'
                ];
            }
            if (mysqli_num_rows($exists) < 1) {
                if (!mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, buyer, seller, ref_id, status, school_id, created_at) VALUES ($manual_id, $price, $user_id, $seller_id, '$tx_ref', 'successful', $school_id, NOW())")) {
                    return [
                        'already_processed' => false,
                        'status' => 'error',
                        'message' => 'Failed to deliver purchased material'
                    ];
                }
            }
            $items_processed++;
        } elseif ($item['type'] === 'event') {
            $event_id = (int)$item['item_id'];
            $event_query = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $event_id");

            if (!$event_query || mysqli_num_rows($event_query) < 1) {
                return [
                    'already_processed' => false,
                    'status' => 'error',
                    'message' => 'Unable to resolve purchased event for fulfillment'
                ];
            }

            $event = mysqli_fetch_assoc($event_query);
            $price = (float)$event['price'];
            $seller_id = (int)$event['user_id'];
            $exists = mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$tx_ref' AND event_id = $event_id AND buyer = $user_id LIMIT 1");
            if (!$exists) {
                return [
                    'already_processed' => false,
                    'status' => 'error',
                    'message' => 'Unable to validate purchased event delivery status'
                ];
            }
            if (mysqli_num_rows($exists) < 1) {
                if (!mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, buyer, seller, ref_id, status, created_at) VALUES ($event_id, $price, $user_id, $seller_id, '$tx_ref', 'successful', NOW())")) {
                    return [
                        'already_processed' => false,
                        'status' => 'error',
                        'message' => 'Failed to deliver purchased event ticket'
                    ];
                }
            }
            $items_processed++;
        }
    }

    if ($items_processed < 1 || $amount <= 0) {
        return [
            'already_processed' => false,
            'status' => 'error',
            'message' => 'No cart items were fulfilled; transaction not recorded'
        ];
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
        if ($tx_exists) {
            $updateTxSql = "UPDATE transactions
                            SET user_id = $user_id, amount = $total_amount, charge = $charge, profit = $profit, refund = $refund_applied, status = 'successful', medium = '$gateway_medium'
                            WHERE ref_id = '$tx_ref'";
            if (!mysqli_query($conn, $updateTxSql)) {
                throw new Exception('Failed to repair transaction: ' . mysqli_error($conn));
            }
        } else {
            $insertTxSql = "INSERT INTO transactions (user_id, ref_id, amount, charge, profit, refund, status, medium, created_at) VALUES ($user_id, '$tx_ref', $total_amount, $charge, $profit, $refund_applied, 'successful', '$gateway_medium', '$date')";
            if (!mysqli_query($conn, $insertTxSql)) {
                throw new Exception('Failed to record transaction: ' . mysqli_error($conn));
            }
        }
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
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

    // Clear session cart
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

if (isset($processResult['status']) && $processResult['status'] === 'error') {
    sendApiError($processResult['message'] ?? 'Payment fulfillment failed', 422);
}

$amount = $processResult['amount'];
$date = $processResult['processed_at'];
$refund_applied = (int)$processResult['refund_applied'];
$first_name = isset($user['first_name']) ? trim((string)$user['first_name']) : '';
$last_name = isset($user['last_name']) ? trim((string)$user['last_name']) : '';
$payer_name = trim($first_name . ' ' . $last_name);
if ($payer_name === '') {
    $payer_name = 'Customer';
}
$matric_no = isset($user['matric_no']) ? trim((string)$user['matric_no']) : '';
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

    error_log("Payment Verify: Redirecting to $redirect_target for tx_ref $tx_ref");
    header("Location: $redirect_target");
    exit;
}

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
?>
