<?php
// API: Get Active Payment Gateway
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../model/payment_freeze.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// No authentication required - public endpoint to show available payment methods

try {
    $activeGateway = PaymentGatewayFactory::getActiveGatewayName();
    $availableGateways = PaymentGatewayFactory::getAvailableGateways();
    
    // Check payment freeze status
    $isFrozen = is_payment_frozen();
    $freezeMessage = '';
    
    if ($isFrozen) {
        $freezeInfo = get_payment_freeze_info();
        $freezeMessage = $freezeInfo ? $freezeInfo['message'] : 'Payments are currently paused.';
    }
    
    sendApiSuccess('Active payment gateway retrieved', [
        'active' => $activeGateway,
        'available' => $availableGateways,
        'status' => !$isFrozen,
        'message' => $freezeMessage
    ]);
} catch (Exception $e) {
    sendApiError('Failed to retrieve gateway information: ' . $e->getMessage(), 500);
}
?>
