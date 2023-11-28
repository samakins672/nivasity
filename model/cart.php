<?php
session_start();

// Get the product ID from the AJAX request
if (isset($_POST['product_id'])) {
  $product_id = $_POST['product_id'];
  $action = $_POST['action'];

  // Simulate adding/removing the product to/from the cart
  if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = array();
  }

  if ($action == 0) {
    // Product is in the cart, remove it
    $_SESSION['cart'] = array_diff($_SESSION['cart'], array($product_id));
  } else {
    // Product is not in the cart, add it
    $_SESSION['cart'][] = $product_id;
  }

  // Return the total number of carted products
  $response = array('total' => count($_SESSION['cart']));

  // Set the appropriate headers for JSON response
  header('Content-Type: application/json');

  echo json_encode($response);

} else {
  // Invalid request
  http_response_code(400);
  echo "Invalid request";
}
?>
