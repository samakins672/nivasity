<?php
session_start();
include('config.php');

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$cart_ = "nivas_cart_event$user_id";
$cart_2 = "nivas_cart$user_id";
$date = date('Y-m-d');

// Get the event ID from the AJAX request
if (isset($_POST['event_id'])) {
    $event_id = $_POST['event_id'];
    $action = $_POST['action'];

    // Simulate adding/removing the event to/from the cart
    if (!isset($_SESSION[$cart_])) {
        $_SESSION[$cart_] = array();
    }

    if ($action == 0) {
        // event is in the cart, remove it
        $_SESSION[$cart_] = array_diff($_SESSION[$cart_], array($event_id));

        // Remove any pending DB cart rows for this event
        $userId = intval($user_id);
        $itemId = intval($event_id);
        @mysqli_query($conn, "DELETE FROM cart WHERE user_id = {$userId} AND item_id = {$itemId} AND type = 'event' AND status = 'pending'");
    } else {
        // event is not in the cart, add it
        $_SESSION[$cart_][] = $event_id;
    }

    $total = count($_SESSION[$cart_]) + count($_SESSION[$cart_2]);
    // Return the total number of carted products
    $response = array('total' => $total);

    // Set the appropriate headers for JSON response
    header('Content-Type: application/json');

    echo json_encode($response);

}
?>
