<?php
// API: Get User Profile
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/internal_wallet_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
requireStudentRole($user);

$user_id = (int)$user['id'];
$school_id = (int)$user['school'];

// Department name
$dept_name = null;
if ($user['dept']) {
    $dept_query = mysqli_query($conn, "SELECT name FROM depts WHERE id = " . (int)$user['dept']);
    if ($dept_query && mysqli_num_rows($dept_query) > 0) {
        $dept_data = mysqli_fetch_array($dept_query);
        $dept_name = $dept_data['name'];
    }
}

// School name
$school_name = null;
if ($school_id) {
    $school_query = mysqli_query($conn, "SELECT name FROM schools WHERE id = $school_id LIMIT 1");
    if ($school_query && mysqli_num_rows($school_query) > 0) {
        $school_name = mysqli_fetch_array($school_query)['name'];
    }
}

// Seller level: 2 if approved verification exists, 1 otherwise
$level = 1;
$seller_check = mysqli_query($conn, "SELECT id FROM marketplace_seller_verifications WHERE user_id = $user_id AND status = 'approved' LIMIT 1");
if ($seller_check && mysqli_num_rows($seller_check) > 0) {
    $level = 2;
}

// Wallet provisioned
$wallet_provisioned = false;
$wallet = nivasityGetUserWallet($conn, $user_id);
if ($wallet && isset($wallet['id'])) {
    $wallet_provisioned = true;
}

// Phone verified
$phone_verified = !empty($user['phone']) && ($user['phone_verified'] ?? 0) == 1;

// Email verified
$email_verified = $user['status'] !== 'unverified';

sendApiSuccess('Profile retrieved successfully', [
    'id'                => $user_id,
    'first_name'        => $user['first_name'],
    'last_name'         => $user['last_name'],
    'email'             => $user['email'],
    'phone'             => $user['phone'],
    'gender'            => $user['gender'],
    'role'              => $user['role'],
    'status'            => $user['status'],
    'profile_pic'       => $user['profile_pic'],
    'matric_no'         => $user['matric_no'] ?? null,
    'dept'              => $user['dept'] ?? null,
    'dept_name'         => $dept_name,
    'adm_year'          => $user['adm_year'] ?? null,
    'school_id'         => $school_id,
    'school_name'       => $school_name,
    // Marketplace fields
    'level'             => $level,
    'wallet_provisioned' => $wallet_provisioned,
    'phone_verified'    => $phone_verified,
    'email_verified'    => $email_verified,
]);
?>
