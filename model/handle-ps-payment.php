<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/../config/fw.php';
include('mail.php');
include('functions.php');
require_once 'refund_engine.php';
$curl = curl_init();

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$cart_ = $_SESSION["nivas_cart$user_id"];
$cart_2 = $_SESSION["nivas_cart_event$user_id"]; // Cart for events

if (isset($_POST['nivas_ref'])) {
  $nivas_ref = $_POST['nivas_ref'];
  $amount = $_POST['amount'];
  $email = $_POST['email'];
  $seller = $_POST['seller'];
  $charge = $_POST['charge'];

  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.paystack.co/transaction/initialize',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode([
      'amount' => $amount,
      'email' => $email,
      'reference' => $nivas_ref,
      'subaccount' => $seller,
      'transaction_charge' => $charge,
      'callback_url' => nivasity_app_url('model/handle-ps-payment.php')
    ]),
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
    ),
  ));

  $response = curl_exec($curl);
  curl_close($curl);

  // Set the appropriate headers for JSON response
  header('Content-Type: application/json');

  // Decode the JSON response
  echo $response;
} else if (isset($_GET['reference'])) {
  $tx_ref = $_GET['reference'];

  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.paystack.co/transaction/verify/' . $_GET['reference'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'Authorization: Bearer ' . PAYSTACK_SECRET_KEY
    ),
  )
  );

  $response = curl_exec($curl);
  curl_close($curl);

  // Decode the JSON response
  $response = json_decode($response, true);

  if ($response['status'] === true && $response['data']['reference'] === $tx_ref) {
    $status = 'successful';
    $total_amount = 0;

    // Duplicate protection
    $safe_ref = mysqli_real_escape_string($conn, $tx_ref);
    $dupe = false;
    if (mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM transactions WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if (!$dupe && mysqli_num_rows(mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$safe_ref' LIMIT 1")) > 0) { $dupe = true; }
    if ($dupe) {
      consumeReservationsForSettledTx($conn, $tx_ref);
      mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$safe_ref'");
      $_SESSION["nivas_cart$user_id"] = array();
      $_SESSION["nivas_cart_event$user_id"] = array();
      header('Location: /?payment=successful');
      exit;
    }

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

    // Calculate charges using shared gateway pricing logic.
    $calc = calculateGatewayCharges($total_amount, 'paystack');
    $charge = $calc['charge'] ?? 0;
    $profit = $calc['profit'] ?? 0;
    $total_amount = $calc['total_amount'] ?? ($total_amount + $charge);

    try {
      mysqli_begin_transaction($conn);
      $refund_applied = consumeReservationsCore($conn, $tx_ref);
      $existingTx = mysqli_query($conn, "SELECT id FROM transactions WHERE ref_id = '$tx_ref' ORDER BY id DESC LIMIT 1");
      if ($existingTx && mysqli_num_rows($existingTx) > 0) {
        $updateTxSql = "UPDATE transactions
                        SET user_id = $user_id, amount = $total_amount, charge = $charge, profit = $profit, refund = $refund_applied, status = '$status', medium = 'PAYSTACK'
                        WHERE ref_id = '$tx_ref'";
        if (!mysqli_query($conn, $updateTxSql)) {
          throw new Exception('Failed to repair transaction: ' . mysqli_error($conn));
        }
      } else {
        $insertTxSql = "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, refund, status, medium) VALUES ('$tx_ref', $user_id, $total_amount, $charge, $profit, $refund_applied, '$status', 'PAYSTACK')";
        if (!mysqli_query($conn, $insertTxSql)) {
          throw new Exception('Failed to record transaction: ' . mysqli_error($conn));
        }
      }
      mysqli_commit($conn);
    } catch (Throwable $e) {
      mysqli_rollback($conn);
      header('Location: /?payment=unsuccessful');
      exit;
    }

    sendCongratulatoryEmail($conn, $user_id, $tx_ref, $cart_, $cart_2, $total_amount);

    // Mark cart rows as confirmed
    mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$tx_ref'");

    // Close the database connection if needed
    mysqli_close($conn);

    // Empty the cart variables for both manuals and events
    $_SESSION["nivas_cart$user_id"] = array();
    $_SESSION["nivas_cart_event$user_id"] = array();

    header('Location: /?payment=successful');
  } else {
    // Inform the customer their payment was unsuccessful
    header('Location: /?payment=unsuccessful');
  }
}
?>
