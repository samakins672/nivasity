<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = 'failed';

$user_id = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;

if ($user_id && isset($_POST['support_id'])) {
  // Collect form data
  $subject = isset($_POST['subject']) ? mysqli_real_escape_string($conn, $_POST['subject']) : '';
  $message = isset($_POST['message']) ? mysqli_real_escape_string($conn, $_POST['message']) : '';

  if ($subject !== '' && $message !== '') {
    $user = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
    $first_name = $user['first_name'];
    $last_name = $user['last_name'];
    $userEmail = $user['email'];

    // Generate a unique public ticket code
    $uniqueCode = generateVerificationCode(8);

    // Ensure the code is unique in the new tickets table
    while (!isCodeUnique($uniqueCode, $conn, 'support_tickets_v2')) {
      $uniqueCode = generateVerificationCode(8);
    }

    // Handle optional attachment for the first message
    $storedFilePath = null;
    $originalFileName = null;
    $mimeType = null;
    $fileSize = null;

    if (!empty($_FILES['attachment']['name'])) {
      $originalFileName = $_FILES['attachment']['name'];
      $tempName = $_FILES['attachment']['tmp_name'];
      $mimeType = isset($_FILES['attachment']['type']) ? $_FILES['attachment']['type'] : null;
      $fileSize = isset($_FILES['attachment']['size']) ? (int)$_FILES['attachment']['size'] : null;
      $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);

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

      if (move_uploaded_file($tempName, $destination)) {
        // Save relative path for web use
        $storedFilePath = "assets/images/supports/" . $storedName;
      } else {
        $originalFileName = null;
        $mimeType = null;
        $fileSize = null;
        $storedFilePath = null;
      }
    }

    // Send email to support
    $supportEmail = 'support@nivasity.com';
    $supportSubject = "Important: New Support Request - Ticket #$uniqueCode";
    $e_message = str_replace('\r\n', '<br>', $message);

    $attachmentInfo = $storedFilePath
      ? "<br><br>File attached: <a href='https://funaab.nivasity.com/{$storedFilePath}'>https://funaab.nivasity.com/{$storedFilePath}</a>"
      : '';

    $supportMessage = "User: $first_name (User id: $user_id)<br>Email: <a href='mailto:$userEmail'>$userEmail</a><br>Message: <br>$e_message{$attachmentInfo}";

    // Send confirmation email to the user
    $userSubject = "Support Request Received - Ticket #$uniqueCode";
    $userMessage = "Hi $first_name,<br><br>Thank you for reaching out to us. Your support request has been received, and a ticket has been generated with the <b>reference code #$uniqueCode.</b> <br>Our team will get back to you very soon.<br><br>Best regards,<br>Support Team<br><b>Nivasity</b>";

    $mailStatus = sendMail($supportSubject, $supportMessage, $supportEmail);

    // Check the status
    if ($mailStatus === "success") {
      $mailStatus2 = sendMail($userSubject, $userMessage, $userEmail);

      // Check the status 2
      if ($mailStatus2 === "success") {
        // Get current time in the desired format
        $currentDateTime = date("Y-m-d H:i:s");

        // Create the ticket shell
        $ticketSql = "
          INSERT INTO support_tickets_v2 (code, subject, user_id, status, priority, category, assigned_admin_id, last_message_at, created_at)
          VALUES ('$uniqueCode', '$subject', $user_id, 'open', 'medium', NULL, NULL, '$currentDateTime', '$currentDateTime')
        ";
        if (mysqli_query($conn, $ticketSql)) {
          $ticketId = (int)mysqli_insert_id($conn);

          // First message in the conversation
          $messageSql = "
            INSERT INTO support_ticket_messages (ticket_id, sender_type, user_id, admin_id, body, is_internal, created_at)
            VALUES ($ticketId, 'user', $user_id, NULL, '$message', 0, '$currentDateTime')
          ";
          if (mysqli_query($conn, $messageSql)) {
            $messageId = (int)mysqli_insert_id($conn);

            // Optional attachment linked to this message
            if ($storedFilePath && $originalFileName) {
              $filePathDb = mysqli_real_escape_string($conn, $storedFilePath);
              $fileNameDb = mysqli_real_escape_string($conn, $originalFileName);
              $mimeTypeDb = $mimeType ? mysqli_real_escape_string($conn, $mimeType) : null;
              $fileSizeDb = is_int($fileSize) ? $fileSize : null;

              $attachmentSql = "
                INSERT INTO support_ticket_attachments (message_id, file_path, file_name, mime_type, file_size)
                VALUES ($messageId, '$filePathDb', '$fileNameDb', " .
                  ($mimeTypeDb ? "'$mimeTypeDb'" : "NULL") . ", " .
                  ($fileSizeDb !== null ? $fileSizeDb : "NULL") . ")
              ";
              mysqli_query($conn, $attachmentSql);
            }

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
    $messageRes = "Subject and message are required.";
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
