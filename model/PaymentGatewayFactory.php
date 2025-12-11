<?php
/**
 * Payment Gateway Factory
 * 
 * Creates and returns the appropriate payment gateway instance
 * based on the active gateway configuration.
 */

require_once __DIR__ . '/PaymentGateway.php';
require_once __DIR__ . '/FlutterwaveGateway.php';
require_once __DIR__ . '/PaystackGateway.php';
require_once __DIR__ . '/InterswitchGateway.php';
require_once __DIR__ . '/../config/fw.php';

class PaymentGatewayFactory {
    /**
     * Get the active payment gateway instance
     * 
     * @return PaymentGateway
     * @throws Exception if gateway config is invalid
     */
    public static function getActiveGateway() {
        $config = $GLOBALS['payment_gateway_config'] ?? [];
        $activeGateway = $config['active'] ?? 'flutterwave';
        
        return self::getGateway($activeGateway);
    }
    
    /**
     * Get a specific payment gateway instance
     * 
     * @param string $gatewayName Name of the gateway (flutterwave, paystack, interswitch)
     * @return PaymentGateway
     * @throws Exception if gateway is not found or config is invalid
     */
    public static function getGateway($gatewayName) {
        $config = $GLOBALS['payment_gateway_config'] ?? [];
        
        if (!isset($config[$gatewayName])) {
            throw new Exception("Gateway '$gatewayName' not found in configuration");
        }
        
        $gatewayConfig = $config[$gatewayName];
        
        switch ($gatewayName) {
            case 'flutterwave':
                return new FlutterwaveGateway($gatewayConfig);
                
            case 'paystack':
                return new PaystackGateway($gatewayConfig);
                
            case 'interswitch':
                return new InterswitchGateway($gatewayConfig);
                
            default:
                throw new Exception("Unknown gateway: $gatewayName");
        }
    }
    
    /**
     * Get the name of the active gateway
     * 
     * @return string
     */
    public static function getActiveGatewayName() {
        $config = $GLOBALS['payment_gateway_config'] ?? [];
        return $config['active'] ?? 'flutterwave';
    }
    
    /**
     * Get all available gateways
     * 
     * @return array List of available gateway names
     */
    public static function getAvailableGateways() {
        $config = $GLOBALS['payment_gateway_config'] ?? [];
        $gateways = [];
        
        foreach (['flutterwave', 'paystack', 'interswitch'] as $name) {
            if (isset($config[$name]) && ($config[$name]['enabled'] ?? false)) {
                $gateways[] = $name;
            }
        }
        
        return $gateways;
    }
}
