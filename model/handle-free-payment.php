<?php
session_start();
require_once 'config.php';
require_once '../config/fw.php';
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
        $manual = mysqli_query($conn, "SELECT price, user_id FROM manuals_$school_id WHERE id = $manual_id");

        if ($manual && mysqli_num_rows($manual) > 0) {
            $row = mysqli_fetch_assoc($manual);
            $price = $row['price'];
            $seller = $row['user_id'];

            // Insert into manuals_bought
            mysqli_query($conn, "INSERT INTO manuals_bought_$school_id (manual_id, price, seller, buyer, ref_id, status) VALUES ($manual_id, $price, $seller, $user_id, '$tx_ref', '$status')");

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

    // Insert the transaction record
    mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, status, medium) VALUES ('$tx_ref', $user_id, $total_amount, '$status', 'NIVASITY')");

    // Close the database connection
    mysqli_close($conn);

    // Empty the cart variables for both manuals and events
    $_SESSION["nivas_cart$user_id"] = array();
    $_SESSION["nivas_cart_event$user_id"] = array();

    // Redirect to store page with success message
    header('Location: ../store.php?payment=successful');
}

// Output the final status and message
echo json_encode(array('status' => $statusRes, 'message' => $messageRes));

?>