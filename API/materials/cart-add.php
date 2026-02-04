<?php
// API: Add Material to Cart
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
$school_id = $user['school'];

// Check if material exists and is available
$material_query = mysqli_query($conn, "SELECT * FROM manuals WHERE id = $material_id AND school_id = $school_id AND status = 'open'");

if (mysqli_num_rows($material_query) === 0) {
    sendApiError('Material not found or not available', 404);
}

$material = mysqli_fetch_assoc($material_query);

// Check if due date has passed
$due_date = strtotime($material['due_date']);
$now = time();
if ($now > $due_date) {
    sendApiError('Cannot add material to cart - due date has passed', 400);
}

// Check if already purchased
$bought_query = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE manual_id = $material_id AND buyer = $user_id");
if (mysqli_num_rows($bought_query) > 0) {
    sendApiError('You have already purchased this material', 400);
}

// Add to session cart
session_start();
$cart_key = "nivas_cart$user_id";
$cart_event_key = "nivas_cart_event$user_id";

if (!isset($_SESSION[$cart_key])) {
    $_SESSION[$cart_key] = array();
}

// Check if already in cart
if (in_array($material_id, $_SESSION[$cart_key])) {
    sendApiError('Material is already in your cart', 400);
}

// Add to cart
$_SESSION[$cart_key][] = $material_id;

$total_items = count($_SESSION[$cart_key]) + (isset($_SESSION[$cart_event_key]) ? count($_SESSION[$cart_event_key]) : 0);

sendApiSuccess('Material added to cart successfully', [
    'total_items' => $total_items,
    'cart_items' => $_SESSION[$cart_key]
]);
?>
