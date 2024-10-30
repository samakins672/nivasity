<?php
session_start();
require_once 'model/config.php';

$user_id = $_SESSION['nivas_userId'];
$user_name = $_SESSION['nivas_userName'];
$cart_ = $_SESSION["nivas_cart$user_id"];
$cart_2 = $_SESSION["nivas_cart_event$user_id"]; // Cart for events

// // Set your IPinfo API key
// $apiKey = "c9c492932afc34";

// // Get the user's IP address
// $ip = $_SERVER['REMOTE_ADDR'];

// // Prepare the API request URL
// $url = "https://ipinfo.io/{$ip}?token={$apiKey}";

// // Use cURL to send a request to the API
// $ch = curl_init();
// curl_setopt($ch, CURLOPT_URL, $url);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// $response = curl_exec($ch);
// curl_close($ch);

// // Decode the JSON response
// $data = json_decode($response, true);

// // Check if the 'region' (state) information is available
// if (isset($data['region'])) {
//     echo "User's state/region: " . $data['region'];
// } else {
//     echo "Could not retrieve user's state/region. $ip";
// }


// Send a congratulatory email
$user_ = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
$user = mysqli_fetch_array($user_);
$to = $user['email'];
$subject = "Congratulations on Your Purchase!";
$message = "Hello $user_name,<br><br>Thank you for your purchase!<br><br>";

// Add each manual and event to the email body
$message .= "Materials:<br><ol>";
foreach ($cart_ as $manual_id) {
    $manual_query = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM manuals WHERE id = $manual_id"));
    $message .= "<li>Title - ".$manual_query['title']." (".$manual_query['course_code'].")<br>";
    $message .= "Price - ".$manual_query['price']."</li>";
}

$message .= "</ol>Events:<br><ol>";
foreach ($cart_2 as $event_id) {
    $event_query = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM events WHERE id = $event_id"));
    $message .= "<li>Event Title - ".$event_query['title']."<br>";
    $message .= "Location - ".$event_query['location']."<br>";
    $message .= "Date - ".date('j M', strtotime($event_query['event_date'])) . " • " . date('g:i A', strtotime($event_query['event_time']))."<br>";
    $message .= "Price - ".$event_query['price']."</li><br>";
}
$total_amount = 23000;

$message .= "</ol>Total Amount: $" . number_format($total_amount, 2) . "<br>Ref ID: " . number_format($total_amount, 2) . "<br><br>We hope you enjoy your purchase!<br><br>Best regards,<br>Nivasity Team";

?>