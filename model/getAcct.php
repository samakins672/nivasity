<?php
require_once '../config/fw.php';

// Assuming you are receiving account_number and bank_code as GET parameters
$accountNumber = $_GET['account_number'] ?? '';
$bankCode = $_GET['bank_code'] ?? '';

$curl = curl_init();

curl_setopt_array(
  $curl,
  array(
    CURLOPT_URL => "https://api.paystack.co/bank/resolve?account_number=$accountNumber&bank_code=$bankCode",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => array(
      "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
      "Cache-Control: no-cache",
    ),
  )
);

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
  echo json_encode(array("error" => "cURL Error #" . $err));
} else {
  // Parse the Paystack API response
  $paystackResponse = json_decode($response, true);

  if ($paystackResponse['status'] === true && isset($paystackResponse['data']['account_name'])) {
    // Return the account name and bank in the response
    echo json_encode(
      array(
        "account_name" => $paystackResponse['data']['account_name'],
        "bank" => $bankCode // You can include more details about the bank if needed
      )
    );
  } else {
    echo json_encode(array("error" => "Account number or bank is incorrect"));
  }
}
?>
