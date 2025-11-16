<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = 'failed';

$user_id = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
$role = isset($_SESSION['nivas_userRole']) ? $_SESSION['nivas_userRole'] : null;

// Collect form data
$business_name = isset($_POST['business_name']) ? $_POST['business_name'] : '';
$business_address = isset($_POST['business_address']) ? $_POST['business_address'] : '';
$web_url = isset($_POST['web_url']) ? $_POST['web_url'] : '';
$work_email = isset($_POST['work_email']) ? $_POST['work_email'] : '';
$socials = isset($_POST['socials']) ? $_POST['socials'] : '';
$messageBody = isset($_POST['message']) ? $_POST['message'] : '';

$message = "Business name: $business_name \n\Business address: $business_address \n\Business Website: $web_url \n\Work Email: $work_email \n\Socials: $socials \n\nMessage:" . $messageBody;

if ($user_id && $messageBody !== '') {
  $user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
  $first_name = $user['first_name'];
  $last_name = $user['last_name'];
  $userEmail = $user['email'];

  // Generate a unique code
  $uniqueCode = generateVerificationCode(8);

  // Check if the code already exists, regenerate if needed
  while (!isCodeUnique($uniqueCode, $conn, 'support_tickets_v2')) {
    $uniqueCode = generateVerificationCode(8);
  }

  // Send email to support
  $supportEmail = 'support@nivasity.com';
  $subject = "Important: Organisation Info Change Request - Ticket #$uniqueCode";
  $supportMessage = "User: $first_name (User id: $user_id)<br>Email: $userEmail<br>Information: $message";

  // Send confirmation email to the user
  $userSubject = "Support Request Received - Ticket #$uniqueCode";
  $userMessage = "Hi $first_name,<br><br>Thank you for reaching out to us. Your organisation info change request has been received, and a support ticket has been generated with the reference number #$uniqueCode. <br>Our team will get back to you within 24 working hours.<br><br>Best regards,<br>Support Team<br>Nivasity";

  // Use Brevo for support notifications (reply from support goes to the user)
  $mailStatus = sendBrevoMail($subject, $supportMessage, $supportEmail, $userEmail);
  if ($mailStatus !== "success") {
    $mailStatus = sendMail($subject, $supportMessage, $supportEmail);
  }

  // Check the status
  if ($mailStatus === "success") {
  $mailStatus2 = sendBrevoMail($userSubject, $userMessage, $userEmail, $supportEmail);
  if ($mailStatus2 !== "success") {
    $mailStatus2 = sendMail($userSubject, $userMessage, $userEmail);
  }

    // Check the status 2
    if ($mailStatus2 === "success") {
      // Get current time in the desired format
      $currentDateTime = date("Y-m-d H:i:s");

      $subjectDb = mysqli_real_escape_string($conn, 'Organisation Info Change Request');
      $messageDb = mysqli_real_escape_string($conn, $message);

      // Create ticket in v2 table
      $ticketSql = "
        INSERT INTO support_tickets_v2 (code, subject, user_id, status, priority, category, assigned_admin_id, last_message_at, created_at)
        VALUES ('$uniqueCode', '$subjectDb', $user_id, 'open', 'medium', 'Organisation', NULL, '$currentDateTime', '$currentDateTime')
      ";

      if (mysqli_query($conn, $ticketSql)) {
        $ticketId = (int)mysqli_insert_id($conn);

        // Initial message
        $messageSql = "
          INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at)
          VALUES ($ticketId, 'user', $user_id, NULL, '$messageDb', 0, '$currentDateTime')
        ";

        if (mysqli_query($conn, $messageSql)) {
          $statusRes = "success";
          $messageRes = "Request successfully sent!";
        } else {
          $statusRes = "error";
          $messageRes = "Could not save ticket message. Please try again later!";
        }
      } else {
        $statusRes = "error";
        $messageRes = "Could not create support ticket. Please try again later!";
      }
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  } else {
    $statusRes = "error";
    $messageRes = "Couldn't send email. Please try again later!";
  }
} else {
  $statusRes = "error";
  $messageRes = "Invalid request. Please try again.";
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
