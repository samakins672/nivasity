<?php
session_start();
require_once 'config.php';
require_once 'internal_wallet_service.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$userId = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

try {
    $result = nivasitySyncWalletFundingFromPaystack($conn, $userId, 'web_refresh');
    $dashboard = nivasityGetWalletDashboardPayload($conn, $userId, 25);
    echo json_encode([
        'status' => 'success',
        'message' => trim((string)($result['message'] ?? '')) !== '' ? (string)$result['message'] : 'Wallet funding refresh completed',
        'data' => [
            'refresh' => $result,
            'dashboard' => $dashboard,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}