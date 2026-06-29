<?php
// API: Verify Phone Number (send or verify OTP)
// step=send  → send OTP to the provided phone number
// step=verify → verify the submitted OTP and mark phone as verified
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$user_id = (int)$user['id'];

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$step = isset($input['step']) ? strtolower(trim($input['step'])) : '';

if ($step === 'send') {
    // ── Step 1: Send OTP ──────────────────────────────────────────────────────
    if (empty($input['phone'])) {
        sendApiError('Phone number is required', 400);
    }

    $phone_raw = preg_replace('/[^0-9+]/', '', trim($input['phone']));

    // Normalise Nigerian numbers: convert 0xxxxxxxxx → +234xxxxxxxxx
    if (preg_match('/^0(\d{10})$/', $phone_raw, $m)) {
        $phone = '+234' . $m[1];
    } elseif (preg_match('/^234(\d{10})$/', $phone_raw, $m)) {
        $phone = '+234' . $m[1];
    } else {
        $phone = $phone_raw;
    }

    if (strlen($phone) < 10) {
        sendApiError('Invalid phone number format', 400);
    }

    $phone_safe = sanitizeInput($conn, $phone);

    // Generate a 6-digit OTP
    $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', time() + 600); // 10 minutes

    // Invalidate any previous unused OTPs for this user
    mysqli_query($conn, "UPDATE phone_verification_otps SET used = 1 WHERE user_id = $user_id AND used = 0");

    // Insert new OTP
    $otp_safe = sanitizeInput($conn, $otp);
    mysqli_query($conn, "INSERT INTO phone_verification_otps (user_id, phone, otp_code, expires_at) VALUES ($user_id, '$phone_safe', '$otp_safe', '$expires_at')");

    // Send OTP via Meta WhatsApp Cloud API
    require_once __DIR__ . '/../../config/fw.php';

    $wa_token      = defined('WHATSAPP_ACCESS_TOKEN')    ? WHATSAPP_ACCESS_TOKEN    : '';
    $wa_phone_id   = defined('WHATSAPP_PHONE_NUMBER_ID') ? WHATSAPP_PHONE_NUMBER_ID : '';
    $wa_template   = defined('WHATSAPP_OTP_TEMPLATE')    ? WHATSAPP_OTP_TEMPLATE    : 'nivasity_otp';

    if (empty($wa_token) || empty($wa_phone_id)) {
        // Development fallback — log OTP to error log so you can test without credentials
        error_log("[WHATSAPP OTP DEV] user_id=$user_id phone=$phone otp=$otp");
        $sms_sent = true;
    } else {
        // WhatsApp Cloud API requires an approved message template for user-initiated OTPs.
        // Template category: AUTHENTICATION
        // Template body example: "{{1}} is your Nivasity verification code. Valid for 10 minutes."
        // Create it at: business.facebook.com → WhatsApp Manager → Message Templates
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $phone,   // E.164 format: +2348XXXXXXXXX
            'type'              => 'template',
            'template'          => [
                'name'     => $wa_template,
                'language' => ['code' => 'en'],
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $otp],
                        ],
                    ],
                ],
            ],
        ]);

        $url = 'https://graph.facebook.com/v19.0/' . $wa_phone_id . '/messages';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $wa_token,
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response_raw = curl_exec($ch);
        $curl_error   = curl_error($ch);
        $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curl_error) {
            error_log('[WHATSAPP CURL ERROR] ' . $curl_error);
            sendApiError('Could not connect to WhatsApp. Please try again.', 500);
        }

        $response = json_decode($response_raw, true);

        // Meta returns messages[0].id on success (HTTP 200)
        $sms_sent = ($http_code === 200) && !empty($response['messages'][0]['id']);

        if (!$sms_sent) {
            error_log('[WHATSAPP FAILED] HTTP ' . $http_code . ' — ' . $response_raw);
        }
    }

    if (!$sms_sent) {
        sendApiError('Failed to send WhatsApp OTP. Please try again.', 500);
    }

    sendApiSuccess('OTP sent to ' . substr($phone, 0, 7) . '****');

} elseif ($step === 'verify') {
    // ── Step 2: Verify OTP ────────────────────────────────────────────────────
    if (empty($input['otp'])) {
        sendApiError('OTP is required', 400);
    }

    $otp_input = sanitizeInput($conn, trim($input['otp']));

    // Find the latest unused, unexpired OTP for this user
    $otp_query = mysqli_query($conn, "
        SELECT id, phone, otp_code FROM phone_verification_otps
        WHERE user_id = $user_id
          AND used = 0
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$otp_query || mysqli_num_rows($otp_query) === 0) {
        sendApiError('OTP expired or not found. Please request a new one.', 400);
    }

    $otp_row = mysqli_fetch_assoc($otp_query);

    if ($otp_row['otp_code'] !== $otp_input) {
        sendApiError('Incorrect OTP. Please try again.', 400);
    }

    $otp_id   = (int)$otp_row['id'];
    $verified_phone = $otp_row['phone'];
    $verified_phone_safe = sanitizeInput($conn, $verified_phone);

    // Mark OTP as used
    mysqli_query($conn, "UPDATE phone_verification_otps SET used = 1 WHERE id = $otp_id");

    // Update user: set phone + phone_verified = 1
    mysqli_query($conn, "UPDATE users SET phone = '$verified_phone_safe', phone_verified = 1 WHERE id = $user_id");

    // Auto-create wallet if user doesn't have one yet
    try {
        nivasityCreateWalletOnRequest($conn, $user_id, 'phone_verification');
    } catch (Throwable $e) {
        // Non-fatal: wallet may already exist
        error_log('[MARKETPLACE] wallet auto-create skipped for user ' . $user_id . ': ' . $e->getMessage());
    }

    // Return updated user profile fields
    $seller_check = mysqli_query($conn, "SELECT id FROM marketplace_seller_verifications WHERE user_id = $user_id AND status = 'approved' LIMIT 1");
    $level = ($seller_check && mysqli_num_rows($seller_check) > 0) ? 2 : 1;

    $wallet = nivasityGetUserWallet($conn, $user_id);
    $wallet_provisioned = $wallet && isset($wallet['id']);

    $updated_user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id LIMIT 1"));

    sendApiSuccess('Phone verified successfully!', [
        'id'                 => $user_id,
        'first_name'         => $updated_user['first_name'],
        'last_name'          => $updated_user['last_name'],
        'email'              => $updated_user['email'],
        'phone'              => $verified_phone,
        'role'               => $updated_user['role'],
        'gender'             => $updated_user['gender'],
        'status'             => $updated_user['status'],
        'profile_pic'        => $updated_user['profile_pic'],
        'school_id'          => $updated_user['school'],
        'matric_no'          => $updated_user['matric_no'] ?? null,
        'dept'               => $updated_user['dept'] ?? null,
        'adm_year'           => $updated_user['adm_year'] ?? null,
        'level'              => $level,
        'wallet_provisioned' => $wallet_provisioned,
        'phone_verified'     => true,
        'email_verified'     => $updated_user['status'] !== 'unverified',
    ]);

} else {
    sendApiError('Invalid step. Use "send" or "verify".', 400);
}
?>
