<?php
// Withdraw funds to bank account
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user = authenticateApiRequest($conn);
$user_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
validateRequiredFields(['amount', 'bank_code', 'account_number'], $input);

$amount = (int)round((float)$input['amount']);
if ($amount < 100) sendApiError('Minimum withdrawal amount is ₦100', 400);

$bank_code = mysqli_real_escape_string($conn, trim($input['bank_code']));
$account_number = mysqli_real_escape_string($conn, trim($input['account_number']));

mysqli_begin_transaction($conn);
try {
    $wallet_q = mysqli_query($conn, "SELECT id, balance FROM user_wallets WHERE user_id = $user_id LIMIT 1 FOR UPDATE");
    if (!$wallet_q || mysqli_num_rows($wallet_q) === 0) {
        throw new Exception("Your wallet was not found.");
    }
    
    $wallet = mysqli_fetch_assoc($wallet_q);
    $wallet_id = (int)$wallet['id'];
    $balance = (int)$wallet['balance'];

    if ($balance < $amount) {
        throw new Exception("Insufficient wallet balance for withdrawal.");
    }

    $bal_after = $balance - $amount;
    $ref = 'WD_' . time() . '_' . rand(1000, 9999);
    $desc = "Bank Withdrawal: $account_number ($bank_code)";

    mysqli_query($conn, "UPDATE user_wallets SET balance = $bal_after, updated_at = NOW() WHERE id = $wallet_id");
    
    // Status is 'pending' because a cron job or admin needs to process actual bank payout via Paystack/Flutterwave
    mysqli_query($conn, "INSERT INTO wallet_ledger_entries (wallet_id, entry_type, amount, balance_before, balance_after, status, reference, description)
                         VALUES ($wallet_id, 'debit', $amount, $balance, $bal_after, 'pending', '$ref', '$desc')");

    mysqli_commit($conn);
    sendApiSuccess('Withdrawal requested successfully', ['reference' => $ref, 'new_balance' => $bal_after]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    sendApiError($e->getMessage(), 400);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log("[WALLET WITHDRAW] " . $e->getMessage());
    sendApiError('An unexpected error occurred', 500);
}
?>
