<?php
session_start();
include('config.php');
include('mail.php');
include('../../model/functions.php');

$statusRes = $messageRes = 'failed';

$user_id = $_SESSION['nivas_userId'];

if (isset($_POST['close_event'])) {
  $event_id = mysqli_real_escape_string($conn, $_POST['event_id']);
  $action = mysqli_real_escape_string($conn, $_POST['close_event']);

  $curr_status =  'closed';
  if ($action == '1') {
    $curr_status =  'open';
  }

  mysqli_query($conn, "UPDATE events SET status = '$curr_status' WHERE id = $event_id");

  if (mysqli_affected_rows($conn) >= 1) {
    $statusRes = "success";
    $messageRes = "Event successfully closed!";
  } else {
    $statusRes = "error";
    $messageRes = "Internal Server Error.$curr_status Please $action try again$event_id later!";
  }
} else if (isset($_POST['email_guests'])) {
  $event_id = mysqli_real_escape_string($conn, $_POST['event_id']);
  $title = mysqli_real_escape_string($conn, $_POST['title']);
  $message = mysqli_real_escape_string($conn, $_POST['message']);

  // Fetch the event organizer's email
  $organizerQuery = "SELECT o.work_email 
                   FROM events e
                   INNER JOIN organisation o ON e.user_id = o.user_id
                   WHERE e.id = $event_id";
  $organizerResult = mysqli_query($conn, $organizerQuery);
  $organizerEmail = mysqli_fetch_assoc($organizerResult)['work_email'] ?? "no-reply@nivasity.com";

  // Fetch emails of users who bought tickets for the event
  $query = "SELECT DISTINCT u.email 
          FROM event_tickets t
          INNER JOIN users u ON t.buyer = u.id
          WHERE t.event_id = $event_id";

  $result = mysqli_query($conn, $query);

  if (!$result) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch user emails.']);
    exit;
  }

  $emails = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $emails[] = $row['email'];
  }

  if (!empty($emails)) {
    // Call the sendMail function with BCC support
    $status = sendBulkMail(
      "Notification for Event: $title",
      $message,
      $emails,
      $organizerEmail
    );


    if ($status == "success") {
      $statusRes = "success";
      $messageRes = "Emails sent successfully!";
    } else {
      $statusRes = "error";
      $messageRes = "Failed to send emails!";
    }
  } else {
    $statusRes = "error";
    $messageRes = "No users found for this event!";
  }
} else if (isset($_POST['event_id'])) {
  $event_id = mysqli_real_escape_string($conn, $_POST['event_id']);
  $title = mysqli_real_escape_string($conn, $_POST['title']);
  $description = mysqli_real_escape_string($conn, $_POST['description']);
  $price = mysqli_real_escape_string($conn, $_POST['price']);
  $quantity = mysqli_real_escape_string($conn, $_POST['quantity']);
  $event_type = mysqli_real_escape_string($conn, $_POST['event_type']);
  $event_link = mysqli_real_escape_string($conn, $_POST['event_link']);
  $school = mysqli_real_escape_string($conn, $_POST['school']);
  $location = mysqli_real_escape_string($conn, $_POST['location']);
  $event_date = mysqli_real_escape_string($conn, $_POST['event_date']);
  $event_time = mysqli_real_escape_string($conn, $_POST['event_time']);
  $picture = $_FILES['upload']['name'];

  if ($picture === '') {
    $picture = 'image.png';
  } else {
    $tempname = $_FILES['upload']['tmp_name'];
    $extension = pathinfo($picture, PATHINFO_EXTENSION);
    $picture = "event_" . time() . "." . $extension;
    $destination = "../../assets/images/events/{$picture}";
  }


  if ($event_id == 0) {
    // Generate a unique verification code
    $event_code = generateVerificationCode(8);

    // Check if the code already exists, regenerate if needed
    while (!isCodeUnique($event_code, $conn, "events")) {
      $event_code = generateVerificationCode(8);
    }

    mysqli_query($conn, "INSERT INTO events (title, description, location, event_banner,	price, code,	event_type, event_link,	school, event_date, event_time,	quantity,	user_id) 
        VALUES ('$title', '$description', '$location', '$picture',	$price, '$event_code',	'$event_type', '$event_link',	$school, '$event_date', '$event_time',	$quantity,	$user_id)");
  } else {
    $last_picture = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM events WHERE id = $event_id"))['event_banner'];

    if ($last_picture !== 'image.png') {
      unlink("../../assets/images/events/{$last_picture}");
    }

    mysqli_query($conn, "UPDATE events SET title = '$title', description = '$description', location = '$location', event_banner = '$picture',	price = $price, event_type = '$event_type', event_link = '$event_link',	school = $school, event_date = '$event_date', event_time = '$event_time', quantity = $quantity 
        WHERE id = $event_id");
  }

  if ($picture !== 'image.png') {
    move_uploaded_file($tempname, $destination);
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
