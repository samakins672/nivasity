<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$payload = nivasityGetWalletTransactionsPayload($conn, (int)$user['id'], $page, 20);

sendApiSuccess('Wallet transactions retrieved successfully', $payload);
?>