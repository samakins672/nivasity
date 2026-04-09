<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

try {
    $result = nivasitySyncWalletFundingFromPaystack($conn, (int)$user['id'], 'api_refresh');
    sendApiSuccess(trim((string)($result['message'] ?? '')) !== '' ? (string)$result['message'] : 'Wallet funding refresh completed', $result);
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}