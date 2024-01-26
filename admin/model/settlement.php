<?php
session_start();
include('config.php');
require_once '../../config/fw.php';

// Assume the initial status is failed
$statusRes = 'failed';
$messageRes = 'Failed to initiate the process. Please try again.';

$user_id = $_SESSION['nivas_userId'];

if (isset($_POST['settlement_id'])) {
  $settlement_id = mysqli_real_escape_string($conn, $_POST['settlement_id']);
  $acct_name = mysqli_real_escape_string($conn, $_POST['acct_name']);
  $acct_number = mysqli_real_escape_string($conn, $_POST['acct_number']);
  $bank = mysqli_real_escape_string($conn, $_POST['bank']);

  // Now, proceed to update the database
  if ($settlement_id == 0) {
    // Make the Paystack request
    $paystackCurlResponse = makePaystackRequest($bank, $acct_number, $acct_name, '');

    // Check if the Paystack request was successful
    if ($paystackCurlResponse && isset($paystackCurlResponse['status']) && $paystackCurlResponse['status'] === true) {
      // If settlement_id is 0, insert a new record and include subaccount_code
      $subaccount_code = $paystackCurlResponse['data']['subaccount_code'];

      mysqli_query($conn, "INSERT INTO settlement_accounts (acct_name, acct_number, bank, user_id, subaccount_code) 
        VALUES ('$acct_name', '$acct_number', '$bank', $user_id, '$subaccount_code')");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = 'success';
        $messageRes = 'Successfully Added!';
      } else {
        $statusRes = 'error';
        $messageRes = 'Failed to insert record into the database.';
      }
    } else {
      // If Paystack request failed, set appropriate response
      $statusRes = 'error';
      $messageRes = 'Paystack request failed! Please check your details and try again. ' . json_encode($paystackCurlResponse);
    }
  } else {
    $subaccount_code = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM settlement_accounts WHERE user_id = $user_id"))['subaccount_code'];
    $subaccount_code = '/' . $subaccount_code;
    
    // Make the Paystack request
    $paystackCurlResponse = makePaystackRequest($bank, $acct_number, $acct_name, $subaccount_code);

    // Check if the Paystack request was successful
    if ($paystackCurlResponse && isset($paystackCurlResponse['status']) && $paystackCurlResponse['status'] === true) {
      mysqli_query($conn, "UPDATE settlement_accounts SET acct_name = '$acct_name', acct_number = '$acct_number', bank = '$bank' WHERE user_id = $user_id");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = 'success';
        $messageRes = 'Successfully Updated!';
      } else {
        $statusRes = 'error';
        $messageRes = 'Failed to update record in the database.';
      }
    } else {
      // If Paystack request failed, set appropriate response
      $statusRes = 'error';
      $messageRes = 'Paystack request failed. Please check your details and try again. ' . json_encode($paystackCurlResponse);
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

// Function to make Paystack request
function makePaystackRequest($bank, $acct_number, $acct_name, $subaccount_code)
{
  $paystackCurlResponse = null;

  $curl = curl_init();
  
  $req = ($subaccount_code === '') ? "POST" : "PUT" ;

  curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.paystack.co/subaccount' . $subaccount_code,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => $req,
    CURLOPT_POSTFIELDS => json_encode([
      'settlement_bank' => $bank,
      'account_number' => $acct_number,
      'business_name' => $acct_name,
      'percentage_charge' => 2
    ]),
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
    ],
  ]);

  $paystackCurlResponse = json_decode(curl_exec($curl), true);

  curl_close($curl);

  return $paystackCurlResponse;
}
?>
