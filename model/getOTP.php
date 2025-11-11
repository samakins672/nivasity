<?php
session_start();
require_once 'config.php';
require_once 'mail.php';

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

if (isset($_POST['getOtp'])) {
  $password = null;

  // Helper: send JSON and exit
  function respond($status, $message, $reference = null, $otp = null)
  {
    echo json_encode([
      'status' => $status,
      'message' => $message,
      'reference' => $reference,
      'otp' => $otp,
    ]);
    exit();
  }

  if ($_POST['getOtp'] == 'get') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);

    // Check if the user exists in the database
    $result = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
    $userData = mysqli_fetch_assoc($result);

    if (!$userData) {
      respond('error', 'Email not found!', null, null);
    }

    // Generate a numeric OTP (6 digits) and expiration (10 minutes)
    $otp = rand(100000, 999999);
    $exp_date = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $user_id = (int)$userData['id'];

    // Remove any existing codes for the user
    mysqli_query($conn, "DELETE FROM verification_code WHERE user_id = $user_id");

    // Insert the new code
    $ins = mysqli_query($conn, "INSERT INTO verification_code (user_id, code, exp_date) VALUES ($user_id, '$otp', '$exp_date')");

    if (!$ins) {
      respond('error', 'Failed to generate OTP. Please try again.');
    }

    // Send email to user with OTP
    $subject = 'Password reset code';
    $body = "Hello {$userData['first_name']},<br><br>Use the code <strong>$otp</strong> to reset your password. This code expires in 10 minutes.<br><br>-- Nivasity";

    // Prefer Brevo if configured, fallback to SMTP PHPMailer
    $mailStatus = sendBrevoMail($subject, $body, $email);
    if ($mailStatus !== 'success') {
      // fallback to standard mail sender
      $mailStatus = sendMail($subject, $body, $email);
    }

    if ($mailStatus !== 'success') {
      // don't expose internal failures; keep message generic
      respond('error', 'Failed to send OTP email. Please contact support.');
    }

    // Store reset identification in session (user id)
    $_SESSION['reset_user_id'] = $user_id;

    // Return user id as reference so the frontend can pass it back for verification
    respond('success', 'OTP sent to your email.', $user_id, null);

  } else {
    // Verification path: user submits otp and new password
    $password_raw = $_POST['password'] ?? null;
    $otp = mysqli_real_escape_string($conn, $_POST['otp'] ?? '');
    $ref = intval($_POST['ref'] ?? 0);

    if (!$password_raw || !$otp || !$ref) {
      respond('error', 'Missing parameters.');
    }

    // Check verification code in DB
    $now = date('Y-m-d H:i:s');
    $q = mysqli_query($conn, "SELECT * FROM verification_code WHERE user_id = $ref AND code = '$otp' AND exp_date >= '$now' LIMIT 1");
    $row = mysqli_fetch_assoc($q);

    if (!$row) {
      respond('error', 'Incorrect or expired OTP.');
    }

    // Update password (store hashed). Keep existing md5 usage to avoid breaking other logic
    $password_hashed = md5($password_raw);
    $update = mysqli_query($conn, "UPDATE users SET password = '$password_hashed' WHERE id = $ref");

    if (!$update) {
      respond('error', 'Failed to update password.');
    }

    // Remove used OTP
    mysqli_query($conn, "DELETE FROM verification_code WHERE user_id = $ref");

    // Cleanup session if set
    if (isset($_SESSION['reset_user_id'])) unset($_SESSION['reset_user_id']);

    respond('success', 'Password updated successfully.');
  }
}

?>
