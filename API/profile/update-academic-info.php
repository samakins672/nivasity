<?php
// API: Update Academic Information
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

$user_id = $user['id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Get academic fields (all optional, but at least one should be provided)
$user_school_id = (int)($user['school'] ?? 0);
$dept_id = isset($input['dept_id']) ? (int)$input['dept_id'] : (int)($user['dept'] ?? 0);
$matric_no = isset($input['matric_no']) ? sanitizeInput($conn, trim((string)$input['matric_no'])) : ($user['matric_no'] ?? '');
// A matric number can be changed but never cleared (students need it for receipts and exports)
if (isset($input['matric_no']) && trim((string) $input['matric_no']) === '') {
    sendApiError('Enter your matric number.', 400);
}
$raw_adm_year = isset($input['adm_year']) ? sanitizeInput($conn, $input['adm_year']) : ($user['adm_year'] ?? '');
$adm_year = trim((string)$raw_adm_year);
if (preg_match('/^(\d{4})$/', $adm_year, $m)) {
    $adm_year = $m[1] . '/' . ((int)$m[1] + 1);
}

// Validate department if changing
if ($dept_id && $dept_id !== (int)($user['dept'] ?? 0)) {
    // Validate department exists, is active, and belongs to user's school
    $dept_check = mysqli_query($conn, "SELECT id FROM depts WHERE id = $dept_id AND school_id = $user_school_id AND status = 'active'");
    if (!$dept_check || mysqli_num_rows($dept_check) === 0) {
        sendApiError('Invalid dept_id. Department does not exist, is not active, or does not belong to your school.', 400);
    }
}

$normalized_matric = strtolower(trim((string) $matric_no));
if ($normalized_matric !== '') {
    $normalized_matric_sql = mysqli_real_escape_string($conn, $normalized_matric);
    $duplicate_check = mysqli_query(
        $conn,
        "SELECT id, email
         FROM users
         WHERE id != $user_id
           AND school = $user_school_id
           AND status = 'verified'
           AND LOWER(TRIM(matric_no)) = '$normalized_matric_sql'
         LIMIT 1"
    );

    if (!$duplicate_check) {
        sendApiError('Internal Server Error. Please try again later!', 500);
    }

    if (mysqli_num_rows($duplicate_check) > 0) {
        // Usually the student's own older account (where their purchases are). Point them to it
        // with a partly hidden email instead of a dead end.
        $existing = mysqli_fetch_assoc($duplicate_check);
        $hint = maskEmailForHint($existing['email'] ?? '');
        sendApiResponse(
            'error',
            'This matric number is already on another Nivasity account' . ($hint !== '' ? " ($hint)" : '') . '. '
                . 'If that account is yours, sign in to it instead: your purchases are there. Forgot its password? Use "Forgot password". '
                . 'If it is not yours, contact support with your student ID card.',
            ['code' => 'matric_taken', 'existing_account_hint' => $hint],
            409
        );
    }
}

// Update academic information
$dept_sql = $dept_id > 0 ? $dept_id : "NULL";
mysqli_query($conn, "UPDATE users SET dept = $dept_sql, matric_no = '$matric_no', adm_year = '$adm_year' WHERE id = $user_id");

if (mysqli_affected_rows($conn) >= 0) {
    // Fetch updated user data
    $updated_user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
    $updated_dept_id = $updated_user['dept'] ? (int)$updated_user['dept'] : null;
    $dept_name = null;
    if ($updated_dept_id > 0) {
        $d_res = mysqli_query($conn, "SELECT name FROM depts WHERE id = $updated_dept_id LIMIT 1");
        if ($d_res && mysqli_num_rows($d_res) > 0) {
            $dept_name = mysqli_fetch_assoc($d_res)['name'];
        }
    }
    
    $academicData = [
        'dept_id' => $updated_dept_id,
        'dept_name' => $dept_name,
        'matric_no' => $updated_user['matric_no'],
        'adm_year' => $updated_user['adm_year']
    ];
    
    sendApiSuccess('Academic information successfully updated!', $academicData);
} else {
    sendApiError('Internal Server Error. Please try again later!', 500);
}
?>
