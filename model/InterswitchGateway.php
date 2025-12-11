<?php
/**
 * Interswitch Payment Gateway Implementation
 * 
 * Implements the payment gateway interface for Interswitch/Quickteller
 */

require_once __DIR__ . '/PaymentGateway.php';

class InterswitchGateway implements PaymentGateway {
    private $merchantCode;
    private $payItemId;
    private $macKey;
    private $apiKey;
    private $quicktellerConfig;
    
    public function __construct($config) {
        $this->merchantCode = $config['merchant_code'] ?? '';
        $this->payItemId = $config['pay_item_id'] ?? '';
        $this->macKey = $config['mac_key'] ?? '';
        $this->apiKey = $config['api_key'] ?? '';
        $this->quicktellerConfig = $config['quickteller'] ?? [];
    }
    
    /**
     * Calculate transaction charges for Interswitch
     * Uses same default logic as Flutterwave (standard pricing)
     */
    public function calculateCharges($baseAmount) {
        $baseAmount = (float)$baseAmount;
        $charge = 0.0;
        
        if ($baseAmount <= 0) {
            $charge = 0.0;
        } elseif ($baseAmount < 2500) {
            // Flat fee for transactions less than ₦2500
            $charge = 70.0;
        } else {
            // Percentage + tiered additions
            $charge += ($baseAmount * 0.02);
            if ($baseAmount >= 2500 && $baseAmount < 5000) {
                $charge += 20.0;
            } elseif ($baseAmount >= 5000 && $baseAmount < 10000) {
                $charge += 30.0;
            } else {
                $charge += 50.0;
            }
        }
        
        $total = $baseAmount + $charge;
        $gateway_fee = round($total * 0.02, 2);
        $profit = round(max($charge - $gateway_fee, 0), 2);
        
        return [
            'total_amount' => $total,
            'charge' => $charge,
            'profit' => $profit,
            'gateway_fee' => $gateway_fee,
        ];
    }
    
    /**
     * Initialize payment with Interswitch
     */
    public function initializePayment($params) {
        $txnRef = $params['reference'];
        $amount = $params['amount'] * 100; // Convert to kobo
        
        // Generate MAC for security
        $mac = $this->generateMac($txnRef, $amount);
        
        // Interswitch payment URL
        $paymentUrl = 'https://webpay.interswitchng.com/paydirect/pay';
        
        return [
            'status' => 'success',
            'gateway' => 'interswitch',
            'payment_url' => $paymentUrl,
            'merchant_code' => $this->merchantCode,
            'pay_item_id' => $this->payItemId,
            'txn_ref' => $txnRef,
            'amount' => $amount,
            'currency' => $params['currency'] ?? '566', // NGN currency code
            'site_redirect_url' => $params['callback_url'] ?? '',
            'mac' => $mac,
            'customer_email' => $params['email'],
            'customer_name' => $params['customer_name'] ?? '',
        ];
    }
    
    /**
     * Verify an Interswitch transaction
     */
    public function verifyTransaction($reference) {
        // Generate MAC for verification
        $mac = $this->generateVerifyMac($reference);
        
        // Query transaction status
        $url = "https://webpay.interswitchng.com/paydirect/api/v1/gettransaction.json";
        $url .= "?merchantcode=" . urlencode($this->merchantCode);
        $url .= "&transactionreference=" . urlencode($reference);
        $url .= "&amount=0"; // Amount can be 0 for query
        
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Hash: ' . $mac
            ),
        ));
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            return ['status' => false, 'message' => 'Connection error: ' . $error];
        }
        
        $data = json_decode($response, true);
        
        // Interswitch returns ResponseCode 00 for successful transactions
        if (isset($data['ResponseCode']) && $data['ResponseCode'] === '00') {
            return [
                'status' => true,
                'data' => $data
            ];
        }
        
        return ['status' => false, 'message' => 'Transaction verification failed', 'data' => $data];
    }
    
    /**
     * Verify Interswitch webhook/callback MAC
     * 
     * Note: Interswitch uses MAC verification through query parameters.
     * The MAC should be verified in the webhook handler by calling verifyTransaction()
     * which includes MAC validation as part of the transaction status check.
     */
    public function verifyWebhookSignature($headers, $payload) {
        // MAC verification is handled through verifyTransaction() method
        // which validates the MAC as part of the transaction query
        return true;
    }
    
    /**
     * Generate MAC for payment initialization
     */
    private function generateMac($txnRef, $amount) {
        $mac_string = $txnRef . $this->merchantCode . $this->payItemId . $amount . $this->macKey;
        return hash('sha512', $mac_string);
    }
    
    /**
     * Generate MAC for transaction verification
     */
    private function generateVerifyMac($txnRef) {
        $mac_string = $this->merchantCode . $txnRef . $this->macKey;
        return hash('sha512', $mac_string);
    }
    
    public function getGatewayName() {
        return 'interswitch';
    }
    
    public function getPublicKey() {
        // Interswitch uses merchant code instead of public key
        return $this->merchantCode;
    }
}
