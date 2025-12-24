<?php
// API: Update User Profile
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

// Handle multipart form data (for file upload)
$firstname = isset($_POST['firstname']) ? sanitizeInput($conn, $_POST['firstname']) : $user['first_name'];
$lastname = isset($_POST['lastname']) ? sanitizeInput($conn, $_POST['lastname']) : $user['last_name'];
$phone = isset($_POST['phone']) ? sanitizeInput($conn, $_POST['phone']) : $user['phone'];
$matric_no = isset($_POST['matric_no']) ? sanitizeInput($conn, $_POST['matric_no']) : $user['matric_no'];
$adm_year = isset($_POST['adm_year']) ? sanitizeInput($conn, $_POST['adm_year']) : $user['adm_year'];

// Handle school_id if provided
$school_id = isset($_POST['school_id']) ? (int)$_POST['school_id'] : $user['school'];
if ($school_id !== $user['school']) {
    // Validate new school exists and is active
    $school_check = mysqli_query($conn, "SELECT id FROM schools WHERE id = $school_id AND status = 'active'");
    if (mysqli_num_rows($school_check) === 0) {
        sendApiError('Invalid school_id. School does not exist or is not active.', 400);
    }
}

// Handle dept_id if provided
$dept_id = isset($_POST['dept_id']) ? (int)$_POST['dept_id'] : $user['dept'];
if ($dept_id && $dept_id !== $user['dept']) {
    // Validate department exists, is active, and belongs to the school
    $dept_check = mysqli_query($conn, "SELECT id FROM depts WHERE id = $dept_id AND school_id = $school_id AND status = 'active'");
    if (mysqli_num_rows($dept_check) === 0) {
        sendApiError('Invalid dept_id. Department does not exist, is not active, or does not belong to the specified school.', 400);
    }
}

$picture = $user['profile_pic'];

// Handle profile picture upload
if (isset($_FILES['upload']) && $_FILES['upload']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['upload']['name'];
    $tempname = $_FILES['upload']['tmp_name'];
    $extension = pathinfo($uploadedFile, PATHINFO_EXTENSION);
    
    // Validate file type
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array(strtolower($extension), $allowedExtensions)) {
        sendApiError('Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.', 400);
    }
    
    $picture = "user" . time() . "." . $extension;
    $destination = __DIR__ . "/../../assets/images/users/{$picture}";
    
    // Delete old picture if not default
    if ($user['profile_pic'] !== 'user.jpg') {
        $old_pic_path = __DIR__ . "/../../assets/images/users/{$user['profile_pic']}";
        if (file_exists($old_pic_path)) {
            unlink($old_pic_path);
        }
    }
    
    if (!move_uploaded_file($tempname, $destination)) {
        sendApiError('Failed to upload profile picture.', 500);
    }
}

// Update user profile
mysqli_query($conn, "UPDATE users SET first_name = '$firstname', last_name = '$lastname', profile_pic = '$picture', phone = '$phone', school = $school_id, dept = " . ($dept_id ? $dept_id : "NULL") . ", matric_no = '$matric_no', adm_year = '$adm_year' WHERE id = $user_id");

if (mysqli_affected_rows($conn) >= 0) {
    // Fetch updated user data
    $updated_user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
    
    $profileData = [
        'id' => $updated_user['id'],
        'first_name' => $updated_user['first_name'],
        'last_name' => $updated_user['last_name'],
        'email' => $updated_user['email'],
        'phone' => $updated_user['phone'],
        'gender' => $updated_user['gender'],
        'profile_pic' => $updated_user['profile_pic'],
        'school_id' => (int)$updated_user['school'],
        'dept_id' => $updated_user['dept'] ? (int)$updated_user['dept'] : null,
        'matric_no' => $updated_user['matric_no'],
        'adm_year' => $updated_user['adm_year']
    ];
    
    sendApiSuccess('Profile successfully updated!', $profileData);
} else {
    sendApiError('Internal Server Error. Please try again later!', 500);
}
?>
