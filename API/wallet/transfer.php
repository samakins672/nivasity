<?php
// Transfer wallet funds to another student in the same school.
// Uses the same service as the website: PIN check, same-school recipient,
// idempotent request_token, wallet_transfers record and ledger entries.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);
$userId = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = strtolower(trim((string)($input['action'] ?? 'transfer')));
// recipient_email is accepted for older clients; matric number or email both work.
$recipientIdentifier = trim((string)($input['recipient_identifier'] ?? ($input['recipient_email'] ?? '')));

try {
    if ($action === 'lookup') {
        if ($recipientIdentifier === '') {
            sendApiError('Enter the recipient email or matric number', 400);
        }

        $senderWallet = nivasityGetUserWallet($conn, $userId);
        if (!$senderWallet || (int)($senderWallet['id'] ?? 0) <= 0) {
            throw new Exception('Create your wallet before transferring funds');
        }

        $recipient = nivasityResolveStudentWalletTransferRecipient(
            $conn,
            (int)($senderWallet['school_id'] ?? 0),
            $recipientIdentifier,
            $userId
        );

        sendApiSuccess('Recipient found', [
            'recipient' => [
                'user_id' => (int)($recipient['user_id'] ?? 0),
                'name' => (string)($recipient['display_name'] ?? ''),
                'email' => (string)($recipient['email'] ?? ''),
                'matric_no' => (string)($recipient['matric_no'] ?? ''),
            ],
        ]);
    }

    if ($action !== 'transfer') {
        sendApiError('Unknown wallet transfer action', 400);
    }

    if ($recipientIdentifier === '') {
        sendApiError('Enter the recipient email or matric number', 400);
    }
    validateRequiredFields(['amount', 'wallet_pin'], $input);

    // Clients that predate request_token (e.g. the marketplace transfer sheet) get a server-made
    // one: the transfer still works, but only clients that send their own token are protected
    // against double sends on retry.
    if (trim((string)($input['request_token'] ?? '')) === '') {
        $input['request_token'] = 'api-' . bin2hex(random_bytes(16));
    }

    try {
        nivasitySyncWalletFundingFromPaystack($conn, $userId, 'api_wallet_transfer');
    } catch (Throwable $syncError) {
        error_log('[NIVASITY_WALLET_TRANSFER_SYNC] ' . $syncError->getMessage());
    }

    $result = nivasityTransferWalletToStudent(
        $conn,
        $userId,
        $recipientIdentifier,
        (int)round((float)$input['amount']),
        trim((string)$input['wallet_pin']),
        trim((string)($input['description'] ?? '')),
        trim((string)$input['request_token']),
        'api'
    );

    sendApiSuccess(
        !empty($result['already_processed'])
            ? 'This wallet transfer was already completed.'
            : 'Wallet transfer completed successfully.',
        [
            'transfer' => $result,
            'reference' => (string)($result['transfer_reference'] ?? ''),
            'new_balance' => (int)($result['wallet_balance_after'] ?? 0),
        ]
    );
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}
