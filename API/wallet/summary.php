<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$wallet = nivasityGetUserWallet($conn, (int)$user['id']);

sendApiSuccess('Wallet summary retrieved successfully', [
    'has_wallet' => $wallet !== null,
    'wallet' => $wallet,
]);
