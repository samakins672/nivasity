<?php
session_start();
require_once 'config.php';
require_once 'payment_freeze.php';

// Check if payments are frozen
if (is_payment_frozen()) {
    header('Content-Type: application/json');
    $freeze_info = get_payment_freeze_info();
    echo json_encode([
        'success' => false, 
        'message' => $freeze_info ? $freeze_info['message'] : 'Payments are currently paused. Please try again later.',
        'payment_frozen' => true
    ]);
    exit;
}

// Read the JSON data from the request body
$data = json_decode(file_get_contents('php://input'), true);

// Check if data is valid
if ($data === null) {
    die("Invalid JSON data received");
}

// Retrieve the data from the request
$ref_id = $data['ref_id'];
$user_id = $data['user_id'];
$items = $data['items'];
$gateway = isset($data['gateway']) ? mysqli_real_escape_string($conn, strtoupper($data['gateway'])) : 'FLUTTERWAVE';

// Validate if user_id exists in the users table
$query = "SELECT id FROM users WHERE id = $user_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    die("Error: user_id does not exist in the users table.");
}

// Prepare the query to insert data into the cart table
$query = "INSERT INTO cart (ref_id, user_id, item_id, type, status, gateway) VALUES ";
$values = [];

foreach ($items as $item) {
    $item_id = intval($item['item_id']); // Sanitize item_id as an integer
    $type = mysqli_real_escape_string($conn, $item['type']); // Escape the type
    $gateway_value = $gateway ? "'$gateway'" : "NULL";
    $values[] = "('$ref_id', $user_id, $item_id, '$type', 'pending', $gateway_value)";
}

$query .= implode(", ", $values);

if (mysqli_query($conn, $query)) {
    echo json_encode(['success' => true, 'message' => 'Cart saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Internal Server Error while adding cart items. Please try again later!']);
}

mysqli_close($conn);
?>
