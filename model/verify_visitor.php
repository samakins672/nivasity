<?php
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = 'failed';

if (isset($_POST['verify'])) {
  $code = $_POST['verify'];

  // Check if code data exists
  $verify_query = mysqli_query($conn, "SELECT * FROM verification_code WHERE code = '$code'");

  if (mysqli_num_rows($verify_query) == 1) {
    $user_id = mysqli_fetch_array($verify_query)['user_id'];

    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
    $user = mysqli_fetch_array($user_query);

    if ($user['status'] == 'unverified') {
      mysqli_query($conn, "UPDATE users SET status = 'verified' WHERE id = $user_id");

      if (mysqli_affected_rows($conn) >= 1) {
        $statusRes = "success";
        $messageRes = "Account successfully created!";
      }
    } else {
      $statusRes = "verified";
    }
  } else {
    $statusRes = "failed";
    $messageRes = "This link is wrong or has expired!";
  }
}

$responseData = array(
  "status" => "$statusRes",
  "message" => "$messageRes"
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
?>