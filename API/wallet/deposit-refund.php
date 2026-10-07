<?php
// API: Students refund their own wallet deposit back to their bank.
//   GET  -> the latest deposit and whether it can be refunded (and why not), plus recent refunds
//   POST { funding_id, reason, wallet_pin } -> refunds that deposit in full via Paystack
// Rules: only the latest completed bank deposit, only the whole amount, only if the wallet still
// holds it, no transfers to or from another student since that deposit, a reason is required,
// and the Wallet PIN. Same mechanics as cc's Student Wallets >
// Refund: the amount comes off the wallet at once and goes back on it if Paystack refuses or fails.
// Student refunds are recorded with created_by = 0 and a "[Student]" reason prefix.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';
require_once __DIR__ . '/../../model/wallet_deposit_refund_core.php';
if (file_exists(__DIR__ . '/../../config/fw.php')) {
    require_once __DIR__ . '/../../config/fw.php';
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);
$userId = (int) $user['id'];

if (!nvWalletRefundReady($conn)) {
    sendApiError('Refunds are not available right now.', 503);
}

$wallet = nivasityGetUserWallet($conn, $userId);
if (!$wallet) {
    sendApiError('You do not have a wallet yet.', 404);
}
$walletId = (int) $wallet['id'];

// The latest completed deposit, whether it can be refunded, and why not
function depositRefundState(mysqli $conn, int $walletId): array
{
    $deposit = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT * FROM wallet_funding_transactions WHERE wallet_id = $walletId AND status = 'posted'
         ORDER BY COALESCE(posted_at, created_at) DESC, id DESC LIMIT 1"
    ));
    $balance = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT balance FROM user_wallets WHERE id = $walletId"))[0] ?? 0);
    if (!$deposit) {
        return ['deposit' => null, 'balance' => $balance, 'eligible' => false, 'reason' => 'no_deposit', 'message' => 'You have no bank deposit to refund.'];
    }
    $fid = (int) $deposit['id'];
    $amount = (int) $deposit['amount'];
    $existing = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT status FROM wallet_deposit_refunds WHERE funding_id = $fid AND status <> 'failed' ORDER BY id DESC LIMIT 1"
    ));
    $public = [
        'id' => $fid,
        'amount' => $amount,
        'date' => $deposit['posted_at'] ?: $deposit['created_at'],
        'provider' => $deposit['provider'],
    ];
    if ($existing) {
        $msg = $existing['status'] === 'refunded' ? 'Your latest deposit has already been refunded.' : 'A refund of your latest deposit is already in progress.';
        return ['deposit' => $public, 'balance' => $balance, 'eligible' => false, 'reason' => 'already_refunded', 'message' => $msg];
    }
    // Money moved to or from another student after this deposit: the balance may be their money,
    // so it can't go to this student's bank (the team can still review it)
    $since = mysqli_real_escape_string($conn, (string) ($deposit['posted_at'] ?: $deposit['created_at']));
    $transfers = (int) (mysqli_fetch_row(mysqli_query(
        $conn,
        "SELECT COUNT(*) FROM wallet_ledger_entries
         WHERE wallet_id = $walletId AND created_at >= '$since'
           AND (reference LIKE 'wallet\_transfer\_in:%' OR reference LIKE 'wallet\_transfer\_out:%')"
    ))[0] ?? 0);
    if ($transfers > 0) {
        return ['deposit' => $public, 'balance' => $balance, 'eligible' => false, 'reason' => 'transfers', 'message' => 'You sent or received money from another student after this deposit, so it can\'t be refunded here. Ask Bella and the team will review it.'];
    }
    if ($deposit['provider'] !== 'paystack') {
        return ['deposit' => $public, 'balance' => $balance, 'eligible' => false, 'reason' => 'not_bank_deposit', 'message' => 'Only bank transfer deposits can be refunded here. Ask Bella for help.'];
    }
    if ($balance < $amount) {
        return [
            'deposit' => $public,
            'balance' => $balance,
            'eligible' => false,
            'reason' => 'spent',
            'message' => 'Your wallet has N' . number_format($balance) . ', less than this deposit (N' . number_format($amount) . '). Only a deposit you haven\'t spent can be refunded.',
        ];
    }
    return ['deposit' => $public, 'balance' => $balance, 'eligible' => true, 'reason' => null, 'message' => 'N' . number_format($amount) . ' can go back to the bank account it came from.'];
}

