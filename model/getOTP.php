<?php
session_start();
require_once 'config.php';
require_once '../config/fw.php';
$curl = curl_init();

$headers = [
  'Authorization: Bearer ' . FLW_SECRET_KEY_TEST,
  'Content-Type: application/json'
];

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

if (isset($_POST['getOtp'])) {
  $password = null;

  if ($_POST['getOtp'] == 'get') {
    $email = $_POST['email'];

    // Check if the user exists in the database
    $result = mysqli_query($conn, "SELECT * FROM users WHERE email = '$email'");

    // Fetch user data
    $userData = mysqli_fetch_assoc($result);

    if (!$userData) {
      // Create a new array with the desired fields
      $filteredResponse = [
        'status' => "error",
        'message' => "Email not found!",
        'reference' => null,
        'otp' => null,
      ];

      // Encode the filtered data as JSON and send it
      echo json_encode($filteredResponse);
      exit();
    } else {
      $url = 'https://api.flutterwave.com/v3/otps';
      $data = [
        "length" => 7,
        "customer" => [
          "name" => $userData['first_name'] . ' ' . $userData['last_name'],
          "email" => $email,
          "phone" => $userData['phone']
        ],
        "sender" => "Nivasity",
        "send" => true,
        "medium" => ["email"],
        "expiry" => 10
      ];
    }
  } else {
    $password = md5($_POST['password']);
    $otp = $_POST['otp'];
    $ref = $_POST['ref'];
    $url = "https://api.flutterwave.com/v3/otps/$ref/validate";
    $data = ["otp" => $otp];
  }

  // Set cURL options
  curl_setopt_array($curl, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => $headers,
  ]);

  $response = curl_exec($curl);

  // Close cURL session
  curl_close($curl);

  // Decode the JSON response
  $decodedResponse = json_decode($response, true);

  // Extract the first entry in the 'data' array
  $data = $decodedResponse['data'][0] ?? null;

  if ($decodedResponse['status'] == 'success') {
    if ($password == null) {
      $_SESSION["reset_email"] = $email;
    } else {
      $user_email = $_SESSION["reset_email"];

      // Update the user's password in the database using mysqli_query
      mysqli_query($conn, "UPDATE users SET password = '$password' WHERE email = '$user_email'");
    }
  }
  // Create a new array with the desired fields
  $filteredResponse = [
    'status' => $decodedResponse['status'] ?? null,
    'message' => $decodedResponse['message'] ?? null,
    'reference' => $data['reference'] ?? null,
    'otp' => $data['otp'] ?? null,
  ];

  // Encode the filtered data as JSON and send it
  echo json_encode($filteredResponse);
}
?>
