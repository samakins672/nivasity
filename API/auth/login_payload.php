<?php
// Shared by sign-in endpoints that log a student straight in (quick login, account setup):
// the same user fields + tokens as /auth/login.php returns.
require_once __DIR__ . '/../jwt.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

if (!function_exists('nivasity_api_login_payload')) {
    function nivasity_api_login_payload($conn, array $user): array
    {
        $userId = (int) $user['id'];
        $tokens = generateTokenPair($user['id'], $user['role'], $user['school']);

        $level = 1;
        $sellerCheck = mysqli_query($conn, "SELECT id FROM marketplace_seller_verifications WHERE user_id = $userId AND status = 'approved' LIMIT 1");
        if ($sellerCheck && mysqli_num_rows($sellerCheck) > 0) {
            $level = 2;
        }
        $wallet = nivasityGetUserWallet($conn, $userId);

        return array_merge([
            'id'                 => $user['id'],
            'first_name'         => $user['first_name'],
            'last_name'          => $user['last_name'],
            'email'              => $user['email'],
            'phone'              => $user['phone'],
            'role'               => $user['role'],
            'gender'             => $user['gender'],
            'status'             => $user['status'],
            'profile_pic'        => $user['profile_pic'],
            'school_id'          => $user['school'],
            'matric_no'          => $user['matric_no'] ?? null,
            'dept'               => $user['dept'] ?? null,
            'adm_year'           => $user['adm_year'] ?? null,
            'level'              => $level,
            'wallet_provisioned' => $wallet && isset($wallet['id']),
            'phone_verified'     => !empty($user['phone']) && ($user['phone_verified'] ?? 0) == 1,
            'email_verified'     => $user['status'] !== 'unverified',
        ], $tokens);
    }
}
