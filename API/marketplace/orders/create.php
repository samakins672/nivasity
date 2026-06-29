<?php
// API: Create marketplace order + process payment
// Supports payment_channel: "wallet" (deduct immediately) | "gateway" (Paystack redirect)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/marketplace_helpers.php';
require_once __DIR__ . '/../../../model/internal_wallet_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id   = (int)$user['id'];
$school_id = (int)$user['school'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

validateRequiredFields(['listing_id'], $input);

$listing_id      = (int)$input['listing_id'];
$payment_channel = strtolower(trim($input['payment_channel'] ?? 'gateway'));
$payment_channel = in_array($payment_channel, ['wallet','gateway'], true) ? $payment_channel : 'gateway';
$wallet_pin      = trim((string)($input['wallet_pin'] ?? ''));

// Load listing
$listing_q = mysqli_query($conn, "SELECT * FROM marketplace_listings WHERE id = $listing_id AND status = 'active' LIMIT 1");
if (!$listing_q || mysqli_num_rows($listing_q) === 0) {
    sendApiError('Listing not found or no longer available', 404);
}
$listing = mysqli_fetch_assoc($listing_q);

// Prevent self-purchase
if ((int)$listing['seller_id'] === $user_id) {
    sendApiError('You cannot buy your own listing', 400);
}

$amount       = (float)$listing['price'];
$product_type = $listing['product_type'];
$is_free      = (bool)$listing['is_free'];

// Free items require no payment
if ($is_free || $product_type === 'free') {
    $amount = 0;
    $payment_channel = 'free';
}

// Build initial timeline
$now_str  = date('Y-m-d H:i:s');
$timeline = json_encode([
    ['status' => 'created', 'label' => 'Order placed', 'timestamp' => $now_str],
]);
$timeline_safe   = mysqli_real_escape_string($conn, $timeline);
$listing_title   = mysqli_real_escape_string($conn, $listing['title']);
$product_type_s  = mysqli_real_escape_string($conn, $product_type);
$seller_id       = (int)$listing['seller_id'];

// ── Wallet payment ────────────────────────────────────────────────────────────
if ($payment_channel === 'wallet' && $amount > 0) {
    // Verify wallet PIN
    try {
        nivasityVerifyWalletPin($conn, $user_id, $wallet_pin);
    } catch (Throwable $e) {
        sendApiError($e->getMessage(), 422);
    }

    mysqli_begin_transaction($conn);
    try {
        // Lock buyer wallet
        $wallet_q = mysqli_query($conn, "SELECT id, balance FROM user_wallets WHERE user_id = $user_id LIMIT 1 FOR UPDATE");
        if (!$wallet_q || mysqli_num_rows($wallet_q) === 0) {
            throw new Exception('Create your Nivasity Wallet before paying with wallet');
        }
        $wallet     = mysqli_fetch_assoc($wallet_q);
        $wallet_id  = (int)$wallet['id'];
        $bal_before = (int)$wallet['balance'];
        $amount_int = (int)round($amount);

        if ($bal_before < $amount_int) {
            throw new Exception('Insufficient wallet balance');
        }

        $bal_after = $bal_before - $amount_int;

        // Create order
        $ins = mysqli_query($conn, "
            INSERT INTO marketplace_orders
                (listing_id, listing_title, product_type, buyer_id, seller_id, amount, status, escrow_locked, timeline)
            VALUES
                ($listing_id, '$listing_title', '$product_type_s', $user_id, $seller_id, $amount, 'pending', 1, '$timeline_safe')
        ");
        if (!$ins || mysqli_affected_rows($conn) === 0) {
            throw new Exception('Failed to create order');
        }
        $order_id = mysqli_insert_id($conn);

        // Debit buyer wallet
        $ref       = mysqli_real_escape_string($conn, 'marketplace_order_' . $order_id);
        $desc      = mysqli_real_escape_string($conn, 'Marketplace purchase — order #' . $order_id);
        mysqli_query($conn, "UPDATE user_wallets SET balance = $bal_after, updated_at = NOW() WHERE id = $wallet_id");
        mysqli_query($conn, "
            INSERT INTO wallet_ledger_entries (wallet_id, entry_type, amount, balance_before, balance_after, status, reference, description)
            VALUES ($wallet_id, 'debit', $amount_int, $bal_before, $bal_after, 'posted', '$ref', '$desc')
        ");

        mysqli_commit($conn);

        // Add escrow timeline event
        marketplaceAddOrderTimeline($conn, $order_id, 'escrow_locked', 'Payment locked in escrow');

        $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id LIMIT 1"));
        sendApiSuccess('Order created successfully', [
            'order' => marketplaceFormatOrder($conn, $row),
            'payment_url' => null,
        ], 201);

    } catch (Throwable $e) {
        mysqli_rollback($conn);
        sendApiError($e->getMessage(), 422);
    }

// ── Free item collection ──────────────────────────────────────────────────────
} elseif ($payment_channel === 'free' || $amount == 0) {
    $ins = mysqli_query($conn, "
        INSERT INTO marketplace_orders
            (listing_id, listing_title, product_type, buyer_id, seller_id, amount, status, escrow_locked, timeline)
        VALUES
            ($listing_id, '$listing_title', '$product_type_s', $user_id, $seller_id, 0, 'pending', 0, '$timeline_safe')
    ");
    if (!$ins || mysqli_affected_rows($conn) === 0) sendApiError('Failed to create order', 500);
    $order_id = mysqli_insert_id($conn);

    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id LIMIT 1"));
    sendApiSuccess('Collection request created', [
        'order'       => marketplaceFormatOrder($conn, $row),
        'payment_url' => null,
    ], 201);

// ── Paystack gateway ──────────────────────────────────────────────────────────
} else {
    // Create a pending order first, then initialise Paystack
    $ins = mysqli_query($conn, "
        INSERT INTO marketplace_orders
            (listing_id, listing_title, product_type, buyer_id, seller_id, amount, status, escrow_locked, timeline)
        VALUES
            ($listing_id, '$listing_title', '$product_type_s', $user_id, $seller_id, $amount, 'pending', 0, '$timeline_safe')
    ");
    if (!$ins || mysqli_affected_rows($conn) === 0) sendApiError('Failed to create order', 500);
    $order_id = mysqli_insert_id($conn);

    // Build Paystack init
    $amount_kobo   = (int)round($amount * 100);
    $tx_ref        = 'mp_order_' . $order_id . '_' . time();
    $email         = $user['email'];
    $callback_url  = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'api.nivasity.com') . '/marketplace/orders/payment-callback.php';

    $paystack_secret = defined('PAYSTACK_SECRET_KEY') ? PAYSTACK_SECRET_KEY : (getenv('PAYSTACK_SECRET_KEY') ?: '');
    if (empty($paystack_secret)) {
        sendApiError('Payment gateway not configured', 500);
    }

    $payload = json_encode([
        'email'        => $email,
        'amount'       => $amount_kobo,
        'reference'    => $tx_ref,
        'callback_url' => $callback_url,
        'metadata'     => ['order_id' => $order_id, 'type' => 'marketplace'],
    ]);

    $ch = curl_init('https://api.paystack.co/transaction/initialize');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $paystack_secret,
            'Content-Type: application/json',
        ],
    ]);
    $response_raw = curl_exec($ch);
    curl_close($ch);

    $response = json_decode($response_raw, true);
    if (!$response || !$response['status']) {
        sendApiError('Failed to initialise payment gateway. Please try again.', 500);
    }

    sendApiSuccess('Payment initialised', [
        'order'       => marketplaceFormatOrder($conn, mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id LIMIT 1"))),
        'payment_url' => $response['data']['authorization_url'] ?? null,
        'reference'   => $tx_ref,
    ], 201);
}
?>
