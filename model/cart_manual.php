<?php
session_start();
include('config.php');

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$cart_ = "nivas_cart$user_id";
$cart_2 = "nivas_cart_event$user_id";
$date = date('Y-m-d');

// Get the product ID from the AJAX request
if (isset($_POST['product_id'])) {
    $product_id = $_POST['product_id'];
    $action = $_POST['action'];

    // Simulate adding/removing the product to/from the cart
    if (!isset($_SESSION[$cart_])) {
        $_SESSION[$cart_] = array();
    }

    if ($action == 0) {
        // product is in the cart, remove it
        $_SESSION[$cart_] = array_diff($_SESSION[$cart_], array($product_id));
    } else {
        // product is not in the cart, add it
        $_SESSION[$cart_][] = $product_id;
    }

    $total = count($_SESSION[$cart_]) + count($_SESSION[$cart_2]);
    // Return the total number of carted products
    $response = array('total' => $total);

    // Set the appropriate headers for JSON response
    header('Content-Type: application/json');

    echo json_encode($response);

} else {
    $product_id = $_GET['product_id'];
    $action = $_GET['action'];

    // Simulate adding/removing the product to/from the cart
    if (!isset($_SESSION[$cart_])) {
        $_SESSION[$cart_] = array();
    }

    if ($action == 0) {
        // product is in the cart, remove it
        $_SESSION[$cart_] = array_diff($_SESSION[$cart_], array($product_id));
    } else {
        // product is not in the cart, add it
        $_SESSION[$cart_][] = $product_id;
    }

    $total = count($_SESSION[$cart_]) + count($_SESSION[$cart_2]);
    // Return the total number of carted products
    $response = array('total' => $total);

    header('Location: ../store.php');
}
?>
