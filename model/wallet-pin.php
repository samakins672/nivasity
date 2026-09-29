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

    if ($action === 'verify_code') {
        $code = trim((string)($_POST['code'] ?? ''));

        $result = nivasityVerifyWalletPinCode($conn, $userId, $code);
        echo json_encode([
            'status' => 'success',
            'message' => 'Wallet PIN code verified successfully.',
            'data' => $result,
        ]);
        exit;
    }

    if ($action === 'save_pin' || $action === 'set_pin_direct') {
        $pin = trim((string)($_POST['pin'] ?? ''));
        $confirmPin = trim((string)($_POST['confirm_pin'] ?? ''));

        if (!nivasityIsValidWalletPin($pin)) {
            throw new Exception('Wallet PIN must be exactly 4 digits');
        }
        if ($pin !== $confirmPin) {
            throw new Exception('Wallet PIN confirmation does not match');
        }

        $wallet = nivasityGetUserWallet($conn, $userId);
        if (!$wallet || (int)($wallet['id'] ?? 0) <= 0) {
            throw new Exception('Create your wallet before setting a Wallet PIN');
        }

        $pinHashSafe = mysqli_real_escape_string($conn, password_hash($pin, PASSWORD_DEFAULT));
        mysqli_begin_transaction($conn);
        try {
            $updates = ["wallet_pin_hash = '$pinHashSafe'"];
            if (nivasityUsersHasWalletPinUpdatedAtColumn($conn)) {
                $updates[] = 'wallet_pin_updated_at = NOW()';
            }
            $updateSql = 'UPDATE users SET ' . implode(', ', $updates) . " WHERE id = $userId LIMIT 1";
            if (!mysqli_query($conn, $updateSql)) {
                throw new Exception('Failed to save Wallet PIN: ' . mysqli_error($conn));
            }
            mysqli_query($conn, "DELETE FROM wallet_pin_tokens WHERE user_id = $userId");
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            throw $e;
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Wallet PIN saved successfully.',
            'data' => [
                'status' => 'saved',
                'has_pin' => true,
            ],
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