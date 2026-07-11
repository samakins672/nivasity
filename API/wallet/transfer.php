<?php
// Transfer funds between students via email
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user = authenticateApiRequest($conn);
$sender_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
validateRequiredFields(['amount', 'recipient_email'], $input);

$amount = (int)round((float)$input['amount']);
if ($amount <= 0) sendApiError('Amount must be greater than zero', 400);

$recipient_email = mysqli_real_escape_string($conn, trim($input['recipient_email']));

if (strtolower($recipient_email) === strtolower($user['email'])) {
    sendApiError('You cannot transfer money to yourself', 400);
}

mysqli_begin_transaction($conn);
try {
    // 1. Check sender wallet
    $sender_q = mysqli_query($conn, "SELECT id, balance FROM user_wallets WHERE user_id = $sender_id LIMIT 1 FOR UPDATE");
    if (!$sender_q || mysqli_num_rows($sender_q) === 0) {
        throw new Exception("Your wallet was not found.");
    }
    $sender_wallet = mysqli_fetch_assoc($sender_q);
    $sender_wallet_id = (int)$sender_wallet['id'];
    $sender_balance = (int)$sender_wallet['balance'];

    if ($sender_balance < $amount) {
        throw new Exception("Insufficient wallet balance.");
    }

    // 2. Find recipient
    $rec_user_q = mysqli_query($conn, "SELECT id FROM users WHERE email = '$recipient_email' LIMIT 1");
    if (!$rec_user_q || mysqli_num_rows($rec_user_q) === 0) {
        throw new Exception("Recipient not found.");
    }
    $recipient = mysqli_fetch_assoc($rec_user_q);
    $recipient_id = (int)$recipient['id'];

    // 3. Check recipient wallet
    $rec_wallet_q = mysqli_query($conn, "SELECT id, balance FROM user_wallets WHERE user_id = $recipient_id LIMIT 1 FOR UPDATE");
    if (!$rec_wallet_q || mysqli_num_rows($rec_wallet_q) === 0) {
        throw new Exception("Recipient does not have a provisioned wallet.");
    }
    $rec_wallet = mysqli_fetch_assoc($rec_wallet_q);
    $rec_wallet_id = (int)$rec_wallet['id'];
    $rec_balance = (int)$rec_wallet['balance'];

    $sender_bal_after = $sender_balance - $amount;
    $rec_bal_after = $rec_balance + $amount;
    $ref = 'TRX_' . time() . '_' . rand(1000, 9999);

    // 4. Update Sender
    mysqli_query($conn, "UPDATE user_wallets SET balance = $sender_bal_after, updated_at = NOW() WHERE id = $sender_wallet_id");
    $desc_s = "Transfer to $recipient_email";
    mysqli_query($conn, "INSERT INTO wallet_ledger_entries (wallet_id, entry_type, amount, balance_before, balance_after, status, reference, description)
                         VALUES ($sender_wallet_id, 'debit', $amount, $sender_balance, $sender_bal_after, 'posted', '$ref', '$desc_s')");

    // 5. Update Recipient
    mysqli_query($conn, "UPDATE user_wallets SET balance = $rec_bal_after, updated_at = NOW() WHERE id = $rec_wallet_id");
    $desc_r = "Transfer from " . $user['email'];
    mysqli_query($conn, "INSERT INTO wallet_ledger_entries (wallet_id, entry_type, amount, balance_before, balance_after, status, reference, description)
                         VALUES ($rec_wallet_id, 'credit', $amount, $rec_balance, $rec_bal_after, 'posted', '$ref', '$desc_r')");

    mysqli_commit($conn);
    sendApiSuccess('Transfer successful', ['reference' => $ref, 'new_balance' => $sender_bal_after]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    sendApiError($e->getMessage(), 400);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log("[WALLET TRANSFER] " . $e->getMessage());
    sendApiError('An unexpected error occurred', 500);
}
?>
