<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once '../config/fw.php';
include('mail.php');
include('functions.php');

// Parse incoming webhook
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
$headers = function_exists('getallheaders') ? getallheaders() : [];
$verifHash = '';
foreach ($headers as $k => $v) { $lk = strtolower($k); if ($lk === 'verif-hash' || $lk === 'verif_hash' || $lk === 'x-flw-signature') { $verifHash = $v; break; } }

// Verify with FLW_VERIF_HASH if configured
if (defined('FLW_VERIF_HASH') && FLW_VERIF_HASH) {
  if (!$verifHash || $verifHash !== FLW_VERIF_HASH) {
    sendMail('FLW Webhook: Invalid hash', 'Hash mismatch or missing.', 'webhook@nivasity.com');
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
    exit;
  }
}
else {
  // Notify if no hash configured
  sendMail('FLW Webhook: No hash configured', 'FLW_VERIF_HASH is not defined in config.', 'webhook@nivasity.com');
}

// Function to verify the transaction via the Flutterwave API
function verifyTransaction($txRef) {
  $secretKey = FLW_SECRET_KEY;

  // API request setup
  $url = "https://api.flutterwave.com/v3/transactions?tx_ref=$txRef";
  $options = [
    "http" => [
      "header" => "Authorization: Bearer $secretKey"
    ]
  ];
  $context = stream_context_create($options);
  $response = file_get_contents($url, false, $context);
  $data = json_decode($response, true);

  if ($data && $data['status'] == 'success' && isset($data['data'][0]['status']) && $data['data'][0]['status'] == 'successful') {
    return true;
  }

  return false;
}

// Determine the tx_ref from payload if present
$overrideRef = '';
if (is_array($payload) && isset($payload['data'])) {
  $overrideRef = $payload['data']['tx_ref'] ?? ($payload['data']['txRef'] ?? '');
  $status = $payload['data']['status'] ?? '';
  if ($overrideRef && $status !== 'successful') {
    sendMail('FLW Webhook: Not successful', 'Status: ' . $status . ' Ref: ' . $overrideRef, 'webhook@nivasity.com');
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'message' => 'Ignored non-success webhook']);
    exit;
  }
}

// Retrieve the list of ref_id from the cart table (or override)
$cartItems = [];
if ($overrideRef) {
  $cartItems[] = $overrideRef;
} else {
  $query = "SELECT DISTINCT ref_id FROM cart";
  $result = mysqli_query($conn, $query);
  while ($row = mysqli_fetch_assoc($result)) { $cartItems[] = $row['ref_id']; }
}

// Iterate over each ref_id and verify the transaction
foreach ($cartItems as $refId) {
  if (verifyTransaction($refId)) {
    $tx_ref = $refId;
    // Duplicate protection
    $safe_ref = mysqli_real_escape_string($conn, $tx_ref);
    $dupe = false;
    if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM transactions WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if ($dupe) {
      mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$safe_ref'");
      sendMail('FLW Webhook: Duplicate', 'Duplicate delivery for ref ' . $tx_ref . ' acknowledged; cart marked confirmed.', 'webhook@nivasity.com');
      continue;
    }

    // Fetch data from the cart table using ref_id
    $cart_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$tx_ref'");

    if (!$cart_query || mysqli_num_rows($cart_query) < 1) {
      http_response_code(400);
      echo json_encode(['status' => 'error', 'message' => 'Cart data not found']);
      exit;
    }

    $cart_items = [];
    while ($row = mysqli_fetch_assoc($cart_query)) {
      $cart_items[] = $row;
    }

    $statusRes = "success";
    $messageRes = "All items successfully added!";

    $total_amount = 0;
    $manual_ids = [];
    $event_ids = [];

    // Process each cart item
    foreach ($cart_items as $item) {
      $item_id = $item['item_id'];
      $type = $item['type'];
      $user_id = $item['user_id'];

      if ($type === 'manual') {
        $manual = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $item_id");
        $row = mysqli_fetch_assoc($manual);

        $price = $row['price'];
        $total_amount = $total_amount + $price;
        $seller = $row['user_id'];

        $user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
        $school = $user['school'];

        $manual_ids[] = $item_id;

        // Use correct column name `school_id` to match schema
        mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) VALUES ($item_id, $price, $seller, $user_id, '$tx_ref', 'successful', $school)");
      } elseif ($type === 'event') {
        $event = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $item_id");
        $row = mysqli_fetch_assoc($event);

        $price = $row['price'];
        $total_amount = $total_amount + $price;
        $seller = $row['user_id'];

        $event_ids[] = $item_id;
        
        mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) VALUES ($item_id, $price, $seller, $user_id, '$tx_ref', 'successful')");
      }

      if (mysqli_affected_rows($conn) < 1) {
        $statusRes = "error";
        $messageRes = "Failed to add items. Please try again later.";
        break;
      }
    }

    // Calculate charges using the existing structure
    $charge = 0;
    if ($total_amount == 0) {
      $charge = 0;
    } elseif ($total_amount < 2500) {
      // Flat fee for transactions less than ₦2500
      $charge = 70;
    } else {
      // Previous calculation for higher amounts
      $charge += ($total_amount * 0.02);
      if ($total_amount >= 2500 && $total_amount < 5000) {
        $charge += 20;
      } elseif ($total_amount >= 5000 && $total_amount < 10000) {
        $charge += 30;
      } else {
        $charge += 50;
      }
    }

    // Finalize transaction by adding the charge to the total amount
    $total_amount += $charge;

    // Flutterwave fee (2% of the final amount)
    $flutterwave_fee = round($total_amount * 0.02, 2);
    // Profit is the remaining charge after Flutterwave fee
    $profit = round(max($charge - $flutterwave_fee, 0), 2);
    // Recompute using helper for consistency
    if (function_exists('calculateFlutterwaveSettlement')) {
      $baseAmount = max($total_amount - $charge, 0);
      $calc = calculateFlutterwaveSettlement($baseAmount);
      $charge = $calc['charge'];
      $profit = $calc['profit'];
      $total_amount = $calc['total_amount'];
    }

    mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) VALUES ('$tx_ref', $user_id, $total_amount, $charge, $profit, 'successful', 'flutterwave')");

    sendCongratulatoryEmail($conn, $user_id, $tx_ref, $manual_ids, $event_ids, $total_amount);
    mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref'");
    sendMail('FLW Webhook: Success', 'Processed ref ' . $tx_ref . ' for user ' . $user_id . ' amount NGN ' . number_format($total_amount, 2), 'webhook@nivasity.com');

    http_response_code(200);
    echo json_encode(['status' => $statusRes, 'message' => $messageRes]);
  } else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payment verification failed or invalid event.']);
  }
}
