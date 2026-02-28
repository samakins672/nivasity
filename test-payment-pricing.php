<?php
/**
 * Test script to verify payment gateway pricing calculations.
 */

require_once __DIR__ . '/model/PaymentGatewayFactory.php';

echo "=== Payment Gateway Pricing Test ===\n\n";

// Test amounts
$testAmounts = [1000, 2500, 2501, 5000, 10000, 26000, 50000];

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
                "Base: N%s | Charge: N%s | Total: N%s | Profit: N%s\n",
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
echo "For amounts >= N2400, Paystack charge = (1.5% + N100) + static add-on (N20/N40/N50)\n\n";

try {
    $gateway = PaymentGatewayFactory::getGateway('paystack');

    // Edge cases around threshold + add-on bands
    $edgeCases = [2399, 2400, 2499, 2500, 3000, 26000, 50000];

    foreach ($edgeCases as $amount) {
        $result = $gateway->calculateCharges($amount);

        if ($amount < 2400) {
            $expectedCharge = 100;
        } else {
            $staticAddOn = 20;
            if ($amount > 25000 && $amount < 50000) {
                $staticAddOn = 40;
            } elseif ($amount >= 50000) {
                $staticAddOn = 50;
            }
            $expectedCharge = ($amount * 0.015) + 100 + $staticAddOn;
        }

        $matches = abs($result['charge'] - round($expectedCharge)) < 0.01;
        $status = $matches ? 'PASS' : 'FAIL';

        echo sprintf(
            "%s Base: N%s | Expected Charge: N%s | Actual Charge: N%s\n",
            $status,
            number_format($amount, 2),
            number_format(round($expectedCharge), 2),
            number_format($result['charge'], 2)
        );
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
