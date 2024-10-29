<?php
// Set your IPinfo API key
$apiKey = "c9c492932afc34";

// Get the user's IP address
$ip = $_SERVER['REMOTE_ADDR'];

// Prepare the API request URL
$url = "https://ipinfo.io/{$ip}?token={$apiKey}";

// Use cURL to send a request to the API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

// Decode the JSON response
$data = json_decode($response, true);

// Check if the 'region' (state) information is available
if (isset($data['region'])) {
    echo "User's state/region: " . $data['region'];
} else {
    echo "Could not retrieve user's state/region. $ip";
}
?>