<?php
// API: Submit seller verification (Student ID + NIN/BVN)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

// Check if already approved
$check_q = mysqli_query($conn, "SELECT status FROM marketplace_seller_verifications WHERE user_id = $user_id LIMIT 1");
if ($check_q && mysqli_num_rows($check_q) > 0) {
    $existing = mysqli_fetch_assoc($check_q);
    if ($existing['status'] === 'approved') {
        sendApiError('You are already a verified seller', 400);
    }
    if ($existing['status'] === 'pending') {
        sendApiError('Your verification is already under review. We will contact you within 24h.', 400);
    }
}

$nin = isset($_POST['nin']) ? sanitizeInput($conn, preg_replace('/\D/', '', trim($_POST['nin']))) : null;
$bvn = isset($_POST['bvn']) ? sanitizeInput($conn, preg_replace('/\D/', '', trim($_POST['bvn']))) : null;

if (empty($nin) && empty($bvn)) {
    sendApiError('Provide at least one of: NIN or BVN', 400);
}
if ($nin && strlen($nin) !== 11) sendApiError('NIN must be exactly 11 digits', 400);
if ($bvn && strlen($bvn) !== 11) sendApiError('BVN must be exactly 11 digits', 400);

// Handle Student ID card upload
$id_card_path = null;
if (isset($_FILES['student_id_card']) && $_FILES['student_id_card']['error'] === UPLOAD_ERR_OK) {
    $file    = $_FILES['student_id_card'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','pdf','webp'];
    if (!in_array($ext, $allowed, true)) {
        sendApiError('ID card must be JPG, PNG, WebP, or PDF', 400);
    }

    $upload_dir = __DIR__ . '/../../../../assets/images/seller-ids/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

    $filename   = 'seller_id_' . $user_id . '_' . time() . '.' . $ext;
    $dest       = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        sendApiError('Failed to upload ID card. Please try again.', 500);
    }
    $id_card_path = 'assets/images/seller-ids/' . $filename;
}

if (!$id_card_path) sendApiError('Student ID card image is required', 400);

$id_card_safe = mysqli_real_escape_string($conn, $id_card_path);
$nin_sql = $nin ? "'$nin'" : 'NULL';
$bvn_sql = $bvn ? "'$bvn'" : 'NULL';

// Insert or update verification request
$existing_q = mysqli_query($conn, "SELECT id FROM marketplace_seller_verifications WHERE user_id = $user_id LIMIT 1");
if ($existing_q && mysqli_num_rows($existing_q) > 0) {
    mysqli_query($conn, "UPDATE marketplace_seller_verifications
                         SET id_card_path = '$id_card_safe', nin = $nin_sql, bvn = $bvn_sql, status = 'pending', reviewed_at = NULL
                         WHERE user_id = $user_id");
} else {
    mysqli_query($conn, "INSERT INTO marketplace_seller_verifications (user_id, id_card_path, nin, bvn, status)
                         VALUES ($user_id, '$id_card_safe', $nin_sql, $bvn_sql, 'pending')");
}

if (mysqli_affected_rows($conn) === 0) sendApiError('Failed to submit verification', 500);

sendApiSuccess("Verification submitted — we'll review within 24h");
?>
