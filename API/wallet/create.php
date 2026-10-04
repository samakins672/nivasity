<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$providedPhone = trim((string)($input['phone'] ?? ''));

if ($providedPhone !== '') {
    $normalized = nivasityNormalizePhoneNumber($providedPhone);
    if (strlen($normalized) >= 10 && strlen($normalized) <= 15) {
        $cleanPhone = mysqli_real_escape_string($conn, $normalized);
        mysqli_query($conn, "UPDATE users SET phone = '$cleanPhone' WHERE id = " . (int)$user['id']);
        $user['phone'] = $normalized;
    }
}

try {
    $result = nivasityCreateWalletOnRequest($conn, (int)$user['id'], 'api');
    sendApiSuccess(
        $result['status'] === 'created' ? 'Wallet created successfully' : 'Wallet already exists',
        [
            'created' => $result['status'] === 'created',
            'wallet' => $result['wallet'],
        ]
    );
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 422);
}
