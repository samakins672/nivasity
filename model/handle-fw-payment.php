<?php
session_start();
require_once 'config.php';
require_once '../config/fw.php';
include('mail.php');
include('functions.php');
$curl = curl_init();

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$cart_ = $_SESSION["nivas_cart$user_id"];
$cart_2 = $_SESSION["nivas_cart_event$user_id"]; // Cart for events

$statusRes = "success";
$messageRes = "All manuals successfully added to manuals_bought_2!";

if (isset($_GET['transaction_id'])) {
  $tx_ref = $_GET['tx_ref'];

  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.flutterwave.com/v3/transactions/' . $_GET['transaction_id'] . '/verify',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'Authorization: Bearer ' . FLW_SECRET_KEY
    ),
  ));

  $response = curl_exec($curl);
  curl_close($curl);

  // Decode the JSON response
  $response = json_decode($response, true);
  // var_dump($response);

  // Check if the status is "successful" and data is not NULL
  if (isset($response['status']) && $response['status'] === "success" && isset($response['data']) && is_array($response['data'])) {
    $status = $response['data']['status'];
    $total_amount = 0;

    // Iterate through each manualId
    foreach ($cart_ as $manual_id) {
      // Fetch details from manuals_2 table
      $manual = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $manual_id AND school_id = $school_id");

      if ($manual && mysqli_num_rows($manual) > 0) {
        $row = mysqli_fetch_assoc($manual);

        $price = $row['price'];
        $total_amount = $total_amount + $price;
        $seller = $row['user_id'];

        mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) VALUES ($manual_id, $price, $seller, $user_id, '$tx_ref', '$status', $school_id)");

        if (mysqli_affected_rows($conn) < 1) {
          // Insertion failed for a manual
          $statusRes = "error";
          $messageRes = "Internal Server Error. Please try again later!";
          break; // Break the loop if any insertion fails
        }
      } else {
        // Unable to fetch details from manuals_2 for a manual
        $statusRes = "error";
        $messageRes = "Unable to fetch details from manuals. Please try again later!";
        break; // Break the loop if unable to fetch details
      }
    }

    // Process event tickets in the cart (cart_2)
    foreach ($cart_2 as $event_id) {
      // Fetch details from events table
      $event = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $event_id");

      if ($event && mysqli_num_rows($event) > 0) {
        $row = mysqli_fetch_assoc($event);

        $price = $row['price'];
        $total_amount = $total_amount + $price;
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


    $charge = 0;
    if ($total_amount < 2500) {
      $charge = 45;
    } elseif ($total_amount >= 2500) {
      // Add 1.4% to the total_amount
      $charge += ($total_amount * 0.014);

      // Adjust the charge accordingly
      if ($total_amount >= 2500 && $total_amount < 5000) {
        $charge += 20;
      } elseif ($total_amount >= 5000 && $total_amount < 10000) {
        $charge += 30;
      } else {
        $charge += 35;
      }
    }

    sendCongratulatoryEmail($conn, $user_id, $tx_ref, $cart_, $cart_2, $total_amount);

    // Add the charge to the total
    $total_amount += $charge;
    
    mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, status) VALUES ('$tx_ref', $user_id, $total_amount, '$status')");
    
    mysqli_query($conn, "DELETE FROM cart WHERE ref_id = '$tx_ref'");
    

    // Empty the cart variables for both manuals and events
    $_SESSION["nivas_cart$user_id"] = array();
    $_SESSION["nivas_cart_event$user_id"] = array();

    if (!isset($_GET['callback'])) {
        header('Location: ../store.php?payment=successful');
    }
  } else {
    if (!isset($_GET['callback'])) {
        // Inform the customer their payment was unsuccessful
        header('Location: ../store.php?payment=unsuccessful');
    }
  }
}

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Output the final status and message
echo json_encode(array('status' => $statusRes, 'message' => $messageRes));
?>