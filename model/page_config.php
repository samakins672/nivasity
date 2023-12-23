<?php
if (!isset($_SESSION['nivas_userId'])) {
  header('Location: ../signin.html');
  exit();
}

$url = substr($_SERVER["SCRIPT_NAME"], strrpos($_SERVER["SCRIPT_NAME"], "/") + 1);

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];

$user_ = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));

$user_image = $user_['profile_pic'];
$user_email = $user_['email'];
$user_phone = $user_['phone'];
$user_dept = $_SESSION['nivas_userDept'] = $user_['dept'];
$f_name = $user_['first_name'];
$user_name = $f_name .' '. $user_['last_name'];

$admin_role = False;

$date = date('Y-m-d');
$_day = date('w');
$day = jdDayOfWeek($_day - 1, 1);
$short_day = jdDayOfWeek($_day - 1, 2);

if (isset($_GET['loggedin'])) {
  date_default_timezone_set('Africa/Lagos');
  $current_login = date('Y-m-d H:i:s');
  $last_login = mysqli_fetch_array(mysqli_query($conn, "SELECT last_login FROM users WHERE id = $user_id"))[0];
  $last_login = new DateTime($last_login);

  mysqli_query($conn, "UPDATE users SET last_login = '$current_login' WHERE id ='$user_id'");
}

if ($_SESSION['nivas_userRole'] != 'student') {
  $admin_role = True;
}

?>