<?php
// API: Get Active Payment Gateway
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// No authentication required - public endpoint to show available payment methods

try {
    $activeGateway = PaymentGatewayFactory::getActiveGatewayName();
    $availableGateways = PaymentGatewayFactory::getAvailableGateways();
    
    sendApiSuccess('Active payment gateway retrieved', [
        'active' => $activeGateway,
        'available' => $availableGateways
    ]);
} catch (Exception $e) {
    sendApiError('Failed to retrieve gateway information: ' . $e->getMessage(), 500);
}
?>
