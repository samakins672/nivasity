<?php
/**
 * Flutterwave Payment Gateway Implementation
 * 
 * Implements the payment gateway interface for Flutterwave following
 * the existing implementation in handle-fw-payment.php
 */

require_once __DIR__ . '/PaymentGateway.php';

class FlutterwaveGateway implements PaymentGateway {
    private $publicKey;
    private $secretKey;
    private $verifHash;
    private $logFile;
    
    public function __construct($config) {
        $this->publicKey = $config['public_key'] ?? '';
        $this->secretKey = $config['secret_key'] ?? '';
        $this->verifHash = $config['verif_hash'] ?? '';
        $this->logFile = __DIR__ . '/../error.log';
    }
    
    /**
     * Calculate transaction charges for Flutterwave
     * Follows the existing logic from handle-fw-payment.php
     * Updated to use 2.15% percentage fee
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
            // Percentage + tiered additions (2.15% instead of 2%)
            $charge += ($baseAmount * 0.0215);
            if ($baseAmount >= 2500 && $baseAmount < 5000) {
                $charge += 20.0;
            } elseif ($baseAmount >= 5000 && $baseAmount < 10000) {
                $charge += 30.0;
            } else {
                $charge += 50.0;
            }
        }
        
        $total = $baseAmount + $charge;
        // Round to whole numbers for consistency
        $charge = round($charge);
        $total = round($total);
        $gateway_fee = round($total * 0.0215);
        $profit = round(max($charge - $gateway_fee, 0));
        
        return [
            'total_amount' => $total,
            'charge' => $charge,
            'profit' => $profit,
            'gateway_fee' => $gateway_fee,
        ];
    }
    
    /**
     * Initialize payment with Flutterwave
     * Note: Flutterwave uses frontend SDK initialization, so this returns config
     */
    public function initializePayment($params) {
        // Flutterwave uses client-side initialization with FlutterwaveCheckout
        // Return the configuration needed for frontend
        return [
            'status' => 'success',
            'gateway' => 'flutterwave',
            'public_key' => $this->publicKey,
            'tx_ref' => $params['reference'],
            'amount' => $params['amount'],
            'currency' => $params['currency'] ?? 'NGN',
            'customer' => [
                'email' => $params['email'],
                'name' => $params['customer_name'] ?? '',
            ],
            'customizations' => [
                'title' => $params['title'] ?? 'Nivasity Payment',
                'description' => $params['description'] ?? 'Payment for items',
            ]
        ];
    }
    
    /**
     * Verify a Flutterwave transaction
     */
    public function verifyTransaction($referenceOrId) {
        $curl = curl_init();
        $url = '';

        // Flutterwave best practice: verify by transaction_id; fall back to tx_ref search
        if (is_numeric($referenceOrId)) {
            $url = 'https://api.flutterwave.com/v3/transactions/' . urlencode($referenceOrId) . '/verify';
        } else {
            $url = 'https://api.flutterwave.com/v3/transactions?tx_ref=' . urlencode($referenceOrId);
        }
        
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
                'Authorization: Bearer ' . $this->secretKey
            ),
        ));
        
        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            $this->logError("VerifyTransaction cURL error for {$referenceOrId}: {$error}");
            return ['status' => false, 'message' => 'Connection error: ' . $error];
        }
        
        $data = json_decode($response, true);

        // When verifying by id, response shape is data[status, data => {...}]
        if (isset($data['status']) && $data['status'] === 'success' &&
            isset($data['data']['status']) && $data['data']['status'] === 'successful') {
            return [
                'status' => true,
                'data' => $data['data']
            ];
        }

        // When searching by tx_ref, response shape is data => [ { ... } ]
        if (isset($data['status']) && $data['status'] === 'success' &&
            isset($data['data'][0]['status']) && $data['data'][0]['status'] === 'successful') {
            return [
                'status' => true,
                'data' => $data['data'][0]
            ];
        }

        $this->logError("VerifyTransaction failed for {$referenceOrId}: " . $response);
        
        return [
            'status' => false,
            'message' => 'Transaction verification failed: ' . (is_string($response) ? $response : json_encode($response))
        ];
    }
    
    /**
     * Verify Flutterwave webhook signature
     */
    public function verifyWebhookSignature($headers, $payload) {
        if (empty($this->verifHash)) {
            // No hash configured, allow through but log warning
            return true;
        }
        
        $verifHash = '';
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            if ($lk === 'verif-hash' || $lk === 'verif_hash' || $lk === 'x-flw-signature') {
                $verifHash = $v;
                break;
            }
        }
        
        return $verifHash === $this->verifHash;
    }
    
    public function getGatewayName() {
        return 'flutterwave';
    }
    
    public function getPublicKey() {
        return $this->publicKey;
    }

    private function logError($message) {
        $line = '[' . date('Y-m-d H:i:s') . '] [FLUTTERWAVE] ' . $message . PHP_EOL;
        @file_put_contents($this->logFile, $line, FILE_APPEND);
    }
}
