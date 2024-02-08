<?php
session_start();
require_once 'config.php';
require_once '../config/fw.php';
$curl = curl_init();

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$cart_ = $_SESSION["nivas_cart$user_id"];

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

  if ($response['data']['status'] === "successful" && $response['data']['tx_ref'] === $tx_ref) {
    $status = $response['data']['status'];
    $total_amount = 0;

    // Iterate through each manualId
    foreach ($cart_ as $manual_id) {
      // Fetch details from manuals_2 table
      $manual = mysqli_query($conn, "SELECT price, user_id FROM manuals_$school_id WHERE id = $manual_id");

      if ($manual && mysqli_num_rows($manual) > 0) {
        $row = mysqli_fetch_assoc($manual);

        $price = $row['price'];
        $total_amount = $total_amount + $price;
        $seller = $row['user_id'];

        mysqli_query($conn, "INSERT INTO manuals_bought_$school_id (manual_id, price, seller, buyer, ref_id, status) VALUES ($manual_id, $price, $seller, $user_id, '$tx_ref', '$status')");

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


    if ($transferAmount < 2500) {
      $charge = 45;
    } elseif ($transferAmount >= 2500) {
      // Add 1.4% to the transferAmount
      $charge += ($transferAmount * 0.014);

      // Adjust the charge accordingly
      if ($transferAmount >= 2500 && $transferAmount < 5000) {
        $charge += 20;
      } elseif ($transferAmount >= 5000 && $transferAmount < 10000) {
        $charge += 30;
      } else {
        $charge += 35;
      }
    }

    // Add the charge to the total
    $total_amount += $charge;

    mysqli_query($conn, "INSERT INTO transactions_$school_id (ref_id, user_id, amount, status) VALUES ('$tx_ref', $user_id, $total_amount, '$status')");

    // Close the database connection if needed
    mysqli_close($conn);

    // Empty the cart variable
    $_SESSION["nivas_cart$user_id"] = array();

    header('Location: ../store.php?payment=successful');
  } else {
    // Inform the customer their payment was unsuccessful
    header('Location: ../store.php?payment=unsuccessful');
  }
}

// Output the final status and message
echo json_encode(array('status' => $statusRes, 'message' => $messageRes));
?>