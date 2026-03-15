<?php
/**
 * Interswitch Webhook Handler
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
require_once __DIR__ . '/payment_verifier.php';

try {
    $gateway = PaymentGatewayFactory::getGateway('interswitch');
} catch (Exception $e) {
    sendMail('Interswitch Webhook: Gateway Error', $e->getMessage(), 'webhook@nivasity.com');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gateway configuration error']);
    exit;
}

$tx_ref = $_GET['txnref'] ?? $_POST['txnref'] ?? '';
$responseCode = $_GET['resp'] ?? $_POST['resp'] ?? '';

if ($tx_ref === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing transaction reference']);
    exit;
}

if ($responseCode !== '00') {
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Ignored non-success webhook']);
    exit;
}

$verifyResult = $gateway->verifyTransaction($tx_ref);
if (!$verifyResult['status']) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Transaction verification failed']);
    exit;
}

$cart_query = mysqli_query($conn, "SELECT user_id FROM cart WHERE ref_id = '" . mysqli_real_escape_string($conn, $tx_ref) . "' LIMIT 1");
if (!$cart_query || mysqli_num_rows($cart_query) < 1) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Cart data not found']);
    exit;
}
$user_id = (int)mysqli_fetch_assoc($cart_query)['user_id'];
$user_query = mysqli_query($conn, "SELECT school FROM users WHERE id = $user_id LIMIT 1");
$school_id = ($user_query && mysqli_num_rows($user_query) > 0) ? (int)mysqli_fetch_assoc($user_query)['school'] : 0;

try {
    $processResult = withTxProcessingLock($conn, $tx_ref, function() use ($conn, $tx_ref, $user_id, $school_id, $verifyResult) {
        return paymentVerifyAndFulfill(
            $conn,
            $tx_ref,
            $user_id,
            $school_id,
            'interswitch',
            $verifyResult['data'] ?? [],
            [
                'send_email' => true,
                'send_notification' => true,
                'clear_session' => false,
                'notify_status' => 'successful'
            ]
        );
    });
} catch (Exception $e) {
    $processResult = ['status' => 'error', 'message' => 'Payment is currently being processed. Please retry shortly.'];
}

http_response_code(($processResult['status'] ?? 'error') === 'success' ? 200 : 422);
echo json_encode($processResult);
?>
