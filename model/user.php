<?php
include('config.php');
include('mail.php');
include('functions.php');
require_once __DIR__ . '/../config/fw.php';
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
    $school = 1;
    mysqli_query($conn, "INSERT INTO users (first_name, last_name, email, phone, password, role, school, gender)"
        . " VALUES ('$first_name', '$last_name', '$email', '$phone', '$password', '$role', $school, '$gender')");
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
      Click on the following link to verify your account: <a href='https://funaab.nivasity.com/$verificationCode'>Verify Account</a>
      <br>If you are unable to click on the link, please copy and paste the following URL into your browser: https://funaab.nivasity.com/$verificationCode
      <br><br>
      Thank you for choosing Nivasity. We look forward to serving you!
      <br><br>
      Best regards,<br><b>Nivasity Team</b>";

      // Call the Brevo mail function and capture the status
      $mailStatus = sendBrevoMail($subject, $body, $email);

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

if (isset($_POST['resend_verification'])) {
  $email = mysqli_real_escape_string($conn, $_POST['email']);

  if (empty($email)) {
    $statusRes = "error";
    $messageRes = "We couldn't find that email address. Please sign up again.";
  } else {
    $user_query = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

    if (mysqli_num_rows($user_query) !== 1) {
      $statusRes = "error";
      $messageRes = "We couldn't find that email address. Please sign up again.";
    } else {
      $user = mysqli_fetch_array($user_query);

      if ($user['status'] !== 'unverified') {
        $statusRes = "warning";
        $messageRes = "This account has already been verified. You can go ahead and sign in.";
      } else {
        $user_id = $user['id'];
        $verificationCode = generateVerificationCode(12);

        while (!isCodeUnique($verificationCode, $conn, 'verification_code')) {
          $verificationCode = generateVerificationCode(12);
        }

        $existingCode = mysqli_query($conn, "SELECT user_id FROM verification_code WHERE user_id = $user_id");

        if (mysqli_num_rows($existingCode) > 0) {
          mysqli_query($conn, "UPDATE verification_code SET code = '$verificationCode' WHERE user_id = $user_id");
        } else {
          mysqli_query($conn, "INSERT INTO verification_code (user_id, code) VALUES ($user_id, '$verificationCode')");
        }

        if (mysqli_affected_rows($conn) < 1 && mysqli_errno($conn) !== 0) {
          $statusRes = "error";
          $messageRes = "We couldn't generate a new verification link right now. Please try again shortly.";
        } else {
          if ($user['role'] == 'org_admin') {
            $verificationCode = "setup_org.html?verify=$verificationCode";
          } elseif ($user['role'] == 'visitor') {
            $verificationCode = "verify.html?verify=$verificationCode";
          } else {
            $verificationCode = "setup.html?verify=$verificationCode";
          }

          $subject = "Verify Your Account on NIVASITY";
          $first_name = $user['first_name'];
          $body = "Hello $first_name,
      <br><br>
      We're sending you a new verification link so you can finish setting up your Nivasity account.
      <br><br>
      Click on the following link to verify your account: <a href='https://funaab.nivasity.com/$verificationCode'>Verify Account</a>
      <br>If you are unable to click on the link, please copy and paste the following URL into your browser: https://funaab.nivasity.com/$verificationCode
      <br><br>
      Thank you for choosing Nivasity. We look forward to serving you!
      <br><br>
      Best regards,<br><b>Nivasity Team</b>";

          $mailStatus = sendBrevoMail($subject, $body, $email);

          if ($mailStatus === "success") {
            $statusRes = "success";
            $messageRes = "We've sent you a fresh verification link. Please check your inbox (and spam folder).";
          } else {
            $statusRes = "error";
            $messageRes = "We couldn't send the verification email right now. Please try again shortly.";
          }
        }
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

  // Staging: only allow a single tester account to sign in
  if (defined('STAGING_GATE') && STAGING_GATE === true) {
    if (strtolower($email) !== strtolower(STAGING_ALLOWED_EMAIL)) {
      $statusRes = "failed";
      $messageRes = "Access restricted: staging is limited to authorised tester.";
      $responseData = array(
        "role" => "$roleRes",
        "status" => "$statusRes",
        "message" => "$messageRes"
      );
      header('Content-Type: application/json');
      echo json_encode($responseData);
      exit();
    }
  }

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

if (isset($_POST['logout'])) {
  session_start();
  session_unset();
  session_destroy();

  if (isset($_POST['not_allowed']) && $_POST['not_allowed'] == 1) {
    $statusRes = "failed";
    $messageRes = "You are not allowed on this domain, kindly visity nivasity.com!";
  } else {
    $statusRes = "success";
    $messageRes = "You have successfully logged out!";
  }
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
