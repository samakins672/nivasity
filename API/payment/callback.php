<?php
// API: Payment Gateway Callback (Unauthenticated)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../model/functions.php';
require_once __DIR__ . '/../../model/refund_engine.php';
require_once __DIR__ . '/../../model/mail.php';
require_once __DIR__ . '/../../model/notifications.php';
require_once __DIR__ . '/../../model/payment_verifier.php';
require_once __DIR__ . '/../../config/fw.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

if (!isset($_GET['tx_ref'])) {
    sendApiError('Transaction reference is required', 400);
}

$tx_ref = sanitizeInput($conn, $_GET['tx_ref']);
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
$gateway_slug = isset($cart_row['gateway']) && !empty($cart_row['gateway']) ? strtolower($cart_row['gateway']) : 'flutterwave';

try {
    $gateway = PaymentGatewayFactory::getGateway($gateway_slug);
} catch (Exception $e) {
    try {
        $gateway = PaymentGatewayFactory::getActiveGateway();
    } catch (Exception $e2) {
        sendApiError('Payment gateway configuration error', 500);
    }
}

$verifyResult = $gateway->verifyTransaction($tx_ref);
if (!$verifyResult['status']) {
    releaseReservationsForTx($conn, $tx_ref, 'verification_failed');
    sendApiError('Payment verification failed', 400);
}

$redirect_url = null;
if (isset($verifyResult['data']['metadata']['redirect_url'])) {
    $redirect_url = $verifyResult['data']['metadata']['redirect_url'];
} elseif (isset($verifyResult['data']['meta']['redirect_url'])) {
    $redirect_url = $verifyResult['data']['meta']['redirect_url'];
}

try {
    $processResult = withTxProcessingLock($conn, $tx_ref, function() use ($conn, $tx_ref, $user_id, $school_id, $gateway_slug, $verifyResult) {
        return paymentVerifyAndFulfill(
            $conn,
            $tx_ref,
            $user_id,
            $school_id,
            $gateway_slug,
            $verifyResult['data'] ?? [],
            [
                'send_email' => true,
                'send_notification' => true,
                'clear_session' => true,
                'notify_status' => 'success'
            ]
        );
    });
} catch (Exception $e) {
    sendApiError('Payment is currently being processed. Please retry shortly.', 409);
}

if (isset($processResult['status']) && $processResult['status'] === 'error') {
    sendApiError($processResult['message'] ?? 'Payment fulfillment failed', 422);
}

$amount = (float)($processResult['amount'] ?? 0);
$date = (string)($processResult['processed_at'] ?? date('Y-m-d H:i:s'));
$refund_applied = (int)($processResult['refund_applied'] ?? 0);
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
$date_ts = strtotime($date);
$date_formatted = $date_ts ? date('jS F, Y', $date_ts) : date('jS F, Y');
$payer_name_with_matric = $payer_name . ' (Matric No.: ' . $matric_no . ')';

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

sendApiSuccess($processResult['already_processed'] ? 'Payment already processed' : 'Payment verified and processed successfully', [
    'status' => 'success',
    'tx_ref' => $tx_ref,
    'amount' => $amount,
    'refund_applied' => $refund_applied,
    'processed_at' => $date,
    'date_formatted' => $date_formatted,
    'payer_name' => $payer_name,
    'matric_no' => $matric_no,
    'payer_name_with_matric' => $payer_name_with_matric
]);
?>
