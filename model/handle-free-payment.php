<?php
session_start();
require_once 'config.php';
require_once 'payment_freeze.php';
require_once '../config/fw.php';
include('mail.php');
include('functions.php');

// Check if payments are frozen
if (is_payment_frozen()) {
    header('Content-Type: application/json');
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

$curl = curl_init();

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$cart_ = $_SESSION["nivas_cart$user_id"];
$cart_2 = $_SESSION["nivas_cart_event$user_id"]; // Cart for events

$statusRes = "success";
$messageRes = "All manuals and events successfully processed!";

if (isset($_GET['tx_ref'])) {
    $tx_ref = $_GET['tx_ref'];
    $status = 'successful';
    $total_amount = 0;

    // Process manuals in the cart
    foreach ($cart_ as $manual_id) {
        // Fetch details from manuals_2 table
        $manual = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $manual_id AND school_id = $school_id");

        if ($manual && mysqli_num_rows($manual) > 0) {
            $row = mysqli_fetch_assoc($manual);
            $price = $row['price'];
            $seller = $row['user_id'];

            // Insert into manuals_bought
            mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) VALUES ($manual_id, $price, $seller, $user_id, '$tx_ref', '$status', $school_id)");

            if (mysqli_affected_rows($conn) < 1) {
                $statusRes = "error";
                $messageRes = "Internal Server Error while adding manual. Please try again later!";
                break; // Stop processing if there is an error
            }

        } else {
            $statusRes = "error";
            $messageRes = "Unable to fetch details from manuals. Please try again later!";
            break;
        }
    }

    // Process event tickets in the cart (cart_2)
    foreach ($cart_2 as $event_id) {
        // Fetch details from events table
        $event = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $event_id");

        if ($event && mysqli_num_rows($event) > 0) {
            $row = mysqli_fetch_assoc($event);
            $price = $row['price'];
            $seller = $row['user_id'];

            // Insert into event_tickets table
            mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) VALUES ($event_id, $price, $seller, $user_id, '$tx_ref', '$status')");

            if (mysqli_affected_rows($conn) < 1) {
                $statusRes = "error";
                $messageRes = "Internal Server Error while adding event ticket. Please try again later!";
                break; // Stop processing if there is an error
            }
        } else {
            $statusRes = "error";
            $messageRes = "Unable to fetch details from events. Please try again later!";
            break;
        }
    }

    sendCongratulatoryEmail($conn, $user_id, $tx_ref, $cart_, $cart_2, $total_amount);

    // Duplicate protection
    $safe_ref = mysqli_real_escape_string($conn, $tx_ref);
    $dupe = false;
    if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM transactions WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if ($dupe) {
        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$safe_ref'");
    } else {
        // Insert the transaction record with zero charge and profit
        mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) VALUES ('$tx_ref', $user_id, $total_amount, 0, 0, '$status', 'NIVASITY')");
        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref'");
    }

    // Close the database connection
    mysqli_close($conn);

    // Empty the cart variables for both manuals and events
    $_SESSION["nivas_cart$user_id"] = array();
    $_SESSION["nivas_cart_event$user_id"] = array();
}

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Output the final status and message
echo json_encode(array('status' => $statusRes, 'message' => $messageRes));

?>
