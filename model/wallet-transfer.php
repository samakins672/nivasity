<?php
session_start();
require_once 'config.php';
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

$action = strtolower(trim((string)($_POST['action'] ?? 'transfer')));

try {
    if ($action === 'lookup') {
        $senderWallet = nivasityGetUserWallet($conn, $userId);
        if (!$senderWallet || (int)($senderWallet['id'] ?? 0) <= 0) {
            throw new Exception('Create your wallet before transferring funds');
        }

        $recipientIdentifier = trim((string)($_POST['recipient_identifier'] ?? ''));
        $recipient = nivasityResolveStudentWalletTransferRecipient(
            $conn,
            (int)($senderWallet['school_id'] ?? 0),
            $recipientIdentifier,
            $userId
        );

        echo json_encode([
            'status' => 'success',
            'message' => 'Recipient found',
            'data' => [
                'recipient' => [
                    'user_id' => (int)($recipient['user_id'] ?? 0),
                    'name' => (string)($recipient['display_name'] ?? ''),
                    'email' => (string)($recipient['email'] ?? ''),
                    'matric_no' => (string)($recipient['matric_no'] ?? ''),
                ],
            ],
        ]);
        exit;
    }

    if ($action !== 'transfer') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unknown wallet transfer action']);
        exit;
    }

    try {
        nivasitySyncWalletFundingFromPaystack($conn, $userId, 'web_wallet_transfer');
    } catch (Throwable $syncError) {
        error_log('[NIVASITY_WALLET_TRANSFER_SYNC] ' . $syncError->getMessage());
    }

    $result = nivasityTransferWalletToStudent(
        $conn,
        $userId,
        trim((string)($_POST['recipient_identifier'] ?? '')),
        (int)round((float)($_POST['amount'] ?? 0)),
        trim((string)($_POST['wallet_pin'] ?? '')),
        trim((string)($_POST['description'] ?? '')),
        trim((string)($_POST['request_token'] ?? '')),
        'web'
    );
    $dashboard = nivasityGetWalletDashboardPayload($conn, $userId, 25);

    echo json_encode([
        'status' => 'success',
        'message' => !empty($result['already_processed'])
            ? 'This wallet transfer was already completed.'
            : 'Wallet transfer completed successfully.',
        'data' => [
            'transfer' => $result,
            'dashboard' => $dashboard,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}