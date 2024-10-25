<?php
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = 'failed';

if (isset($_POST['setup'])) {
  $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
  $business_name = mysqli_real_escape_string($conn, $_POST['business_name']);
  $business_address = mysqli_real_escape_string($conn, $_POST['business_address']);
  $web_url = mysqli_real_escape_string($conn, $_POST['web_url']);
  $work_email = mysqli_real_escape_string($conn, $_POST['work_email']);
  $socials = mysqli_real_escape_string($conn, $_POST['socials']);

  $organisation_query = mysqli_query($conn, "SELECT * FROM organisation WHERE web_url = '$web_url' OR work_email = '$work_email'");

  if (mysqli_num_rows($organisation_query) < 1) {
    $user_ = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
    $user = mysqli_fetch_array($user_);

    $subject = "New Business Owner waiting to be verified";
    $body = "<b>New Business Owner Information</b><br>Name: ".$user['first_name'].' '. $user['last_name']."<br>Business Name: $business_name<br>Work Email: $work_email<br> User number: ".$user['phone']."<br> User Email: ".$user['email'];

    // Call the sendMail function and capture the status
    $mailStatus = sendMail($subject, $body, 'support@nivasity.com');

    mysqli_query($conn, "UPDATE users SET status = 'inreview' WHERE id = $user_id");

    mysqli_query($conn, "INSERT INTO organisation (user_id, business_name, business_address, web_url, work_email, socials) 
        VALUES ('$user_id', '$business_name', '$business_address', '$web_url', '$work_email', '$socials')");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Account successfully created!";
    }
  } else {
    $statusRes = "denied";
    $messageRes = "Work email or Business website has been used by another person!";
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