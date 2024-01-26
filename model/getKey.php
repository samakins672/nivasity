<?php
require_once '../config/fw.php';

$responseData = array(
  "paystack_pk" => '---',
  "paystack_sk" => '---',
  "flw_pk" => '---',
  "flw_sk" => '---'
);

// Your PHP code to process the AJAX request
if (isset($_POST['getKey'])) {
  $responseData = array(
    "paystack_pk" => PAYSTACK_PUBLIC_KEY,
    "paystack_sk" => PAYSTACK_SECRET_KEY,
    "flw_pk" => FLW_PUBLIC_KEY,
    "flw_sk" => FLW_SECRET_KEY
  );

}
// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);

?>