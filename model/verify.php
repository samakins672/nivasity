<?php
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = $school_id = $departments = $user_id = $first_name = $role = 'failed';

if (isset($_POST['verify'])) {
  $code = $_POST['verify'];

  // Check if code data exists
  $verify_query = mysqli_query($conn, "SELECT * FROM verification_code WHERE code = '$code'");

  if (mysqli_num_rows($verify_query) == 1) {
    $user_id = mysqli_fetch_array($verify_query)['user_id'];

    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
    $user = mysqli_fetch_array($user_query);

    if ($user['status'] == 'unverified') {
      // Get school and department
      $school_id = $user['school'];
  
      // Retrieve departments with IDs based on school ID
      $departments_query = mysqli_query($conn, "SELECT * FROM depts_$school_id WHERE status = 'active'");
      $departments = array();
  
      while ($department = mysqli_fetch_assoc($departments_query)) {
        $departments[] = array(
          'id' => $department['id'],
          'name' => $department['name']
        );
      }
  
      if ($user['status'] == 'unverified') {
        session_start();
        $_SESSION['nivas_userId'] = $user_id = $user['id'];
        $_SESSION['nivas_userName'] = $first_name = $user['first_name'];
        $_SESSION['nivas_userRole'] = $role = $user['role'];
  
        $statusRes = "success";
      }
    } else {
      $statusRes = "verified";
    }
  } else {
    $statusRes = "failed";
    $messageRes = "Oops! Login failed. Please make sure you've entered the correct username and password";
  }
}

$responseData = array(
  "status" => "$statusRes",
  "school_id" => "$school_id",
  "departments" => $departments,
  "user_id" => "$user_id",
  "first_name" => "$first_name",
  "role" => "$role",
  "message" => "$messageRes"
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
?>