<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = strtolower(trim((string)($input['action'] ?? '')));

if ($action === '') {
    sendApiError('action is required', 400);
}

try {
    if ($action === 'send_code') {
        $result = nivasitySendWalletPinCode($conn, (int)$user['id']);
        sendApiSuccess('A Wallet PIN code has been sent to your email.', $result);
    }

    if ($action === 'save_pin') {
        validateRequiredFields(['code', 'pin', 'confirm_pin'], $input);

        $result = nivasitySaveWalletPin(
            $conn,
            (int)$user['id'],
            (string)$input['code'],
            (string)$input['pin'],
            (string)$input['confirm_pin']
        );

        sendApiSuccess('Wallet PIN saved successfully.', $result);
    }

    sendApiError('Invalid Wallet PIN action', 400);
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}