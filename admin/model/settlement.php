<?php
session_start();
include('config.php');
require_once '../../config/fw.php';
$curl = curl_init();

$statusRes = $messageRes = 'failed';

$user_id = $_SESSION['nivas_userId'];

if (isset($_POST['settlement_id'])) {
  $settlement_id = mysqli_real_escape_string($conn, $_POST['settlement_id']);
  $acct_name = mysqli_real_escape_string($conn, $_POST['acct_name']);
  $acct_number = mysqli_real_escape_string($conn, $_POST['acct_number']);
  $bank = mysqli_real_escape_string($conn, $_POST['bank']);

  if ($settlement_id == 0) {
    mysqli_query($conn, "INSERT INTO settlement_accounts (acct_name,	acct_number,	bank,	user_id) 
        VALUES ('$acct_name',	$acct_number,	'$bank',	$user_id)");
  } else {
    mysqli_query($conn, "UPDATE settlement_accounts SET acct_name = '$acct_name', acct_number = $acct_number, bank = '$bank' WHERE user_id = $user_id");
  }

  if (mysqli_affected_rows($conn) >= 1) {
    // curl_setopt_array($curl, [
    //   CURLOPT_URL => 'https://api.flutterwave.com/v3/subaccounts',
    //   CURLOPT_RETURNTRANSFER => true,
    //   CURLOPT_ENCODING => '',
    //   CURLOPT_MAXREDIRS => 10,
    //   CURLOPT_TIMEOUT => 0,
    //   CURLOPT_FOLLOWLOCATION => true,
    //   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //   CURLOPT_CUSTOMREQUEST => 'POST',
    //   CURLOPT_POSTFIELDS => '{
    //     "account_bank": "044",
    //     "account_number": "0690000037",
    //     "business_name": "Eternal Blue",
    //     "business_email": "petya@stux.net",
    //     "business_mobile": "09087930450",
    //     "country": "NG",
    //     "currency": "NGN",
    //     "split_type": "flat",
    //     "split_value": 100
    //   }',
    //   CURLOPT_HTTPHEADER => [
    //     'Content-Type: application/json',
    //     'Authorization: Bearer ' . FLW_SECRET_KEY
    //   ],
    // ]
    // );

    $statusRes = "success";
    $messageRes = "Successfully Added!";
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error. Please try again later!";
  }
}

$responseData = array(
  "status" => "$statusRes",
  "message" => "$messageRes"
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
?>