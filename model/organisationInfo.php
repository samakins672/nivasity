<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = 'failed';

$user_id = $_SESSION['nivas_userId'];
$role = $_SESSION['nivas_userRole'];

// Collect form data
$business_name = $_POST['business_name'];
$business_address = $_POST['business_address'];
$web_url = $_POST['web_url'];
$work_email = $_POST['work_email'];
$socials = $_POST['socials'];
$message = "Business name: $business_name \n\Business address: $business_address \n\Business Website: $web_url \n\Work Email: $work_email \n\Socials: $socials \n\nMessage:" . $_POST['message'];

$user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
$first_name = $user['first_name'];
$last_name = $user['last_name'];
$userEmail = $user['email'];

// Generate a unique code
$uniqueCode = generateVerificationCode(8);

// Check if the code already exists, regenerate if needed
while (!isCodeUnique($uniqueCode, $conn, 'support_tickets')) {
  $uniqueCode = generateVerificationCode(8);
}

// Send email to support
$supportEmail = 'support@nivasity.com';
$subject = "Important: Organisation Info Change Request - Ticket #$uniqueCode";
$supportMessage = "User: $first_name (User id: $user_id)<br>Email: $userEmail<br>Information: $message";

// Send confirmation email to the user
$userSubject = "Support Request Received - Ticket #$uniqueCode";
$userMessage = "Hi $first_name,<br><br>Thank you for reaching out to us. Your organisation info change request has been received, and a support ticket has been generated with the reference number #$uniqueCode. <br>Our team will get back to you within 24 working hours.<br><br>Best regards,<br>Support Team<br>Nivasity";

$mailStatus = sendMail($subject, $supportMessage, $supportEmail);

// Check the status
if ($mailStatus === "success") {
  $mailStatus2 = sendMail($userSubject, $userMessage, $userEmail);

  // Check the status 2
  if ($mailStatus2 === "success") {
    // Get current time in the desired format
    $currentDateTime = date("Y-m-d H:i:s");

    mysqli_query($conn, "INSERT INTO support_tickets (subject, code,	user_id,	message, created_at) 
      VALUES ('Organisation Info Change Request', '$uniqueCode',	$user_id,	'$message', '$currentDateTime')");

    $statusRes = "success";
    $messageRes = "Request successfully sent!";
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error. Please try again later!";
  }
} else {
  $statusRes = "error";
  $messageRes = "Couldn't send email. Please try again later!";
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