function recentRefunds(mysqli $conn, int $walletId): array
{
    $out = [];
    $rs = mysqli_query($conn, "SELECT reference, amount, status, failure_reason, created_at, completed_at FROM wallet_deposit_refunds WHERE wallet_id = $walletId ORDER BY id DESC LIMIT 5");
    while ($rs && ($r = mysqli_fetch_assoc($rs))) {
        $out[] = [
            'reference' => $r['reference'],
            'amount' => (int) $r['amount'],
            'status' => $r['status'],
            'failure_reason' => $r['failure_reason'],
            'created_at' => $r['created_at'],
            'completed_at' => $r['completed_at'],
        ];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $state = depositRefundState($conn, $walletId);
    $state['refunds'] = recentRefunds($conn, $walletId);
    sendApiSuccess('Deposit refund details', $state);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}
$fundingId = (int) ($input['funding_id'] ?? 0);
$reason = trim((string) ($input['reason'] ?? ''));
if (strlen($reason) < 5) {
    sendApiError('Tell us why you want this refund.', 400);
}

try {
    nivasityVerifyWalletPin($conn, $userId, (string) ($input['wallet_pin'] ?? ''));
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}

$secret = defined('PAYSTACK_SECRET_KEY') ? trim((string) PAYSTACK_SECRET_KEY) : '';
$reasonStored = substr('[Student] ' . $reason, 0, 250);

// 1) Take the whole deposit off the wallet and record the refund (one transaction)
mysqli_begin_transaction($conn);
try {
    $state = depositRefundState($conn, $walletId);
    if (!$state['eligible']) {
        throw new RuntimeException($state['message']);
    }
    if ((int) $state['deposit']['id'] !== $fundingId) {
        throw new RuntimeException('Only your latest deposit can be refunded. Refresh and try again.');
    }
    $funding = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM wallet_funding_transactions WHERE id = $fundingId AND wallet_id = $walletId LIMIT 1 FOR UPDATE"));
    $walletRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT balance FROM user_wallets WHERE id = $walletId LIMIT 1 FOR UPDATE"));
    $amount = (int) $funding['amount'];
    $balance = (int) $walletRow['balance'];
    $already = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM wallet_deposit_refunds WHERE funding_id = $fundingId AND status <> 'failed'"))[0] ?? 0);
    if ($already > 0) {
        throw new RuntimeException('A refund of this deposit already exists.');
    }
    if ($balance < $amount) {
        throw new RuntimeException('Your wallet no longer holds the whole deposit.');
    }

    $reference = 'WDR-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $after = $balance - $amount;
    $refEsc = mysqli_real_escape_string($conn, $reference);
    $provRef = mysqli_real_escape_string($conn, (string) $funding['provider_reference']);
    $desc = mysqli_real_escape_string($conn, 'Refund to bank of deposit ' . $funding['provider_reference']);
    $meta = mysqli_real_escape_string($conn, json_encode(['funding_id' => $fundingId, 'requested_by' => 'student', 'user_id' => $userId, 'reason' => $reason]));
    if (!mysqli_query($conn, "INSERT INTO wallet_ledger_entries (wallet_id, entry_type, amount, balance_before, balance_after, status, reference, provider_reference, description, metadata)
        VALUES ($walletId, 'debit', $amount, $balance, $after, 'pending', '$refEsc', '$provRef', '$desc', '$meta')")) {
        throw new RuntimeException('Could not record the wallet debit.');
    }
    $debitLedgerId = (int) mysqli_insert_id($conn);
    mysqli_query($conn, "UPDATE user_wallets SET balance = $after, updated_at = NOW() WHERE id = $walletId");
    $reasonEsc = mysqli_real_escape_string($conn, $reasonStored);
    if (!mysqli_query($conn, "INSERT INTO wallet_deposit_refunds (reference, funding_id, wallet_id, user_id, amount, reason, status, provider_transaction_reference, debit_ledger_id, created_by)
        VALUES ('$refEsc', $fundingId, $walletId, $userId, $amount, '$reasonEsc', 'processing', '$provRef', $debitLedgerId, 0)")) {
        throw new RuntimeException('Could not record the refund.');
    }
    $refundId = (int) mysqli_insert_id($conn);
    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    sendApiError($e->getMessage(), 422);
}

// 2) Ask Paystack to refund the deposit to the account it came from
$call = nvWalletRefundPaystack('POST', '/refund', [
    'transaction' => (string) $funding['provider_reference'],
    'amount' => $amount * 100,
    'currency' => 'NGN',
    'merchant_note' => 'Nivasity wallet refund ' . $reference . ' (student): ' . $reason,
    'customer_note' => 'Refund of your Nivasity wallet deposit',
], $secret);

if (!$call['ok']) {
    // Paystack refused: the money goes back on the wallet
    nvWalletRefundApplyProviderStatus($conn, $refundId, 'failed', ['message' => $call['error']], 'student_create');
    sendApiError('We couldn\'t start the refund (' . rtrim($call['error'], '.') . '). Your N' . number_format($amount) . ' is back in your wallet; ask Bella for help.', 502);
}

$data = is_array($call['body']['data'] ?? null) ? $call['body']['data'] : [];
if (!empty($data['id'])) {
    mysqli_query($conn, "UPDATE wallet_deposit_refunds SET provider_refund_id = '" . mysqli_real_escape_string($conn, (string) $data['id']) . "' WHERE id = $refundId");
}
$status = nvWalletRefundApplyProviderStatus($conn, $refundId, (string) ($data['status'] ?? 'pending'), $data, 'student_create');

sendApiSuccess(
    $status === 'refunded'
        ? 'Refund completed. N' . number_format($amount) . ' is on its way to your bank.'
        : 'Refund started. N' . number_format($amount) . ' is off your wallet and will reach your bank in a few hours to 3 working days.',
    ['reference' => $reference, 'amount' => $amount, 'status' => $status]
);
