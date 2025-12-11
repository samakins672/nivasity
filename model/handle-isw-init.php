<?php
/**
 * Interswitch Payment Initialization Handler
 * 
 * This handler initializes Interswitch payment and redirects to their payment page
 */

session_start();
require_once 'config.php';
require_once '../config/fw.php';
require_once 'PaymentGatewayFactory.php';

// Get parameters
$tx_ref = $_GET['ref'] ?? '';
$amount = (float)($_GET['amount'] ?? 0);
$user_id = $_SESSION['nivas_userId'] ?? 0;
$user_email = $_SESSION['nivas_userEmail'] ?? '';

if (empty($tx_ref) || $amount <= 0) {
    header('Location: /?payment=error&message=Invalid payment parameters');
    exit;
}

try {
    // Get Interswitch gateway
    $gateway = PaymentGatewayFactory::getGateway('interswitch');
    
    // Initialize payment
    $initResult = $gateway->initializePayment([
        'reference' => $tx_ref,
        'amount' => $amount,
        'email' => $user_email,
        'customer_name' => $_SESSION['nivas_userName'] ?? '',
        'callback_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/model/handle-isw-webhook.php',
        'currency' => '566', // NGN
    ]);
    
    if (!isset($initResult['status']) || $initResult['status'] !== 'success') {
        header('Location: /?payment=error&message=Failed to initialize payment');
        exit;
    }
    
    // Build Interswitch payment URL with parameters
    $paymentUrl = $initResult['payment_url'];
    $params = http_build_query([
        'product_id' => $initResult['pay_item_id'],
        'pay_item_id' => $initResult['pay_item_id'],
        'merchant_code' => $initResult['merchant_code'],
        'txn_ref' => $initResult['txn_ref'],
        'amount' => $initResult['amount'], // in kobo
        'currency' => $initResult['currency'],
        'site_redirect_url' => $initResult['site_redirect_url'],
        'hash' => $initResult['mac'],
        'cust_id' => $user_id,
        'cust_name' => $initResult['customer_name'],
    ]);
    
    // Redirect to Interswitch payment page
    header('Location: ' . $paymentUrl . '?' . $params);
    exit;
    
} catch (Exception $e) {
    error_log('Interswitch initialization error: ' . $e->getMessage());
    header('Location: /?payment=error&message=Payment initialization failed');
    exit;
}
?>
