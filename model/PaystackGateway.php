<?php
/**
 * Paystack Payment Gateway Implementation
 * 
 * Implements the payment gateway interface for Paystack with special pricing:
 * - For amounts > ₦2500: add flat ₦100 fee + 1.5% fee
 */

require_once __DIR__ . '/PaymentGateway.php';

class PaystackGateway implements PaymentGateway {
    // Paystack special pricing: Flat fee for amounts > ₦2500
    const FLAT_FEE_THRESHOLD = 2500.0;
    const FLAT_FEE_AMOUNT = 100.0;
    const PERCENTAGE_FEE = 0.015; // 1.5%
    
    private $publicKey;
    private $secretKey;
    private $logFile;
    
    public function __construct($config) {
        $this->publicKey = $config['public_key'] ?? '';
        $this->secretKey = $config['secret_key'] ?? '';
        // Centralized error log path
        $this->logFile = __DIR__ . '/../error.log';
    }
    
    /**
     * Calculate transaction charges for Paystack
     * 
     * USER PRICING (what we charge):
     * - Under ₦2500: Charge static ₦100
     * - ₦2500 and above: Charge ₦20 + gateway fees
     * 
     * PAYSTACK GATEWAY FEE (what Paystack charges us):
     * - Under ₦2500: 1.5% (₦100 fee waived)
     * - ₦2500 and above: 1.5% + ₦100
     */
    public function calculateCharges($baseAmount) {
        $baseAmount = (float)$baseAmount;
        $charge = 0.0;
        $gateway_fee = 0.0;
        
        if ($baseAmount <= 0) {
            $charge = 0.0;
            $gateway_fee = 0.0;
        } elseif ($baseAmount < self::FLAT_FEE_THRESHOLD) {
            // For amounts under ₦2500: charge static ₦100 to user
            $charge = 100.0;
            // Paystack gateway fee: 1.5% only (₦100 waived for under ₦2500)
            $total = $baseAmount + $charge;
            $gateway_fee = round($total * self::PERCENTAGE_FEE, 2);
        } else {
            // For amounts ₦2500 and above: charge ₦20 + gateway fees
            // Gateway fees = 1.5% + ₦100
            $gateway_fees = ($baseAmount * self::PERCENTAGE_FEE) + self::FLAT_FEE_AMOUNT;
            $charge = 20.0 + $gateway_fees;
            // Paystack gateway fee is the actual 1.5% + ₦100
            $total = $baseAmount + $charge;
            $gateway_fee = round(($total * self::PERCENTAGE_FEE) + self::FLAT_FEE_AMOUNT, 2);
        }
        
        // Round to whole numbers for consistency
        $charge = round($charge);
        $total = round($baseAmount + $charge);
        $gateway_fee = round($gateway_fee);
        $profit = round(max($charge - $gateway_fee, 0));
        
        return [
            'total_amount' => $total,
            'charge' => $charge,
            'profit' => $profit,
            'gateway_fee' => $gateway_fee,
        ];
    }
    
    /**
     * Initialize payment with Paystack
     */
    public function initializePayment($params) {
        $curl = curl_init();
        
        $postData = [
            'amount' => $params['amount'] * 100, // Paystack expects amount in kobo
            'email' => $params['email'],
            'reference' => $params['reference'],
            'callback_url' => $params['callback_url'] ?? '',
        ];
        
        // Add subaccount if provided
        if (isset($params['subaccount'])) {
            $postData['subaccount'] = $params['subaccount'];
        }
        
        // Add transaction charge if provided
        if (isset($params['transaction_charge'])) {
            $postData['transaction_charge'] = $params['transaction_charge'];
        }
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.paystack.co/transaction/initialize',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->secretKey
            ),
        ));
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            $this->logError("InitializePayment cURL error: {$error}");
            return ['status' => false, 'message' => 'Connection error: ' . $error];
        }
        
        $data = json_decode($response, true);
        return $data;
    }
    
    /**
     * Verify a Paystack transaction
     */
    public function verifyTransaction($reference) {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.paystack.co/transaction/verify/' . urlencode($reference),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->secretKey
            ),
        ));
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            $this->logError("VerifyTransaction cURL error for ref {$reference}: {$error}");
            return ['status' => false, 'message' => 'Connection error: ' . $error];
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['status']) && $data['status'] === true && 
            isset($data['data']['status']) && $data['data']['status'] === 'success') {
            return [
                'status' => true,
                'data' => $data['data']
            ];
        }
        
        $this->logError("VerifyTransaction failed for ref {$reference}: " . $response);
        
        return ['status' => false, 'message' => 'Transaction verification failed'];
    }
    
    /**
     * Verify Paystack webhook signature
     */
    public function verifyWebhookSignature($headers, $payload) {
        $signature = '';
        foreach ($headers as $k => $v) {
            if (strtolower($k) === 'x-paystack-signature') {
                $signature = $v;
                break;
            }
        }
        
        if (empty($signature)) {
            return false;
        }
        
        $computedSignature = hash_hmac('sha512', $payload, $this->secretKey);
        return hash_equals($signature, $computedSignature);
    }
    
    public function getGatewayName() {
        return 'paystack';
    }
    
    public function getPublicKey() {
        return $this->publicKey;
    }

    private function logError($message) {
        $line = '[' . date('Y-m-d H:i:s') . '] [PAYSTACK] ' . $message . PHP_EOL;
        @file_put_contents($this->logFile, $line, FILE_APPEND);
    }
}
