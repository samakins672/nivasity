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

$statusRes = "success";
$messageRes = "Payment processed successfully!";

$user_id = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
$school_id = isset($_SESSION['nivas_userSch']) ? (int)$_SESSION['nivas_userSch'] : 0;
$cart_ = isset($_SESSION["nivas_cart$user_id"]) ? $_SESSION["nivas_cart$user_id"] : [];
$cart_2 = isset($_SESSION["nivas_cart_event$user_id"]) ? $_SESSION["nivas_cart_event$user_id"] : [];

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

    try {
        $result = withTxProcessingLock($conn, $tx_ref, function() use ($conn, $tx_ref, $tx_ref_esc, $user_id, $school_id, $cart_, $cart_2, $gatewayName) {
        $statusResInner = 'success';
        $messageResInner = 'Payment processed successfully!';
        $status = 'successful';

        // Duplicate protection: if already processed, just confirm cart and return.
        $dupe = false;
        if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM transactions WHERE ref_id = '$tx_ref_esc' LIMIT 1")) > 0) { $dupe = true; }
        if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$tx_ref_esc' LIMIT 1")) > 0) { $dupe = true; }
        if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$tx_ref_esc' LIMIT 1")) > 0) { $dupe = true; }

        if ($dupe) {
            mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref_esc'");
            $_SESSION["nivas_cart$user_id"] = array();
            $_SESSION["nivas_cart_event$user_id"] = array();

            $tx_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT amount, refund FROM transactions WHERE ref_id = '$tx_ref_esc' LIMIT 1"));
            return [
                'status' => 'success',
                'message' => 'Already processed',
                'already_processed' => true,
                'total_amount' => $tx_row ? (float)$tx_row['amount'] : 0,
                'refund_applied' => $tx_row && isset($tx_row['refund']) ? (int)$tx_row['refund'] : 0
            ];
        }

        $total_amount = 0;

        // Process manuals in cart
        foreach ($cart_ as $manual_id) {
            $manual_id = (int)$manual_id;
            $manual = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $manual_id AND school_id = $school_id");

            if ($manual && mysqli_num_rows($manual) > 0) {
                $row = mysqli_fetch_assoc($manual);
                $price = (float)$row['price'];
                $total_amount += $price;
                $seller = (int)$row['user_id'];

                mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) VALUES ($manual_id, $price, $seller, $user_id, '$tx_ref', '$status', $school_id)");

                if (mysqli_affected_rows($conn) < 1) {
                    $statusResInner = "error";
                    $messageResInner = "Internal Server Error. Please try again later!";
                    break;
                }
            } else {
                $statusResInner = "error";
                $messageResInner = "Unable to fetch details from manuals. Please try again later!";
                break;
            }
        }

        // Process event tickets in cart
        if ($statusResInner === 'success') {
            foreach ($cart_2 as $event_id) {
                $event_id = (int)$event_id;
                $event = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $event_id");

                if ($event && mysqli_num_rows($event) > 0) {
                    $row = mysqli_fetch_assoc($event);
                    $price = (float)$row['price'];
                    $total_amount += $price;
                    $seller = (int)$row['user_id'];

                    mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) VALUES ($event_id, $price, $seller, $user_id, '$tx_ref', '$status')");

                    if (mysqli_affected_rows($conn) < 1) {
                        $statusResInner = "error";
                        $messageResInner = "Internal Server Error while adding event ticket. Please try again later!";
                        break;
                    }
                } else {
                    $statusResInner = "error";
                    $messageResInner = "Unable to fetch details from events. Please try again later!";
                    break;
                }
            }
        }

        if ($statusResInner !== 'success') {
            return [
                'status' => 'error',
                'message' => $messageResInner,
                'already_processed' => false,
                'total_amount' => 0,
                'refund_applied' => 0
            ];
        }

        // Calculate charges using the gateway-specific logic
        $calc = calculateGatewayCharges($total_amount, $gatewayName);
        $charge = $calc['charge'];
        $profit = $calc['profit'];
        $total_amount = $calc['total_amount'];
        // Save refund consumption and transaction atomically.
        $medium = mysqli_real_escape_string($conn, strtoupper($gatewayName));
        $refund_applied = 0;
        mysqli_begin_transaction($conn);
        try {
            $refund_applied = consumeReservationsCore($conn, $tx_ref);
            $insertTxSql = "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, refund, status, medium) VALUES ('$tx_ref', $user_id, $total_amount, $charge, $profit, $refund_applied, '$status', '$medium')";
            if (!mysqli_query($conn, $insertTxSql)) {
                throw new Exception('Failed to record transaction: ' . mysqli_error($conn));
            }
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }

        // Mark cart rows as confirmed and clear sessions
        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref'");
        $_SESSION["nivas_cart$user_id"] = array();
        $_SESSION["nivas_cart_event$user_id"] = array();

        // Send receipt email
        sendCongratulatoryEmail($conn, $user_id, $tx_ref, $cart_, $cart_2, $total_amount);

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
