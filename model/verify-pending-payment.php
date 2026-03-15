<?php
/**
 * Unified Pending Payment Verification
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/../config/fw.php';
require_once 'PaymentGatewayFactory.php';
include('functions.php');
include('mail.php');
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/refund_engine.php';
require_once __DIR__ . '/payment_verifier.php';

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

if ($action === 'cancel') {
    mysqli_query($conn, "UPDATE cart SET status = 'cancelled' WHERE ref_id = '$ref_id_esc' AND user_id = $user_id AND status = 'pending'");
    releaseReservationsForTx($conn, $ref_id_esc, 'user_cancelled');
    echo json_encode(['status' => 'success', 'message' => 'Payment cancelled']);
    exit;
}

if ($action !== 'verify') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    exit;
}

try {
    $result = withTxProcessingLock($conn, $ref_id_esc, function() use ($conn, $ref_id, $user_id, $school_id) {
        $cart_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '" . mysqli_real_escape_string($conn, $ref_id) . "' AND user_id = $user_id LIMIT 1");
        if (!$cart_query || mysqli_num_rows($cart_query) < 1) {
            return ['status' => 'error', 'message' => 'Cart data not found for reference'];
        }

        $first_row = mysqli_fetch_assoc($cart_query);
        $cart_gateway = $first_row['gateway'] ?? 'FLUTTERWAVE';
        $gateway_slug = strtolower($cart_gateway);

        try {
            $gateway = PaymentGatewayFactory::getGateway($gateway_slug);
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Payment gateway configuration error'];
        }

        $verificationResult = $gateway->verifyTransaction($ref_id);
        if (!$verificationResult || !isset($verificationResult['status']) || $verificationResult['status'] !== true) {
            return ['status' => 'pending', 'message' => 'No successful payment found for ref'];
        }

        return paymentVerifyAndFulfill(
            $conn,
            $ref_id,
            $user_id,
            $school_id,
            $gateway_slug,
            $verificationResult['data'] ?? [],
            [
                'send_email' => true,
                'send_notification' => true,
                'clear_session' => true,
                'notify_status' => 'successful'
            ]
        );
    });
} catch (Exception $e) {
    $result = ['status' => 'error', 'message' => 'Payment is currently being processed. Please retry shortly.'];
}

echo json_encode($result);
?>
