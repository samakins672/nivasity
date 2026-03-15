<?php
// API: Bulk Verify Pending Cart Payments

$isCli = (PHP_SAPI === 'cli');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../config/fw.php';
require_once __DIR__ . '/../../model/mail.php';
require_once __DIR__ . '/../../model/refund_engine.php';
require_once __DIR__ . '/../../model/functions.php';
require_once __DIR__ . '/../../model/notifications.php';
require_once __DIR__ . '/../../model/payment_verifier.php';

$logFile = __DIR__ . '/verify-bulk-cron.log';

function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

if (!$isCli && $_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user_id = 0;
$date_from = '';
$date_to = '';
$ref_id = '';
$limit = 0;
$dry_run = false;

if ($isCli) {
    global $argc, $argv;
    for ($i = 1; $i < $argc; $i++) {
        $arg = $argv[$i];
        if (strpos($arg, '--limit=') === 0) {
            $limit = (int)substr($arg, 8);
        } elseif (strpos($arg, '--dry-run=') === 0) {
            $dry_run = in_array(strtolower(substr($arg, 10)), ['1', 'true'], true);
        } elseif (strpos($arg, '--user_id=') === 0) {
            $user_id = (int)substr($arg, 10);
        } elseif (strpos($arg, '--date_from=') === 0) {
            $date_from = substr($arg, 12);
        } elseif (strpos($arg, '--date_to=') === 0) {
            $date_to = substr($arg, 10);
        } elseif (strpos($arg, '--ref_id=') === 0) {
            $ref_id = substr($arg, 9);
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $date_from = isset($_POST['date_from']) ? sanitizeInput($conn, $_POST['date_from']) : '';
    $date_to = isset($_POST['date_to']) ? sanitizeInput($conn, $_POST['date_to']) : '';
    $ref_id = isset($_POST['ref_id']) ? sanitizeInput($conn, $_POST['ref_id']) : '';
}

$where = ["status = 'pending'"];
if ($ref_id !== '') {
    $where[] = "ref_id = '" . mysqli_real_escape_string($conn, $ref_id) . "'";
} else {
    if ($user_id > 0) {
        $where[] = "user_id = $user_id";
    }
    if ($date_from !== '' && $date_to !== '') {
        $where[] = "created_at >= '" . mysqli_real_escape_string($conn, $date_from) . " 00:00:00'";
        $where[] = "created_at <= '" . mysqli_real_escape_string($conn, $date_to) . " 23:59:59'";
    } elseif ($date_from === '' && $date_to === '') {
        $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $where[] = "created_at <= DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
    } elseif ($date_from !== '') {
        $where[] = "created_at >= '" . mysqli_real_escape_string($conn, $date_from) . " 00:00:00'";
    } elseif ($date_to !== '') {
        $where[] = "created_at <= '" . mysqli_real_escape_string($conn, $date_to) . " 23:59:59'";
    }
}

if (!$dry_run) {
    releaseExpiredReservations($conn, 60);
}

$limit_sql = ($isCli && $limit > 0) ? " LIMIT $limit" : '';
$where_sql = implode(' AND ', $where);
$cart_query = mysqli_query(
    $conn,
    "SELECT ref_id, MAX(gateway) AS gateway, MAX(user_id) AS user_id, MIN(created_at) AS first_created_at
     FROM cart
     WHERE $where_sql
     GROUP BY ref_id
     ORDER BY first_created_at DESC" . $limit_sql
);

if (!$cart_query) {
    $error_msg = 'Database query error: ' . mysqli_error($conn);
    if ($isCli) {
        logMessage('SUMMARY ' . json_encode(['error' => $error_msg], JSON_UNESCAPED_SLASHES), $logFile);
        echo "ERROR: $error_msg\n";
        exit(1);
    }
    sendApiError($error_msg, 500);
}

$results = [];
$summary = [
    'total_refs_checked' => mysqli_num_rows($cart_query),
    'verified' => 0,
    'already_processed' => 0,
    'failed' => 0,
    'failed_not_found' => 0,
    'failed_errors' => 0
];

while ($cart_row = mysqli_fetch_assoc($cart_query)) {
    $current_ref = $cart_row['ref_id'];
    $cart_gateway = strtolower((string)($cart_row['gateway'] ?? 'flutterwave'));
    $cart_user_id = (int)($cart_row['user_id'] ?? 0);

    $result = [
        'ref_id' => $current_ref,
        'user_id' => $cart_user_id,
        'gateway' => strtoupper($cart_gateway),
        'status' => 'pending',
        'message' => ''
    ];

    if ($isCli) {
        echo "Processing ref_id: $current_ref (User: $cart_user_id, Gateway: $cart_gateway)...\n";
    }

    $user_query = mysqli_query($conn, "SELECT school FROM users WHERE id = $cart_user_id LIMIT 1");
    if (!$user_query || mysqli_num_rows($user_query) < 1) {
        $result['status'] = 'error';
        $result['message'] = 'User not found';
        $summary['failed']++;
        $summary['failed_errors']++;
        $results[] = $result;
        continue;
    }
    $school_id = (int)mysqli_fetch_assoc($user_query)['school'];

    try {
        $gateway = PaymentGatewayFactory::getGateway($cart_gateway);
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['message'] = 'Gateway configuration error: ' . $e->getMessage();
        $summary['failed']++;
        $summary['failed_errors']++;
        $results[] = $result;
        continue;
    }

    try {
        $verificationResult = $gateway->verifyTransaction($current_ref);
    } catch (Exception $e) {
        $verificationResult = ['status' => false, 'message' => $e->getMessage()];
    }

    if (!$verificationResult['status']) {
        $result['status'] = 'not_found';
        $result['message'] = $verificationResult['message'] ?? 'No successful payment found';
        $summary['failed']++;
        $summary['failed_not_found']++;
        $results[] = $result;
        continue;
    }

    if ($dry_run) {
        $result['status'] = 'verified';
        $result['message'] = 'Payment verified (DRY RUN)';
        $summary['verified']++;
        $results[] = $result;
        continue;
    }

    try {
        $processResult = withTxProcessingLock($conn, $current_ref, function() use ($conn, $current_ref, $cart_user_id, $school_id, $cart_gateway, $verificationResult) {
            return paymentVerifyAndFulfill(
                $conn,
                $current_ref,
                $cart_user_id,
                $school_id,
                $cart_gateway,
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
        $processResult = ['status' => 'error', 'message' => 'Payment is currently being processed. Please retry shortly.'];
    }

    $result['status'] = $processResult['status'] ?? 'error';
    $result['message'] = $processResult['message'] ?? 'Verification failed';
    $result['refund_applied'] = (int)($processResult['refund_applied'] ?? 0);
    if ($result['status'] === 'success') {
        if (!empty($processResult['already_processed'])) {
            $summary['already_processed']++;
        } else {
            $summary['verified']++;
        }
    } else {
        $summary['failed']++;
        $summary['failed_errors']++;
    }
    $results[] = $result;
}

if ($isCli) {
    echo "SUMMARY:\n";
    foreach ($summary as $key => $value) {
        echo "  $key: $value\n";
    }
    logMessage('SUMMARY ' . json_encode($summary, JSON_UNESCAPED_SLASHES), $logFile);
    exit($summary['failed'] > 0 && $summary['verified'] === 0 && $summary['already_processed'] === 0 ? 1 : 0);
}

sendApiSuccess('Bulk verification completed', [
    'summary' => $summary,
    'results' => $results
]);
?>
