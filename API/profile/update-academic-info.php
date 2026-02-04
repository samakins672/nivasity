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
$dept_id = isset($input['dept_id']) ? (int)$input['dept_id'] : $user['dept'];
$matric_no = isset($input['matric_no']) ? sanitizeInput($conn, $input['matric_no']) : $user['matric_no'];
$adm_year = isset($input['adm_year']) ? sanitizeInput($conn, $input['adm_year']) : $user['adm_year'];

// Validate department if changing
if ($dept_id && $dept_id !== $user['dept']) {
    // Validate department exists, is active, and belongs to user's school
    $dept_check = mysqli_query($conn, "SELECT id FROM depts WHERE id = $dept_id AND school_id = {$user['school']} AND status = 'active'");
    if (mysqli_num_rows($dept_check) === 0) {
        sendApiError('Invalid dept_id. Department does not exist, is not active, or does not belong to your school.', 400);
    }
}

// Update academic information
$dept_sql = $dept_id ? $dept_id : "NULL";
mysqli_query($conn, "UPDATE users SET dept = $dept_sql, matric_no = '$matric_no', adm_year = '$adm_year' WHERE id = $user_id");

if (mysqli_affected_rows($conn) >= 0) {
    // Fetch updated user data
    $updated_user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
    
    $academicData = [
        'dept_id' => $updated_user['dept'] ? (int)$updated_user['dept'] : null,
        'matric_no' => $updated_user['matric_no'],
        'adm_year' => $updated_user['adm_year']
    ];
    
    sendApiSuccess('Academic information successfully updated!', $academicData);
} else {
    sendApiError('Internal Server Error. Please try again later!', 500);
}
?>
