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
    $gatewayFrozen = is_payment_frozen('gateway');
    $walletFrozen = is_payment_frozen('wallet');
    $freezeInfo = $gatewayFrozen ? get_payment_freeze_info('gateway') : null;
    $freezeMessage = $freezeInfo ? $freezeInfo['message'] : '';

    $allowedChannels = [];
    if (!$gatewayFrozen) {
        $allowedChannels[] = 'gateway';
    }
    if (!$walletFrozen) {
        $allowedChannels[] = 'wallet';
    }
    
    sendApiSuccess('Active payment gateway retrieved', [
        'active' => $activeGateway,
        'available' => $availableGateways,
        'status' => !$gatewayFrozen,
        'message' => $freezeMessage,
        'gateway_enabled' => !$gatewayFrozen,
        'wallet_enabled' => !$walletFrozen,
        'allowed_payment_channels' => $allowedChannels
    ]);
} catch (Exception $e) {
    sendApiError('Failed to retrieve gateway information: ' . $e->getMessage(), 500);
}
?>
