<?php
/**
 * Payment Gateway Interface
 * 
 * All payment gateway implementations must implement this interface
 * to ensure consistent behavior across different providers.
 */

interface PaymentGateway {
    /**
     * Calculate transaction charges and fees
     * 
     * @param float $baseAmount The base amount before charges
     * @return array ['charge' => float, 'profit' => float, 'total_amount' => float, 'gateway_fee' => float]
     */
    public function calculateCharges($baseAmount);
    
    /**
     * Initialize a payment transaction
     * 
     * @param array $params Payment parameters (amount, email, reference, etc.)
     * @return array Response from payment gateway API
     */
    public function initializePayment($params);
    
    /**
     * Verify a transaction
     * 
     * @param string $reference Transaction reference
     * @return array ['status' => bool, 'data' => array]
     */
    public function verifyTransaction($reference);
    
    /**
     * Verify webhook signature/hash
     * 
     * @param array $headers Request headers
     * @param string $payload Raw payload
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature($headers, $payload);
    
    /**
     * Get the gateway name
     * 
     * @return string Gateway name (flutterwave, paystack, interswitch)
     */
    public function getGatewayName();
    
    /**
     * Get the public key for frontend initialization
     * 
     * @return string Public/publishable key
     */
    public function getPublicKey();
}
