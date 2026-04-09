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

$action = strtolower(trim((string)($_POST['action'] ?? '')));

try {
    if ($action === 'send_code') {
        $result = nivasitySendWalletPinCode($conn, $userId);
        echo json_encode([
            'status' => 'success',
            'message' => 'A Wallet PIN code has been sent to your email.',
            'data' => $result,
        ]);
        exit;
    }

    if ($action === 'save_pin') {
        $code = trim((string)($_POST['code'] ?? ''));
        $pin = trim((string)($_POST['pin'] ?? ''));
        $confirmPin = trim((string)($_POST['confirm_pin'] ?? ''));

        $result = nivasitySaveWalletPin($conn, $userId, $code, $pin, $confirmPin);
        echo json_encode([
            'status' => 'success',
            'message' => 'Wallet PIN saved successfully.',
            'data' => $result,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Wallet PIN action']);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}