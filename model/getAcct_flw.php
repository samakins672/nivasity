<?php
require_once '../config/fw.php';

// Assuming you are receiving account_number and bank_code as GET parameters
$accountNumber = $_GET['account_number'] ?? '';
$bankCode = $_GET['bank_code'] ?? '';

$curl = curl_init();

curl_setopt_array(
  $curl,
  array(
    CURLOPT_URL => "https://api.flutterwave.com/v3/accounts/resolve",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode([
      'account_number' => $accountNumber,
      'account_bank' => $bankCode,
    ]),
    CURLOPT_HTTPHEADER => array(
      "Authorization: Bearer " . FLW_SECRET_KEY,
      'Content-Type: application/json',
    ),
  )
);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo json_encode(array("error" => "cURL Error #" . $err));
} else {
  // Parse the flutterwave API response
  $flutterwaveResponse = json_decode($response, true);

  if ($flutterwaveResponse['status'] === 'success' && isset($flutterwaveResponse['data']['account_name'])) {
    // Return the account name and bank in the response
    echo json_encode(
      array(
        "account_name" => $flutterwaveResponse['data']['account_name'],
        "bank" => $bankCode
      )
    );
  } else {
    echo json_encode(array("error" => "Account number or bank is incorrect"));
  }
}
?>
