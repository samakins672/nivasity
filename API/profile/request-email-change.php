<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/email_change_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

validateRequiredFields(['new_email'], $input);

try {
    $result = nivasityDispatchEmailChangeOtp($conn, (int) $user['id'], (string) $input['new_email']);
    sendApiSuccess('OTP sent to your new email address. Please check your inbox.', $result);
} catch (Throwable $e) {
    sendApiError($e->getMessage(), 400);
}
?>