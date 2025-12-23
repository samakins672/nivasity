<?php
// API: Get User Profile
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

// Get department name if exists
$dept_name = null;
if ($user['dept']) {
    $dept_query = mysqli_query($conn, "SELECT name FROM depts WHERE id = " . (int)$user['dept']);
    if ($dept_query && mysqli_num_rows($dept_query) > 0) {
        $dept_data = mysqli_fetch_array($dept_query);
        $dept_name = $dept_data['name'];
    }
}

// Prepare profile data
$profileData = [
    'id' => $user['id'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'email' => $user['email'],
    'phone' => $user['phone'],
    'gender' => $user['gender'],
    'role' => $user['role'],
    'status' => $user['status'],
    'profile_pic' => $user['profile_pic'],
    'matric_no' => $user['matric_no'] ?? null,
    'dept' => $user['dept'] ?? null,
    'dept_name' => $dept_name,
    'adm_year' => $user['adm_year'] ?? null,
    'school' => $user['school']
];

sendApiSuccess('Profile retrieved successfully', $profileData);
?>
