<?php
include('config.php');
include('mail.php');
include('functions.php');
$statusRes = $messageRes = $roleRes = 'failed';

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
    if (isset($_POST['school'])) {
      $school = mysqli_real_escape_string($conn, $_POST['school']);

      mysqli_query($conn, "INSERT INTO users (first_name, last_name, email, phone, password, role, school, gender) 
        VALUES ('$first_name', '$last_name', '$email', '$phone', '$password', '$role', $school, '$gender')");
    } else {
      mysqli_query($conn, "INSERT INTO users (first_name, last_name, email, phone, password, role, gender) 
        VALUES ('$first_name', '$last_name', '$email', '$phone', '$password', '$role', '$gender')");
    }

    $user_id = mysqli_insert_id($conn);

    if (mysqli_affected_rows($conn) < 1) {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    } else {

      // Generate a unique verification code
      $verificationCode = generateVerificationCode(12);

      // Check if the code already exists, regenerate if needed
      while (!isCodeUnique($verificationCode, $conn, 'verification_code')) {
        $verificationCode = generateVerificationCode(12);
      }

      mysqli_query($conn, "INSERT INTO verification_code (user_id, code) VALUES ($user_id, '$verificationCode')");

      if ($role == 'org_admin') {
        $verificationCode = "setup_org.html?verify=$verificationCode";
      } elseif ($role == 'visitor') {
        $verificationCode = "verify.html?verify=$verificationCode";
      } else {
        $verificationCode = "setup.html?verify=$verificationCode";
      }

      $subject = "Verify Your Account on NIVASITY";
      $body = "Hello $first_name,
      <br><br>
      Welcome to Nivasity! We're excited to have you on board. To ensure the security of your account and to provide you with the best experience, we kindly ask you to verify your email address.
      <br><br>
      Click on the following link to verify your account: <a href='https://nivasity.com/$verificationCode'>Verify Account</a>
      <br>If you are unable to click on the link, please copy and paste the following URL into your browser: https://nivasity.com/$verificationCode
      <br><br>
      Thank you for choosing Nivasity. We look forward to serving you!
      <br><br>
      Best regards,<br><b>Nivasity Team</b>";

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

if (isset($_POST['edit_profile'])) {
  session_start();
  $user_id = $_SESSION['nivas_userId'];
  $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
  $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
  $phone = mysqli_real_escape_string($conn, $_POST['phone']);
  $picture = $_FILES['upload']['name'];

  if ($picture !== 'user.jpg') {
    $tempname = $_FILES['upload']['tmp_name'];
    $extension = pathinfo($picture, PATHINFO_EXTENSION);
    $picture = "user" . time() . "." . $extension;
    $destination = "../assets/images/users/{$picture}";

    $last_picture = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"))['profile_pic'];

    if ($last_picture !== 'user.jpg') {
      unlink("../assets/images/users/{$last_picture}");
    }
    move_uploaded_file($tempname, $destination);
  }

  mysqli_query($conn, "UPDATE users SET first_name = '$firstname', last_name = '$lastname', profile_pic = '$picture', phone = '$phone' WHERE id = $user_id");

  if (mysqli_affected_rows($conn) >= 1) {
    $statusRes = "success";
    $messageRes = "Profile successfully edited!.";
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error. Please try again later!";
  }
}

if (isset($_POST['change_password'])) {
  session_start();
  $user_id = $_SESSION['nivas_userId'];
  $curr_password = md5($_POST['curr_password']);
  $new_password = md5($_POST['new_password']);

  // Check if user data exists
  $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id AND password = '$curr_password'");

  if (mysqli_num_rows($user_query) == 1) {
    mysqli_query($conn, "UPDATE users SET password = '$new_password' WHERE id = $user_id");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Password successfully changed!.";
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  } else {
    $statusRes = "failed";
    $messageRes = "Oops! your current password is incorrect.";
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
      mysqli_query($conn, "INSERT INTO depts (name, school_id) VALUES ('$dept', $school_id)");
      $dept = mysqli_insert_id($conn);
    }
  
    $user_ = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
    $user = mysqli_fetch_array($user_);
    $status = 'verified';
    if ($user['role'] == 'hoc') {
      $status = 'inreview';

      $user_dept = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM depts WHERE id = $dept AND school_id = $school_id"))['name'];

      $subject = "New HOC waiting to be verified";
      $body = "<b>New HOC Information</b><br>Name: ".$user['first_name'].' '. $user['last_name']."<br>Department: $user_dept<br>Matric Number: $matric_no<br> Phone number: ".$user['phone']."<br> Email: ".$user['email'];

      // Call the sendMail function and capture the status
      $mailStatus = sendMail($subject, $body, 'support@nivasity.com');

      mysqli_query($conn, "UPDATE users SET dept = '$dept', adm_year = '$adm_year', matric_no = '$matric_no', status = '$status' WHERE id = $user_id");
    } else {
      mysqli_query($conn, "UPDATE users SET dept = '$dept', adm_year = '$adm_year', matric_no = '$matric_no', status = '$status' WHERE id = $user_id");
    }


    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Account successfully created!";
    }
  } else {
    $statusRes = "denied";
    $messageRes = "Matric Number has been used by another user!";
  }
}

if (isset($_POST['login'])) {
  $email = mysqli_real_escape_string($conn, $_POST['email']);

  if ($_POST['login'] !== 'g_signin') {
    $password = md5($_POST['password']);

    // Check if user data exists
    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email' AND password = '$password'");
  } else {
    // Check if user data exists
    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");
  } 
  if (mysqli_num_rows($user_query) == 1) {
    session_start();
    $user = mysqli_fetch_array($user_query);
    if ($user['status'] == 'unverified') {
      $statusRes = "unverified";
      $messageRes = "Your email is unverified. <br>Please check your mail inbox for the verification link.";
    } elseif ($user['status'] == 'denied') {
      $statusRes = "denied";
      $messageRes = "Your account is temporarily suspended. Contact our support team for help.";
    } elseif ($user['status'] == 'deactivated') {
      $statusRes = "deactivated";
      $messageRes = "Your account has been deactivated. Contact our support team to reopen your account.";
    } else {
      $_SESSION['nivas_userId'] = $user['id'];
      $_SESSION['nivas_userName'] = $user['first_name'];
      $_SESSION['nivas_userRole'] = $user['role'];
      $_SESSION['nivas_userSch'] = $user['school'];

      if ($_SESSION['nivas_userRole'] !== 'student' && $_SESSION['nivas_userRole'] !== 'visitor') {
        $roleRes = 'admin';
      }

      $statusRes = "success";
      $messageRes = "Logged in successfully!";
    }
  } else {
    $statusRes = "failed";
    $messageRes = "Email or Password incorrect!";
  }
}

if (isset($_POST['deactivate_acct'])) {
  session_start();
  $user_id = $_SESSION['nivas_userId'];
  $password = md5($_POST['password']);

  // Check if user data exists
  $user_query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id AND password = '$password'");

  if (mysqli_num_rows($user_query) == 1) {
    mysqli_query($conn, "UPDATE users SET status = 'deactivated' WHERE id = $user_id");

    if (mysqli_affected_rows($conn) >= 1) {
      $statusRes = "success";
      $messageRes = "Account successfully deleted!.";
    }
  } else {
    $statusRes = "failed";
    $messageRes = "Password incorrect! Please try again.";
  }
}

if (isset($_POST['delete_account_request'])) {
  $email = mysqli_real_escape_string($conn, $_POST['email']);
  $password = md5($_POST['password']);
  $reason = mysqli_real_escape_string($conn, $_POST['reason']);

  // Check if user data exists and password is correct
  $user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email' AND password = '$password'");

  if (mysqli_num_rows($user_query) == 1) {
    $user = mysqli_fetch_array($user_query);
    $user_id = $user['id'];
    $first_name = $user['first_name'];
    $last_name = $user['last_name'];

    // Update user status to deactivated
    mysqli_query($conn, "UPDATE users SET status = 'deactivated' WHERE id = $user_id");

    if (mysqli_affected_rows($conn) >= 1) {
      // Send notification email to support
      $subject = "Account Deletion Request - $first_name $last_name";
      $reason_escaped = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
      $body = "<b>Account Deletion Request</b><br><br>
              <b>User Information:</b><br>
              Name: $first_name $last_name<br>
              Email: $email<br>
              User ID: $user_id<br><br>
              <b>Reason for deletion:</b><br>
              $reason_escaped<br><br>
              The account has been deactivated.";
      
      // Send notification to support
      sendMail($subject, $body, 'support@nivasity.com');

      // Send confirmation email to user
      $user_subject = "Your Account Deletion Request - Nivasity";
      $user_body = "Hello $first_name,<br><br>
                    We have received your request to delete your Nivasity account. Your account has been deactivated.<br><br>
                    If you did not make this request, please contact our support team immediately at support@nivasity.com.<br><br>
                    We're sorry to see you go. If you change your mind, please contact our support team within 30 days to reactivate your account.<br><br>
                    Best regards,<br><b>Nivasity Team</b>";
      
      sendMail($user_subject, $user_body, $email);

      $statusRes = "success";
      $messageRes = "Your account deletion request has been received. Your account has been deactivated. If you change your mind, please contact support@nivasity.com within 30 days.";
    } else {
      $statusRes = "error";
      $messageRes = "Internal Server Error. Please try again later!";
    }
  } else {
    $statusRes = "failed";
    $messageRes = "Email or Password incorrect!";
  }
}

if (isset($_POST['logout'])) {
  session_start();
  session_unset();
  session_destroy();

  $statusRes = "success";
  $messageRes = "You have successfully logged out!";
}

$responseData = array(
  "role" => "$roleRes",
  "status" => "$statusRes",
  "message" => "$messageRes"
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
?>