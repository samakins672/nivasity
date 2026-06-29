<?php
// Buyer cancels an order (only allowed while status=pending and escrow is still locked)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/marketplace_helpers.php';
require_once __DIR__ . '/../../../model/internal_wallet_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
validateRequiredFields(['order_id'], $input);
$order_id = (int)$input['order_id'];

$q = mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id AND buyer_id = $user_id LIMIT 1");
if (!$q || mysqli_num_rows($q) === 0) sendApiError('Order not found', 404);

$order = mysqli_fetch_assoc($q);
if ($order['status'] !== 'pending') {
    sendApiError('Only pending orders can be cancelled', 400);
}

// Refund wallet if escrow was locked
$amount = (int)round((float)$order['amount']);
if ($order['escrow_locked'] && $amount > 0) {
    mysqli_begin_transaction($conn);
    try {
        $wallet_q = mysqli_query($conn, "SELECT id, balance FROM user_wallets WHERE user_id = $user_id LIMIT 1 FOR UPDATE");
        if ($wallet_q && mysqli_num_rows($wallet_q) > 0) {
            $wallet    = mysqli_fetch_assoc($wallet_q);
            $wallet_id = (int)$wallet['id'];
            $bal_before = (int)$wallet['balance'];
            $bal_after  = $bal_before + $amount;
            $ref   = mysqli_real_escape_string($conn, 'marketplace_refund_' . $order_id);
            $desc  = mysqli_real_escape_string($conn, 'Marketplace refund — cancelled order #' . $order_id);

            mysqli_query($conn, "UPDATE user_wallets SET balance = $bal_after, updated_at = NOW() WHERE id = $wallet_id");
            mysqli_query($conn, "INSERT INTO wallet_ledger_entries (wallet_id, entry_type, amount, balance_before, balance_after, status, reference, description)
                                 VALUES ($wallet_id, 'credit', $amount, $bal_before, $bal_after, 'posted', '$ref', '$desc')");
        }
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        sendApiError('Refund failed: ' . $e->getMessage(), 500);
    }
}

marketplaceAddOrderTimeline($conn, $order_id, 'cancelled', 'Order cancelled by buyer — funds returned');
mysqli_query($conn, "UPDATE marketplace_orders SET status = 'cancelled', escrow_locked = 0 WHERE id = $order_id");

$row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id LIMIT 1"));
sendApiSuccess('Order cancelled — funds returned to your wallet', marketplaceFormatOrder($conn, $row));
?>
