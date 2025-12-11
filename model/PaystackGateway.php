<?php
/**
 * Paystack Payment Gateway Implementation
 * 
 * Implements the payment gateway interface for Paystack with special pricing:
 * - For amounts > ₦2500: add flat ₦100 fee + 1.5% fee
 */

require_once __DIR__ . '/PaymentGateway.php';

class PaystackGateway implements PaymentGateway {
    private $publicKey;
    private $secretKey;
    
    public function __construct($config) {
        $this->publicKey = $config['public_key'] ?? '';
        $this->secretKey = $config['secret_key'] ?? '';
    }
    
    /**
     * Calculate transaction charges for Paystack
     * SPECIAL PRICING: For amounts > ₦2500, add flat ₦100 + 1.5% fee
     */
    public function calculateCharges($baseAmount) {
        $baseAmount = (float)$baseAmount;
        $charge = 0.0;
        
        if ($baseAmount <= 0) {
            $charge = 0.0;
        } elseif ($baseAmount <= 2500) {
            // For amounts up to ₦2500: use 1.5% fee only
            $charge = $baseAmount * 0.015;
        } else {
            // For amounts > ₦2500: 1.5% + flat ₦100 fee (PAYSTACK EXCEPTION)
            $charge = ($baseAmount * 0.015) + 100.0;
        }
        
        $total = $baseAmount + $charge;
        // Paystack fee is approximately 1.5% + ₦100 (capped at ₦2000)
        // For simplicity, we'll use 2% of total as gateway fee estimate
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
}
