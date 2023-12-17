<?php
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = 'failed';

if (isset($_POST['signup'])) {
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $password = md5($_POST['password']);

  $user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

  if (mysqli_num_rows($user_query) >= 1) {
    $statusRes = "denied";
    $messageRes = "A user has been associated with this email. <br> Please try again with another email!";
  } else {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $gender = mysqli_real_escape_string($conn, $_POST['gender']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $school = mysqli_real_escape_string($conn, $_POST['school']);

    mysqli_query($conn, "INSERT INTO users (first_name, last_name, email, phone, password, role, school, gender) 
      VALUES ('$first_name', '$last_name', '$email', '$phone', '$password', '$role', $school, '$gender')");

    $user_id = mysqli_insert_id($conn);
    
    if (mysqli_affected_rows($conn) < 1) {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    } else {

      // Generate a unique verification code
      $verificationCode = generateVerificationCode();

      // Check if the code already exists, regenerate if needed
      while (!isCodeUnique($verificationCode, $conn)) {
        $verificationCode = generateVerificationCode();
      }

      mysqli_query($conn, "INSERT INTO verification_code (user_id, code) VALUES ($user_id, '$verificationCode')");

      $subject = "Verify Your Account on NIVASITY";
      $body = "Hello $first_name,
      <br><br>
      Welcome to Nivasity! We're excited to have you on board. To ensure the security of your account and to provide you with the best experience, we kindly ask you to verify your email address.
      <br><br>
      Click on the following link to verify your account: <a href='https://nivasity.com/setup.html?verify=$verificationCode'>Verify Account</a>
      <br>If you are unable to click on the link, please copy and paste the following URL into your browser: https://nivasity.com/setup.html?verify=$verificationCode
      <br><br>
      Thank you for choosing Nivasity. We look forward to serving you!
      <br><br>
      Best regards,
      <br>The Nivasity Team";

      // Call the sendMail function and capture the status
      $mailStatus = sendMail($subject, $body, $email);

      // Check the status
      if ($mailStatus === "success") {
        $statusRes = "success";
        $messageRes = "Great news! You're one step away from completing your signup.We've sent an account verification link to your email address. <br><br>Please check your inbox (and your spam folder, just in case) for an email from us. Click on the verification link to confirm your account and gain full access.";
      } else {
        $statusRes = "error";
        $messageRes = "Internal Server Error. Please try again later!";
      }
    }
  }
}

if (isset($_POST['setup'])) {
  $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
  $dept = mysqli_real_escape_string($conn, $_POST['dept']);
  $adm_year = mysqli_real_escape_string($conn, $_POST['adm_year']);
  $matric_no = mysqli_real_escape_string($conn, $_POST['matric_no']);
  $school_id = mysqli_real_escape_string($conn, $_POST['school_id']);

  $user_query = mysqli_query($conn, "SELECT * FROM users WHERE matric_no = '$matric_no' AND school = '$school_id'");
  
  if (mysqli_num_rows($user_query) < 1) {
    if (!is_numeric($dept)) {
      mysqli_query($conn, "INSERT INTO depts_$school_id (name) VALUES ('$dept')");
      $dept = mysqli_insert_id($conn);
    }

    mysqli_query($conn, "UPDATE users SET dept = '$dept', adm_year = '$adm_year', matric_no = '$matric_no', status = 'active' WHERE id = $user_id");
    
    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Account successfully created!.";
    }
  } else {
    $statusRes = "denied";
    $messageRes = "Matric Number has been used by another user!";
  }
}

if (isset($_POST['login'])) {
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $password = md5($_POST['password']);

  // Check if user data exists
  $user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email' AND password = '$password'");

  if (mysqli_num_rows($user_query) == 1) {
    session_start();
    $user = mysqli_fetch_array($user_query);
    if ($user['status'] == 'unverified') {
      $statusRes = "unverified";
      $messageRes = "Your email is unverified. <br>Please check your mail inbox for the verification link.";
    } else if ($user['status'] == 'denied') {
      $statusRes = "denied";
      $messageRes = "Your account is temporarily suspended. Contact our support team for help.";
    } else {
      $_SESSION['nivas_userId'] = $user['id'];
      $_SESSION['nivas_userName'] = $user['first_name'];
      $_SESSION['nivas_userRole'] = $user['role'];

      $statusRes = "success";
      $messageRes = "Great news! You've successfully logged into your account. Welcome back!";
    }
  } else {
    $statusRes = "failed";
    $messageRes = "Oops! Login failed. Please make sure you've entered the correct username and password";
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