<?php
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'mail.php';
require_once 'notifications.php';
require_once 'refund_engine.php';
require_once 'internal_wallet_service.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$userId = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

$refId = isset($_POST['ref_id']) ? trim((string)$_POST['ref_id']) : '';
if ($refId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Transaction reference is required']);
    exit;
}

$walletPin = isset($_POST['wallet_pin']) ? trim((string)$_POST['wallet_pin']) : '';

try {
    nivasityVerifyWalletPin($conn, $userId, $walletPin);

    try {
        nivasitySyncWalletFundingFromPaystack($conn, $userId, 'web_wallet_checkout');
    } catch (Throwable $syncError) {
        error_log('[NIVASITY_WALLET_WEB_SYNC] ' . $syncError->getMessage());
    }

    $result = nivasityProcessWalletCheckout($conn, $refId, $userId, 'web', $walletPin);

    $cartManualKey = "nivas_cart$userId";
    $cartEventKey = "nivas_cart_event$userId";
    if (isset($_SESSION[$cartManualKey])) {
        $_SESSION[$cartManualKey] = array_values(array_diff($_SESSION[$cartManualKey], $result['manual_ids'] ?? []));
    }
    if (isset($_SESSION[$cartEventKey])) {
        $_SESSION[$cartEventKey] = array_values(array_diff($_SESSION[$cartEventKey], $result['event_ids'] ?? []));
    }

    if (empty($result['already_processed'])) {
        sendCongratulatoryEmail(
            $conn,
            $userId,
            $refId,
            $result['manual_ids'] ?? [],
            $result['event_ids'] ?? [],
            $result['total_amount'] ?? 0
        );

        notifyUser(
            $conn,
            $userId,
            'Wallet Payment Successful',
            'Your wallet purchase has been completed successfully.',
            'payment',
            [
                'action' => 'order_receipt',
                'tx_ref' => $refId,
                'amount' => $result['total_amount'] ?? 0,
                'payment_channel' => 'wallet',
                'status' => 'successful',
            ]
        );
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Wallet payment completed successfully',
        'data' => $result,
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}