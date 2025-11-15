<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = 'failed';

$user_id = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
$role = isset($_SESSION['nivas_userRole']) ? $_SESSION['nivas_userRole'] : null;

// Collect form data
$new_institution = isset($_POST['new_institution']) ? $_POST['new_institution'] : '';
$new_adm_year = isset($_POST['new_adm_year']) ? $_POST['new_adm_year'] : '';
$new_department = isset($_POST['new_department']) ? $_POST['new_department'] : '';
$new_matric_no = isset($_POST['new_matric_no']) ? $_POST['new_matric_no'] : '';
$messageBody = isset($_POST['message']) ? $_POST['message'] : '';

$message = "School: $new_institution \n\nAdmission year: $new_adm_year \n\nDepartment: $new_department \n\nMatric number: $new_matric_no \n\n" . $messageBody;

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

  $picture = isset($_FILES['attachment']['name']) ? $_FILES['attachment']['name'] : '';
  $tempname = isset($_FILES['attachment']['tmp_name']) ? $_FILES['attachment']['tmp_name'] : '';
  $storedFilePath = null;
  $mimeType = isset($_FILES['attachment']['type']) ? $_FILES['attachment']['type'] : null;
  $fileSize = isset($_FILES['attachment']['size']) ? (int)$_FILES['attachment']['size'] : null;

  if ($picture && $tempname) {
    $extension = pathinfo($picture, PATHINFO_EXTENSION);
    $safeExtension = $extension ? preg_replace('/[^a-zA-Z0-9]/', '', $extension) : '';
    $storedName = "support_{$user_id}_{$uniqueCode}";
    if ($safeExtension !== '') {
      $storedName .= "." . $safeExtension;
    }

    $uploadDir = "../assets/images/supports/";
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $storedName;

    if (move_uploaded_file($tempname, $destination)) {
      $storedFilePath = "assets/images/supports/" . $storedName;
    } else {
      $storedFilePath = null;
      $mimeType = null;
      $fileSize = null;
    }
  }

  if ($storedFilePath) {
    // Send email to support
    $supportEmail = 'support@nivasity.com';
    $subject = "Important: Academic Info Change Request - Ticket #$uniqueCode";
    $supportMessage = "User: $first_name (User id: $user_id)<br>Email: $userEmail<br>Message: $message";

    // Send confirmation email to the user
    $userSubject = "Support Request Received - Ticket #$uniqueCode";
    $userMessage = "Hi $first_name,<br><br>Thank you for reaching out to us. Your academic info change request has been received, and a support ticket has been generated with the reference number #$uniqueCode. <br>Our team will get back to you within 24 working hours.<br><br>Best regards,<br>Support Team<br>Nivasity";

    $mailStatus = sendMail($subject, $supportMessage, $supportEmail);

    // Check the status
    if ($mailStatus === "success") {
      $mailStatus2 = sendMail($userSubject, $userMessage, $userEmail);

      // Check the status 2
      if ($mailStatus2 === "success") {
        // Get current time in the desired format
        $currentDateTime = date("Y-m-d H:i:s");

        $subjectDb = mysqli_real_escape_string($conn, 'Academic Info Change Request');
        $messageDb = mysqli_real_escape_string($conn, $message);

        // Create ticket in v2 table
        $ticketSql = "
          INSERT INTO support_tickets_v2 (code, subject, user_id, status, priority, category, assigned_admin_id, last_message_at, created_at)
          VALUES ('$uniqueCode', '$subjectDb', $user_id, 'open', 'medium', 'Academic', NULL, '$currentDateTime', '$currentDateTime')
        ";

        if (mysqli_query($conn, $ticketSql)) {
          $ticketId = (int)mysqli_insert_id($conn);

          // Initial message
          $messageSql = "
            INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at)
            VALUES ($ticketId, 'user', $user_id, NULL, '$messageDb', 0, '$currentDateTime')
          ";

          if (mysqli_query($conn, $messageSql)) {
            $messageId = (int)mysqli_insert_id($conn);

            // Attach proof file
            $filePathDb = mysqli_real_escape_string($conn, $storedFilePath);
            $fileNameDb = mysqli_real_escape_string($conn, $picture);
            $mimeTypeDb = $mimeType ? mysqli_real_escape_string($conn, $mimeType) : null;
            $fileSizeDb = is_int($fileSize) ? $fileSize : null;

            $attachmentSql = "
              INSERT INTO support_ticket_attachments (message_id, file_path, file_name, mime_type, file_size)
              VALUES ($messageId, '$filePathDb', '$fileNameDb', " .
                ($mimeTypeDb ? "'$mimeTypeDb'" : "NULL") . ", " .
                ($fileSizeDb !== null ? $fileSizeDb : "NULL") . ")
            ";

            mysqli_query($conn, $attachmentSql);

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
    $messageRes = "Couldn't upload file. Please try again later!";
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
