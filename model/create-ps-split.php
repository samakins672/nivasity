<?php
/**
 * Create or reuse a Paystack flat split_code for multi-seller payouts.
 * Idempotent: caches by sorted seller totals hash to avoid excess splits.
 */
session_start();
require_once 'config.php';
require_once 'payment_freeze.php';
require_once __DIR__ . '/../config/fw.php';

header('Content-Type: application/json');

// Check if payments are frozen
if (is_payment_frozen()) {
    $freeze_info = get_payment_freeze_info();
    $message = ($freeze_info && isset($freeze_info['message'])) 
        ? $freeze_info['message'] 
        : 'Payments are currently paused. Please try again later.';
    echo json_encode([
        'status' => 'error', 
        'message' => $message
    ]);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload || !isset($payload['sellers']) || !is_array($payload['sellers'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$sellers = $payload['sellers'];
$amountKobo = isset($payload['amount_kobo']) ? (int)$payload['amount_kobo'] : 0;
$bearerType = isset($payload['bearer_type']) ? $payload['bearer_type'] : 'account';
$bearerSubaccount = isset($payload['bearer_subaccount']) ? $payload['bearer_subaccount'] : null;

// Normalize sellers to array of ['id' => string, 'share' => int kobo]
$normalized = [];
foreach ($sellers as $sid => $total) {
    if (is_array($total) && isset($total['id']) && isset($total['total'])) {
        $sid = $total['id'];
        $total = $total['total'];
    }
    $sid = trim((string)$sid);
    $share = (int) round(((float)$total) * 100); // kobo
    if ($sid === '' || $share <= 0) { continue; }
    $normalized[] = ['subaccount' => $sid, 'share' => $share];
}

if (empty($normalized)) {
    echo json_encode(['status' => 'error', 'message' => 'No valid sellers provided']);
    exit;
}

// Ensure sum of shares does not exceed charged amount if provided
$sumShares = array_sum(array_column($normalized, 'share'));
if ($amountKobo > 0 && $sumShares > $amountKobo) {
    // Scale down proportionally to fit
    $scale = $amountKobo / $sumShares;
    foreach ($normalized as &$n) {
        $n['share'] = (int) floor($n['share'] * $scale);
    }
    unset($n);
}

// Build idempotent hash
usort($normalized, function($a, $b) { return strcmp($a['subaccount'], $b['subaccount']); });
$hash = md5(json_encode($normalized));
$cacheFile = __DIR__ . '/paystack_split_cache.json';
$cache = [];
if (file_exists($cacheFile)) {
    $cache = json_decode(file_get_contents($cacheFile), true) ?: [];
}

if (isset($cache[$hash]) && !empty($cache[$hash]['split_code'])) {
    echo json_encode(['status' => 'success', 'split_code' => $cache[$hash]['split_code'], 'cached' => true]);
    exit;
}

$postData = [
    'name' => 'Nivasity Flat Split ' . substr($hash, 0, 8),
    'type' => 'flat',
    'currency' => 'NGN',
    'subaccounts' => $normalized,
    'bearer_type' => $bearerType,
];
if (!empty($bearerSubaccount)) {
    $postData['bearer_subaccount'] = $bearerSubaccount;
}

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.paystack.co/split',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
    ],
    CURLOPT_POSTFIELDS => json_encode($postData),
]);

$response = curl_exec($curl);
$error = curl_error($curl);
curl_close($curl);

if ($error) {
    echo json_encode(['status' => 'error', 'message' => 'Connection error: ' . $error]);
    exit;
}

$data = json_decode($response, true);
if (isset($data['status']) && $data['status'] === true && isset($data['data']['split_code'])) {
    $splitCode = $data['data']['split_code'];
    $cache[$hash] = ['split_code' => $splitCode, 'created_at' => time()];
    @file_put_contents($cacheFile, json_encode($cache));
    echo json_encode(['status' => 'success', 'split_code' => $splitCode, 'cached' => false]);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Failed to create split: ' . (is_string($response) ? $response : json_encode($response))
]);
exit;
