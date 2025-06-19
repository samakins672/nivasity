<?php
session_start();
include('config.php');

$cart_ = "nivas_cart_event";
$cart_2 = "nivas_cart";
$active = isset($_SESSION['nivas_userId']) ? '1' : '0';

$date = date('Y-m-d');

// Get the event ID from the AJAX request
if (isset($_GET['product_id'])) {
    $product_id = $_GET['product_id'];
    $action = $_GET['action'];
    $type = $_GET['type'];

    // Simulate adding/removing the product/event to/from the cart
    if ($type == 'product') {
        $cart = "nivas_cart";
    } else {
        $cart = "nivas_cart_event";
    }

    // Simulate adding/removing the event to/from the cart
    if (!isset($_SESSION[$cart_2]) || !isset($_SESSION[$cart_])) {
        $_SESSION[$cart_] = array();
        $_SESSION[$cart_2] = array();
    }

    if ($action == 0) {
        // event is in the cart, remove it
        $_SESSION[$cart] = array_diff($_SESSION[$cart], array($product_id));
    } else {
        if (!in_array($product_id, $_SESSION[$cart])) {
            $_SESSION[$cart][] = $product_id;  // Add guest item to the user's cart
        }
    }

    $total = count($_SESSION[$cart_]) + count($_SESSION[$cart_2]);
    // Return the total number of carted products
    $response = array('total' => $total, 'active' => $active);

    if (isset($_GET['share'])) {
        if ($active == 1) {
            header('Location: /?cart=1');
        } else {
            header('Location: ../signin.html');
        }
    }
    // Set the appropriate headers for JSON response
    header('Content-Type: application/json');

    echo json_encode($response);
}

?>
