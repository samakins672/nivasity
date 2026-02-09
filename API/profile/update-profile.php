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

$picture = $user['profile_pic'];

// Handle profile picture upload
if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['profile_pic']['name'];
    $tempname = $_FILES['profile_pic']['tmp_name'];
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
mysqli_query($conn, "UPDATE users SET first_name = '$firstname', last_name = '$lastname', profile_pic = '$picture', phone = '$phone' WHERE id = $user_id");

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
