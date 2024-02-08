<?php
session_start();
include('config.php');
require_once '../../config/fw.php';

// Assume the initial status is failed
$statusRes = 'failed';
$messageRes = 'Failed to initiate the process. Please try again.';

$user_id = $_SESSION['nivas_userId'];
$user_ = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
$user_email = $user_['email'];
$user_phone = $user_['phone'];

if (isset($_POST['settlement_id'])) {
  $settlement_id = mysqli_real_escape_string($conn, $_POST['settlement_id']);
  $acct_name = mysqli_real_escape_string($conn, $_POST['acct_name']);
  $acct_number = mysqli_real_escape_string($conn, $_POST['acct_number']);
  $bank = mysqli_real_escape_string($conn, $_POST['bank']);

  // Now, proceed to update the database
  if ($settlement_id == 0) {
    // Make the Flutterwave request
    $flutterwaveCurlResponse = makeFlutterwaveRequest($user_email, $user_phone, $bank, $acct_number, $acct_name, '');

    // Check if the Flutterwave request was successful
    if ($flutterwaveCurlResponse && isset($flutterwaveCurlResponse['status']) && $flutterwaveCurlResponse['status'] === 'success') {
      // If settlement_id is 0, insert a new record and include subaccount_code and flw_id
      $subaccount_code = $flutterwaveCurlResponse['data']['subaccount_id'];
      $flw_id = $flutterwaveCurlResponse['data']['id'];

      mysqli_query($conn, "INSERT INTO settlement_accounts (acct_name, acct_number, bank, user_id, flw_id, subaccount_code) 
        VALUES ('$acct_name', '$acct_number', '$bank', $user_id, '$flw_id', '$subaccount_code')");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = 'success';
        $messageRes = 'Successfully Added!';
      } else {
        $statusRes = 'error';
        $messageRes = 'Failed to insert record into the database.';
      }
    } else {
      // If Flutterwave request failed, set appropriate response
      $statusRes = 'error';
      $messageRes = 'Flutterwave request failed! Please check your details and try again. ' . json_encode($flutterwaveCurlResponse);
    }
  } else {
    $flw_id = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM settlement_accounts WHERE user_id = $user_id"))['flw_id'];
    $flw_id = '/' . $flw_id;

    // Make the Flutterwave request
    $flutterwaveCurlResponse = makeFlutterwaveRequest($user_email, $user_phone, $bank, $acct_number, $acct_name, $flw_id);

    // Check if the Flutterwave request was successful
    if ($flutterwaveCurlResponse && isset($flutterwaveCurlResponse['status']) && $flutterwaveCurlResponse['status'] === 'success') {
      mysqli_query($conn, "UPDATE settlement_accounts SET acct_name = '$acct_name', acct_number = '$acct_number', bank = '$bank' WHERE user_id = $user_id");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = 'success';
        $messageRes = 'Successfully Updated!';
      } else {
        $statusRes = 'error';
        $messageRes = 'Failed to update record in the database.';
      }
    } else {
      // If Flutterwave request failed, set appropriate response
      $statusRes = 'error';
      $messageRes = 'Flutterwave request failed. Please check your details and try again. ' . json_encode($flutterwaveCurlResponse);
    }
  }
}

$responseData = array(
  'status' => $statusRes,
  'message' => $messageRes
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);

// Function to make Flutterwave request
function makeFlutterwaveRequest($user_email, $user_phone, $bank, $acct_number, $acct_name, $flw_id)
{
  $flutterwaveCurlResponse = null;

  $curl = curl_init();

  $req = ($flw_id === '') ? "POST" : "PUT";

  curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.flutterwave.com/v3/subaccounts' . $flw_id,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => $req,
    CURLOPT_POSTFIELDS => json_encode([
      'account_bank' => $bank,
      'account_number' => $acct_number,
      'business_name' => $acct_name,
      'business_email' => $user_email,
      'business_mobile' => $user_phone,
      'country' => "NG",
      'currency' => "NGN",
      'split_type' => "percentage",
      'split_value' => 0.2,
    ]),
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . FLW_SECRET_KEY,
    ],
  ]);

  $flutterwaveCurlResponse = json_decode(curl_exec($curl), true);

  curl_close($curl);

  return $flutterwaveCurlResponse;
}
?>
