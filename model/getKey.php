<?php
require_once 'payment_freeze.php';
require_once '../config/fw.php';
require_once 'PaymentGatewayFactory.php';

// Check if payments are frozen
if (is_payment_frozen()) {
    header('Content-Type: application/json');
    $freeze_info = get_payment_freeze_info();
    echo json_encode([
        'error' => true,
        'payment_frozen' => true,
        'message' => $freeze_info['message']
    ]);
    exit;
}

$responseData = array(
  "paystack_pk" => '---',
  "paystack_sk" => '---',
  "flw_pk" => '---',
  "flw_sk" => '---',
  "active_gateway" => 'flutterwave'
);

// Your PHP code to process the AJAX request
if (isset($_POST['getKey'])) {
  try {
    $activeGateway = PaymentGatewayFactory::getActiveGatewayName();
    $gateway = PaymentGatewayFactory::getActiveGateway();
    
    $responseData = array(
      "paystack_pk" => PAYSTACK_PUBLIC_KEY,
      "paystack_sk" => PAYSTACK_SECRET_KEY,
      "flw_pk" => FLW_PUBLIC_KEY,
      "flw_sk" => FLW_SECRET_KEY,
      "active_gateway" => $activeGateway,
      "active_gateway_pk" => $gateway->getPublicKey()
    );
  } catch (Exception $e) {
    // Fallback to legacy behavior if gateway factory fails
    $responseData = array(
      "paystack_pk" => PAYSTACK_PUBLIC_KEY,
      "paystack_sk" => PAYSTACK_SECRET_KEY,
      "flw_pk" => FLW_PUBLIC_KEY,
      "flw_sk" => FLW_SECRET_KEY,
      "active_gateway" => 'flutterwave'
    );
  }
}
// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);

?>