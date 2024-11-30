<?php
session_start();
include('config.php');
include('../../model/functions.php');

$statusRes = $messageRes = 'failed';

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$user_dept = $_SESSION['nivas_userDept'];

if (isset($_POST['close_manual'])) {
  $manual_id = mysqli_real_escape_string($conn, $_POST['manual_id']);
  $action = mysqli_real_escape_string($conn, $_POST['close_manual']);

  $curr_status =  'closed';
  if ($action == '1') {
    $curr_status =  'open';
  }

  mysqli_query($conn, "UPDATE manuals SET status = '$curr_status' WHERE id = $manual_id");

  if (mysqli_affected_rows($conn) >= 1) {
    $statusRes = "success";
    $messageRes = "Manual successfully closed!";
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error.$curr_status Please $action try again$manual_id later!";
  }
} else if (isset($_POST['delete_manual'])) {
  $product_id = mysqli_real_escape_string($conn, $_POST['product_id']);
  $product_type = mysqli_real_escape_string($conn, $_POST['product_type']);

  if ($product_type == 'event') {
    mysqli_query($conn, "DELETE FROM events WHERE id = $product_id");
  } else {
    mysqli_query($conn, "DELETE FROM manuals WHERE id = $product_id");
  }

  if (mysqli_affected_rows($conn) >= 1) {
    $statusRes = "success";
    $messageRes = "Manual successfully deleted!";
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error. Please try again later!";
  }
} else if (isset($_POST['manual_id'])) {
  $manual_id = mysqli_real_escape_string($conn, $_POST['manual_id']);
  $title = mysqli_real_escape_string($conn, $_POST['title']);
  $course_code = mysqli_real_escape_string($conn, $_POST['course_code']);
  $price = mysqli_real_escape_string($conn, $_POST['price']);
  $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
  $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);

  if ($manual_id == 0) {
    // Generate a unique verification code
    $manual_code = generateVerificationCode(8);
  
    // Check if the code already exists, regenerate if needed
    while (!isCodeUnique($manual_code, $conn, "manuals")) {
      $manual_code = generateVerificationCode(8);
    }

    mysqli_query($conn, "INSERT INTO manuals (title,	course_code,	price, dept,	code,	due_date,	quantity,	user_id, school_id) 
        VALUES ('$title',	'$course_code',	$price, $user_dept,	'$manual_code',	'$due_date',	$quantity,	$user_id, $school_id)");
  } else {
    mysqli_query($conn, "UPDATE manuals SET title = '$title', course_code = '$course_code', price = $price, due_date = '$due_date', quantity = $quantity 
        WHERE id = $manual_id");
  }

  if (mysqli_affected_rows($conn) >= 1) {
    $statusRes = "success";
    $messageRes = "Successfully POSTed!";
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error. Please try again later!";
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