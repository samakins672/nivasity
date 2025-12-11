<?php
/**
 * Test script to verify payment gateway pricing calculations
 * 
 * Run this script to verify that each gateway applies the correct pricing logic
 */

require_once __DIR__ . '/model/PaymentGatewayFactory.php';

echo "=== Payment Gateway Pricing Test ===\n\n";

// Test amounts
$testAmounts = [1000, 2500, 2501, 5000, 10000];

// Test each gateway
$gateways = ['flutterwave', 'paystack', 'interswitch'];

foreach ($gateways as $gatewayName) {
    echo "Testing $gatewayName:\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        $gateway = PaymentGatewayFactory::getGateway($gatewayName);
        
        foreach ($testAmounts as $amount) {
            $result = $gateway->calculateCharges($amount);
            
            echo sprintf(
                "Base: ₦%s | Charge: ₦%s | Total: ₦%s | Profit: ₦%s\n",
                number_format($amount, 2),
                number_format($result['charge'], 2),
                number_format($result['total_amount'], 2),
                number_format($result['profit'], 2)
            );
        }
        
        echo "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

echo "\n=== Paystack Special Pricing Verification ===\n";
echo "For amounts > ₦2500, Paystack should add ₦100 flat fee + 1.5%\n\n";

try {
    $gateway = PaymentGatewayFactory::getGateway('paystack');
    
    // Test edge cases around ₦2500
    $edgeCases = [2499, 2500, 2501, 3000];
    
    foreach ($edgeCases as $amount) {
        $result = $gateway->calculateCharges($amount);
        
        $expectedCharge = $amount <= 2500 
            ? ($amount * 0.015) 
            : (($amount * 0.015) + 100);
        
        $matches = abs($result['charge'] - $expectedCharge) < 0.01;
        $status = $matches ? '✓ PASS' : '✗ FAIL';
        
        echo sprintf(
            "%s Base: ₦%s | Expected Charge: ₦%s | Actual Charge: ₦%s\n",
            $status,
            number_format($amount, 2),
            number_format($expectedCharge, 2),
            number_format($result['charge'], 2)
        );
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
