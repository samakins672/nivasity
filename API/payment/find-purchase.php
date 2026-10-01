<?php
// API: "Missing a purchase?" self-check. GET ?reference=<payment or bank transfer reference>
// Tells the student where a payment went without contacting support:
//   in_your_orders     the purchase is on this account
//   pending_payment    a gateway payment on this account that was never confirmed (client verifies it)
//   other_account      on another account of the same person (same name or matric): hint = partly hidden email
//   other_student      on someone else's account (no details shared)
//   wallet_funding     a bank transfer into this account's wallet (status tells if it was credited)
//   not_found          nothing matches; contact support with the receipt
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];
$school_id = (int)$user['school'];

$reference = trim((string)($_GET['reference'] ?? ''));
// References are short tokens; strip spaces people paste from bank apps.
$reference = preg_replace('/\s+/', '', $reference);
if ($reference === '' || strlen($reference) > 120) {
    sendApiError('Enter the payment reference from your receipt', 400);
}
$ref_sql = mysqli_real_escape_string($conn, $reference);

$normalize_name = function ($value) {
    return preg_replace('/[^a-z]/', '', strtolower((string)$value));
};

// Same person = same first+last name (either order) or same matric number.
$is_same_person = function (array $other) use ($user, $normalize_name) {
    $mine = [$normalize_name($user['first_name'] ?? ''), $normalize_name($user['last_name'] ?? '')];
    $theirs = [$normalize_name($other['first_name'] ?? ''), $normalize_name($other['last_name'] ?? '')];
    $names_match = $mine[0] !== '' && $mine[1] !== ''
        && (($mine[0] === $theirs[0] && $mine[1] === $theirs[1]) || ($mine[0] === $theirs[1] && $mine[1] === $theirs[0]));
    $my_matric = strtolower(trim((string)($user['matric_no'] ?? '')));
    $matric_match = $my_matric !== '' && $my_matric === strtolower(trim((string)($other['matric_no'] ?? '')));
    return $names_match || $matric_match;
};

$other_account_response = function ($other_user_id, $what) use ($conn, $is_same_person, $school_id) {
    $other_user_id = (int)$other_user_id;
    $rs = mysqli_query($conn, "SELECT id, first_name, last_name, matric_no, email, school FROM users WHERE id = $other_user_id LIMIT 1");
    $other = $rs ? mysqli_fetch_assoc($rs) : null;
    if ($other && (int)$other['school'] === $school_id && $is_same_person($other)) {
        $hint = maskEmailForHint($other['email']);
        sendApiSuccess("This $what is on your other Nivasity account ($hint). Sign in to that account to see it. Forgot its password? Use \"Forgot password\".", [
            'status' => 'other_account',
            'existing_account_hint' => $hint,
        ]);
    }
    sendApiSuccess("This reference belongs to a different student's account. If you paid it, contact support with your receipt.", [
        'status' => 'other_student',
    ]);
};

// 1. Material purchase on this account
$rs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM manuals_bought WHERE ref_id = '$ref_sql' AND buyer = $user_id AND status = 'successful'");
if ($rs && (int)mysqli_fetch_assoc($rs)['c'] > 0) {
    sendApiSuccess('This purchase is in your orders.', ['status' => 'in_your_orders', 'ref_id' => $reference]);
}

// 2. Gateway payment started on this account but never confirmed
$rs = mysqli_query($conn, "SELECT status FROM cart WHERE ref_id = '$ref_sql' AND user_id = $user_id LIMIT 1");
if ($rs && mysqli_num_rows($rs) > 0) {
    sendApiSuccess('We found this payment on your account. Checking it with the payment provider now.', [
        'status' => 'pending_payment',
        'ref_id' => $reference,
    ]);
}

// 3. Material purchase (or unconfirmed payment) on another account
$rs = mysqli_query($conn, "SELECT buyer FROM manuals_bought WHERE ref_id = '$ref_sql' AND status = 'successful' LIMIT 1");
if ($rs && ($row = mysqli_fetch_assoc($rs))) {
    $other_account_response($row['buyer'], 'purchase');
}
$rs = mysqli_query($conn, "SELECT user_id FROM cart WHERE ref_id = '$ref_sql' LIMIT 1");
if ($rs && ($row = mysqli_fetch_assoc($rs))) {
    $other_account_response($row['user_id'], 'payment');
}

// 4. Bank transfer into a wallet (provider reference, or the bank's session reference inside the payload)
$funding_rs = mysqli_query($conn, "SELECT user_id, amount, status FROM wallet_funding_transactions
    WHERE provider_reference = '$ref_sql' OR provider_transaction_id = '$ref_sql' LIMIT 1");
$funding = $funding_rs ? mysqli_fetch_assoc($funding_rs) : null;
if (!$funding && strlen($reference) >= 12) {
    $like = mysqli_real_escape_string($conn, addcslashes($reference, '%_\\'));
    $funding_rs = mysqli_query($conn, "SELECT user_id, amount, status FROM wallet_funding_transactions
        WHERE raw_payload LIKE '%$like%' ORDER BY id DESC LIMIT 1");
    $funding = $funding_rs ? mysqli_fetch_assoc($funding_rs) : null;
}
if ($funding) {
    if ((int)$funding['user_id'] === $user_id) {
        $credited = $funding['status'] === 'posted';
        sendApiSuccess(
            $credited
                ? 'This bank transfer was credited to your wallet (₦' . number_format((int)$funding['amount']) . ').'
                : 'We received this bank transfer but it has not been credited yet. Tap "Refresh balance" on your wallet, or contact support if it stays this way.',
            ['status' => 'wallet_funding', 'credited' => $credited, 'amount' => (int)$funding['amount']]
        );
    }
    $other_account_response($funding['user_id'], 'bank transfer');
}

sendApiSuccess("We couldn't find this reference yet. If you paid by bank transfer, tap \"Refresh balance\" on your wallet and try again. Otherwise contact support with your receipt.", [
    'status' => 'not_found',
]);
