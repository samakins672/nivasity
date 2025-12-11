<?php
/**
 * Initialize Paystack transaction server-side with flat subaccount shares.
 * Expects JSON payload:
 * {
 *   "amount": 308450,           // in kobo
 *   "email": "user@example.com",
 *   "reference": "nivas_1_... ",
 *   "callback_url": "https://.../model/handle-payment.php",
 *   "subaccounts": [ { "subaccount": "ACCT_x", "share": 7000 }, ... ],
 *   "bearer_type": "account",   // optional, default account bears fees
 *   "bearer_subaccount": null   // optional
 * }
 */
session_start();
require_once 'config.php';
require_once '../config/fw.php';

header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload']);
    exit;
}

$amount = isset($payload['amount']) ? (int)$payload['amount'] : 0;
$email = isset($payload['email']) ? trim($payload['email']) : '';
$reference = isset($payload['reference']) ? trim($payload['reference']) : '';
$callbackUrl = isset($payload['callback_url']) ? trim($payload['callback_url']) : '';
$subaccounts = isset($payload['subaccounts']) && is_array($payload['subaccounts']) ? $payload['subaccounts'] : [];
$bearerType = isset($payload['bearer_type']) ? $payload['bearer_type'] : 'account';
$bearerSubaccount = isset($payload['bearer_subaccount']) ? $payload['bearer_subaccount'] : null;

if ($amount <= 0 || $email === '' || $reference === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

// Normalize subaccounts and ensure shares are integers
$normalized = [];
foreach ($subaccounts as $sa) {
    if (!isset($sa['subaccount']) || !isset($sa['share'])) { continue; }
    $sid = trim($sa['subaccount']);
    $share = (int)$sa['share'];
    if ($sid === '' || $share <= 0) { continue; }
    $normalized[] = ['subaccount' => $sid, 'share' => $share];
}

$postData = [
    'amount' => $amount,
    'email' => $email,
    'reference' => $reference,
];
if ($callbackUrl !== '') {
    $postData['callback_url'] = $callbackUrl;
}
if (!empty($normalized)) {
    $postData['subaccounts'] = $normalized;
    $postData['bearer_type'] = $bearerType;
    if (!empty($bearerSubaccount)) {
        $postData['bearer_subaccount'] = $bearerSubaccount;
    }
}

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.paystack.co/transaction/initialize',
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
if (isset($data['status']) && $data['status'] === true && isset($data['data']['authorization_url'])) {
    echo json_encode(['status' => 'success', 'authorization_url' => $data['data']['authorization_url']]);
    exit;
}

echo json_encode([
    'status' => 'error',
    'message' => 'Failed to initialize Paystack transaction: ' . (is_string($response) ? $response : json_encode($response))
]);
exit;
