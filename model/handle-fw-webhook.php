<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once '../config/fw.php';
include('mail.php');
include('functions.php');

// Retrieve the webhook payload
$payload = file_get_contents('php://input');
$headers = getallheaders();

// Verify the Verif-Hash header
if (!isset($headers['verif-hash']) || $headers['verif-hash'] !== FLW_VERIF_HASH) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid verification hash']);
    exit;
}

// Decode the JSON payload
$data = json_decode($payload, true);

if ($data['event'] === 'charge.completed' && $data['data']['status'] === 'successful') {
    $tx_ref = $data['data']['tx_ref'];
    $transaction_id = $data['data']['id'];
    $amount = $data['data']['amount'];

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
        $price = $item['price'];
        $seller = $item['seller'];
        $user_id = $item['user_id'];

        $total_amount += $price;

        if ($type === 'manual') {
            $manual_ids[] = $item_id;
            mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status) VALUES ($item_id, $price, $seller, $user_id, '$tx_ref', 'successful')");
        } elseif ($type === 'event') {
            $event_ids[] = $item_id;
            mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) VALUES ($item_id, $price, $seller, $user_id, '$tx_ref', 'successful')");
        }

        if (mysqli_affected_rows($conn) < 1) {
            $statusRes = "error";
            $messageRes = "Failed to add items. Please try again later.";
            break;
        }
    }

    // Calculate additional charges
    $charge = 0;
    if ($total_amount < 2500) {
        $charge = 45;
    } elseif ($total_amount >= 2500) {
        $charge += ($total_amount * 0.014);

        if ($total_amount >= 2500 && $total_amount < 5000) {
            $charge += 20;
        } elseif ($total_amount >= 5000 && $total_amount < 10000) {
            $charge += 30;
        } else {
            $charge += 35;
        }
    }

    // Finalize transaction
    $total_amount += $charge;
    mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, status) VALUES ('$tx_ref', $user_id, $total_amount, 'successful')");

    sendCongratulatoryEmail($conn, $user_id, $tx_ref, $manual_ids, $event_ids, $total_amount);

    http_response_code(200);
    echo json_encode(['status' => $statusRes, 'message' => $messageRes]);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Payment verification failed or invalid event.']);
}
