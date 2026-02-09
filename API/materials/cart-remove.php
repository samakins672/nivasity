<?php
// API: Remove Material from Cart
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user
$user = authenticateApiRequest($conn);
requireStudentRole($user);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Validate required fields
validateRequiredFields(['material_id'], $input);

$material_id = (int)$input['material_id'];
$user_id = $user['id'];

// Remove from session cart
session_start();
$cart_key = "nivas_cart$user_id";
$cart_event_key = "nivas_cart_event$user_id";

if (!isset($_SESSION[$cart_key])) {
    $_SESSION[$cart_key] = array();
}

// Remove from cart
$_SESSION[$cart_key] = array_diff($_SESSION[$cart_key], array($material_id));

$total_items = count($_SESSION[$cart_key]) + (isset($_SESSION[$cart_event_key]) ? count($_SESSION[$cart_event_key]) : 0);

sendApiSuccess('Material removed from cart successfully', [
    'total_items' => $total_items,
    'cart_items' => array_values($_SESSION[$cart_key])
]);
?>
