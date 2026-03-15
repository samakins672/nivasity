<?php
/**
 * Unified Payment Handler
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/../config/fw.php';
require_once 'PaymentGatewayFactory.php';
include('mail.php');
include('functions.php');
require_once 'refund_engine.php';
require_once 'notifications.php';
require_once 'payment_verifier.php';

$statusRes = 'success';
$messageRes = 'Payment processed successfully!';

$user_id = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
$school_id = isset($_SESSION['nivas_userSch']) ? (int)$_SESSION['nivas_userSch'] : 0;

if (isset($_GET['transaction_id']) || isset($_GET['reference']) || isset($_GET['tx_ref'])) {
    $tx_ref = $_GET['tx_ref'] ?? $_GET['reference'] ?? '';

    if (empty($tx_ref)) {
        header('Location: /?payment=unsuccessful');
        exit;
    }
    $tx_ref_esc = mysqli_real_escape_string($conn, $tx_ref);

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

    $cart_gateway_raw = 'FLUTTERWAVE';
    $cart_gateway_q = mysqli_query($conn, "SELECT gateway FROM cart WHERE ref_id = '$tx_ref_esc' LIMIT 1");
    if ($cart_gateway_q && mysqli_num_rows($cart_gateway_q) > 0) {
        $cg_row = mysqli_fetch_assoc($cart_gateway_q);
        if (!empty($cg_row['gateway'])) {
            $cart_gateway_raw = $cg_row['gateway'];
        }
    }
    $resolvedGatewaySlug = strtolower($cart_gateway_raw);

    try {
        $gateway = PaymentGatewayFactory::getGateway($resolvedGatewaySlug);
        $gatewayName = $gateway->getGatewayName();
    } catch (Exception $e) {
        try {
            $gateway = PaymentGatewayFactory::getActiveGateway();
            $gatewayName = $gateway->getGatewayName();
        } catch (Exception $e2) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Payment gateway configuration error: ' . $e2->getMessage()]);
            exit;
        }
    }

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
        $result = withTxProcessingLock($conn, $tx_ref, function() use ($conn, $tx_ref, $user_id, $school_id, $gatewayName, $verifyResult) {
            return paymentVerifyAndFulfill(
                $conn,
                $tx_ref,
                $user_id,
                $school_id,
                $gatewayName,
                $verifyResult['data'] ?? [],
                [
                    'send_email' => true,
                    'send_notification' => true,
                    'clear_session' => true,
                    'notify_status' => 'successful'
                ]
            );
        });
    } catch (Exception $e) {
        $result = [
            'status' => 'error',
            'message' => 'Payment is currently being processed. Please retry shortly.',
            'refund_applied' => 0
        ];
    }

    if (!isset($_GET['callback'])) {
        header('Location: /?payment=' . (($result['status'] ?? 'error') === 'success' ? 'successful' : 'unsuccessful'));
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => $result['status'] ?? 'error',
            'message' => $result['message'] ?? 'Payment verification failed',
            'refund_applied' => isset($result['refund_applied']) ? (int)$result['refund_applied'] : 0
        ]);
    }
    exit;
}

header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
?>
