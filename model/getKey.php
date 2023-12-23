<?php
require_once '../config/fw.php';

$responseData = array(
  "flw_pk" => '---',
  "flw_sk" => '---'
);

// Your PHP code to process the AJAX request
if (isset($_POST['getKey'])) {
  $responseData = array(
    "flw_pk" => FLW_PUBLIC_KEY,
    "flw_sk" => FLW_SECRET_KEY
  );

}
// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);

?>