<?php
/**
 * Unified Payment Handler
 *
 * Routes payment processing to the appropriate gateway based on configuration.
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/../config/fw.php';
require_once 'PaymentGatewayFactory.php';
include('mail.php');
include('functions.php');
require_once 'refund_engine.php';
require_once 'internal_wallet_service.php';

$statusRes = "success";
$messageRes = "Payment processed successfully!";

$user_id = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
$school_id = isset($_SESSION['nivas_userSch']) ? (int)$_SESSION['nivas_userSch'] : 0;

// Handle payment verification (callback from gateway)
if (isset($_GET['transaction_id']) || isset($_GET['reference']) || isset($_GET['tx_ref'])) {
    $tx_ref = $_GET['tx_ref'] ?? $_GET['reference'] ?? '';

    if (empty($tx_ref)) {
        header('Location: /?payment=unsuccessful');
        exit;
    }
    $tx_ref_esc = mysqli_real_escape_string($conn, $tx_ref);

    // Resolve user/school from cart when session is missing or stale.
    if ($user_id <= 0 || $school_id <= 0) {
        $owner_q = mysqli_query(
            $conn,
            "SELECT c.user_id, u.school
             FROM cart c
             JOIN users u ON u.id = c.user_id
             WHERE c.ref_id = '$tx_ref_esc'
             LIMIT 1"
        );
        if ($owner_q && mysqli_num_rows($owner_q) > 0) {
            $owner_row = mysqli_fetch_assoc($owner_q);
            if ($user_id <= 0) {
                $user_id = (int)$owner_row['user_id'];
            }
            if ($school_id <= 0) {
                $school_id = (int)$owner_row['school'];
            }
        }
    }
    if ($user_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unable to resolve cart owner for payment verification']);
        exit;
    }

    // Resolve gateway/medium from cart entry (default to FLUTTERWAVE)
    $cart_gateway_raw = 'FLUTTERWAVE';
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
        releaseReservationsForTx($conn, $tx_ref_esc, 'verification_failed');
        if (!isset($_GET['callback'])) {
            header('Location: /?payment=unsuccessful');
        } else {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Payment verification failed']);
        }
        exit;
    }

    try {
        $result = withTxProcessingLock($conn, $tx_ref, function() use ($conn, $tx_ref, $tx_ref_esc, $user_id, $school_id, $gatewayName) {
        $statusResInner = 'success';
        $messageResInner = 'Payment processed successfully!';
        $status = 'successful';

        $processed_query = mysqli_query($conn, "SELECT id, amount FROM transactions WHERE ref_id = '$tx_ref_esc' ORDER BY id DESC LIMIT 1");
        $tx_exists = $processed_query && mysqli_num_rows($processed_query) > 0;
        $tx_row = $tx_exists ? mysqli_fetch_assoc($processed_query) : null;

        $manual_count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM manuals_bought WHERE ref_id = '$tx_ref_esc' AND buyer = $user_id"));
        $event_count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM event_tickets WHERE ref_id = '$tx_ref_esc' AND buyer = $user_id"));
        $delivery_count = (int)($manual_count_row['c'] ?? 0) + (int)($event_count_row['c'] ?? 0);
        $cart_count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM cart WHERE ref_id = '$tx_ref_esc' AND user_id = $user_id"));
        $cart_count = (int)($cart_count_row['c'] ?? 0);

        // Duplicate protection: only treat as completed when all cart rows are already delivered.
        $dupe = ($delivery_count > 0) && ($cart_count <= 0 || $delivery_count >= $cart_count);
        if ($dupe) {
            mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref_esc'");
            $_SESSION["nivas_cart$user_id"] = array();
            $_SESSION["nivas_cart_event$user_id"] = array();

            $refundApplied = consumeReservationsForSettledTx($conn, $tx_ref);
            return [
                'status' => 'success',
                'message' => 'Already processed',
                'already_processed' => true,
                'total_amount' => $tx_row && isset($tx_row['amount']) ? (float)$tx_row['amount'] : 0,
                'refund_applied' => (int)$refundApplied
            ];
        }

        $cart_rows_q = mysqli_query($conn, "SELECT item_id, type FROM cart WHERE ref_id = '$tx_ref_esc' AND user_id = $user_id");
        if (!$cart_rows_q || mysqli_num_rows($cart_rows_q) < 1) {
            return [
                'status' => 'error',
                'message' => 'Cart data not found for this transaction',
                'already_processed' => false,
                'total_amount' => 0,
                'refund_applied' => 0
            ];
        }

        $sum_amount = 0.0;
        $items_processed = 0;
        $manual_ids = [];
        $event_ids = [];

        while ($cart_row = mysqli_fetch_assoc($cart_rows_q)) {
            $item_id = (int)$cart_row['item_id'];
            $type = $cart_row['type'];

            if ($type === 'manual') {
                $manual = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $item_id AND school_id = $school_id");
                if (!$manual || mysqli_num_rows($manual) < 1) {
                    return [
                        'status' => 'error',
                        'message' => 'Unable to resolve a purchased material from cart',
                        'already_processed' => false,
                        'total_amount' => 0,
                        'refund_applied' => 0
                    ];
                }

                $manual_row = mysqli_fetch_assoc($manual);
                $price = (float)$manual_row['price'];
                $seller = (int)$manual_row['user_id'];
                $sum_amount += $price;
                $manual_ids[] = $item_id;

                $exists = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$tx_ref_esc' AND manual_id = $item_id AND buyer = $user_id LIMIT 1");
                if (!$exists) {
                    return [
                        'status' => 'error',
                        'message' => 'Unable to validate purchased materials',
                        'already_processed' => false,
                        'total_amount' => 0,
                        'refund_applied' => 0
                    ];
                }
                if (mysqli_num_rows($exists) < 1) {
                    if (!mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) VALUES ($item_id, $price, $seller, $user_id, '$tx_ref_esc', '$status', $school_id)")) {
                        return [
                            'status' => 'error',
                            'message' => 'Failed to deliver purchased material',
                            'already_processed' => false,
                            'total_amount' => 0,
                            'refund_applied' => 0
                        ];
                    }
                }
                $items_processed++;
            } elseif ($type === 'event') {
                $event = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $item_id");
                if (!$event || mysqli_num_rows($event) < 1) {
                    return [
                        'status' => 'error',
                        'message' => 'Unable to resolve a purchased event from cart',
                        'already_processed' => false,
                        'total_amount' => 0,
                        'refund_applied' => 0
                    ];
                }

                $event_row = mysqli_fetch_assoc($event);
                $price = (float)$event_row['price'];
                $seller = (int)$event_row['user_id'];
                $sum_amount += $price;
                $event_ids[] = $item_id;

                $exists = mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$tx_ref_esc' AND event_id = $item_id AND buyer = $user_id LIMIT 1");
                if (!$exists) {
                    return [
                        'status' => 'error',
                        'message' => 'Unable to validate purchased event tickets',
                        'already_processed' => false,
                        'total_amount' => 0,
                        'refund_applied' => 0
                    ];
                }
                if (mysqli_num_rows($exists) < 1) {
                    if (!mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) VALUES ($item_id, $price, $seller, $user_id, '$tx_ref_esc', '$status')")) {
                        return [
                            'status' => 'error',
                            'message' => 'Failed to deliver purchased event ticket',
                            'already_processed' => false,
                            'total_amount' => 0,
                            'refund_applied' => 0
                        ];
                    }
                }
                $items_processed++;
            }
        }

        if ($items_processed < 1 || $sum_amount <= 0) {
            return [
                'status' => 'error',
                'message' => 'No cart items were fulfilled; transaction not recorded',
                'already_processed' => false,
                'total_amount' => 0,
                'refund_applied' => 0
            ];
        }

        // Calculate charges using the gateway-specific logic
        $calc = calculateGatewayCharges($sum_amount, $gatewayName);
        $charge = $calc['charge'];
        $profit = $calc['profit'];
        $total_amount = $calc['total_amount'];
        // Save refund consumption and transaction atomically.
        $medium = mysqli_real_escape_string($conn, strtoupper($gatewayName));
        $refund_applied = 0;
        mysqli_begin_transaction($conn);
        try {
            $refund_applied = consumeReservationsCore($conn, $tx_ref);
            if ($tx_exists) {
                $updateTxSql = "UPDATE transactions
                                SET user_id = $user_id, amount = $total_amount, charge = $charge, profit = $profit, refund = $refund_applied, status = '$status', medium = '$medium'
                                WHERE ref_id = '$tx_ref_esc'";
                if (!mysqli_query($conn, $updateTxSql)) {
                    throw new Exception('Failed to repair transaction: ' . mysqli_error($conn));
                }
            } else {
                $insertTxSql = "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, refund, status, medium) VALUES ('$tx_ref_esc', $user_id, $total_amount, $charge, $profit, $refund_applied, '$status', '$medium')";
                if (!mysqli_query($conn, $insertTxSql)) {
                    throw new Exception('Failed to record transaction: ' . mysqli_error($conn));
                }
            }
            nivasityRecordSchoolPayable($conn, [
                'school_id' => $school_id,
                'source_ref_id' => $tx_ref,
                'payer_user_id' => $user_id,
                'source_medium' => $medium,
                'source_channel' => 'web',
                'item_subtotal' => $sum_amount,
                'collected_total' => $total_amount,
                'charge_amount' => $charge,
                'refund_amount' => $refund_applied,
                'metadata' => [
                    'handler' => 'model/handle-payment.php',
                    'already_repaired_tx' => $tx_exists,
                ],
            ]);
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }

        // Mark cart rows as confirmed and clear sessions
        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref_esc'");
        $_SESSION["nivas_cart$user_id"] = array();
        $_SESSION["nivas_cart_event$user_id"] = array();

        // Send receipt email
        sendCongratulatoryEmail($conn, $user_id, $tx_ref, $manual_ids, $event_ids, $total_amount);

        return [
            'status' => 'success',
            'message' => $messageResInner,
            'already_processed' => false,
            'total_amount' => (float)$total_amount,
            'refund_applied' => (int)$refund_applied
        ];
        });
    } catch (Exception $e) {
        $result = [
            'status' => 'error',
            'message' => 'Payment is currently being processed. Please retry shortly.',
            'refund_applied' => 0
        ];
    }

    if (!isset($_GET['callback'])) {
        if ($result['status'] === 'success') {
            header('Location: /?payment=successful');
        } else {
            header('Location: /?payment=unsuccessful');
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $result['status'],
            'message' => $result['message'],
            'refund_applied' => isset($result['refund_applied']) ? (int)$result['refund_applied'] : 0
        ]);
    }
    exit;
}

// If no verification request, return error
header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>
