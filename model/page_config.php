<?php
require_once __DIR__ . '/../config/fw.php';
$url = substr($_SERVER["SCRIPT_NAME"], strrpos($_SERVER["SCRIPT_NAME"], "/") + 1);

$__stagingGate = defined('STAGING_GATE') && STAGING_GATE === true;

if (!isset($_SESSION['nivas_userId'])) {
  if ($__stagingGate || $url !== 'event_details.php') {
    header('Location: ../signin.html');
    exit();
  }
}

if (isset($_SESSION['nivas_userId'])) {
  $user_id = $_SESSION['nivas_userId'];
  $school_id = $_SESSION['nivas_userSch'];
  
  $user_ = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
  
  $user_image = $user_['profile_pic'];
  $user_email = $user_['email'];
  $user_phone = $user_['phone'];
  $user_status = $user_['status'];
  $user_matric_no = $user_['matric_no'];
  $user_adm_year = $user_['adm_year'];
  $user_dept = $_SESSION['nivas_userDept'] = $user_['dept'];
  $f_name = $user_['first_name'];
  $l_name = $user_['last_name'];
  $l_name = $user_['last_name'];
  $user_name = $f_name .' '. $l_name;
  
  $is_admin_role = False;

  // Staging: restrict access to a single tester account
  if ($__stagingGate) {
    if (strtolower($user_email) !== strtolower(STAGING_ALLOWED_EMAIL)) {
      session_unset();
      session_destroy();
      header("Location: https://funaab.nivasity.com/signin.html?logout=1&not_allowed=1");
      exit();
    }
  }
  
  if ($_SESSION['nivas_userRole'] == 'org_admin' || $_SESSION['nivas_userRole'] == 'visitor') {
    header("Location: https://funaab.nivasity.com/signin.html?logout=1&not_allowed=1");
    exit();
  }
  if ($_SESSION['nivas_userRole'] !== 'student' && $_SESSION['nivas_userRole'] !== 'visitor') {
    $is_admin_role = True;
  }

  if ($school_id != 1) {
    $redirected_path = $_SERVER['REQUEST_URI'];
    header("Location: https://nivasity.com$redirected_path");
    exit();
  }
}

$date = date('Y-m-d');
$_day = date('w');
$day = date('l', strtotime("last sunday +$_day days"));
$short_day = date('D', strtotime("last sunday +$_day days"));

if (isset($_GET['loggedin'])) {
  date_default_timezone_set('Africa/Lagos');
  $current_login = date('Y-m-d H:i:s');
  $last_login = mysqli_fetch_array(mysqli_query($conn, "SELECT last_login FROM users WHERE id = $user_id"))[0];
  $last_login = new DateTime($last_login);

  mysqli_query($conn, "UPDATE users SET last_login = '$current_login' WHERE id ='$user_id'");
}


?>
