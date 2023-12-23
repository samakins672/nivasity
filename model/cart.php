<?php
session_start();
include('config.php');

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$cart_ = "nivas_cart$user_id";

// Get the product ID from the AJAX request
if (isset($_POST['product_id'])) {
  $product_id = $_POST['product_id'];
  $action = $_POST['action'];

  // Simulate adding/removing the product to/from the cart
  if (!isset($_SESSION[$cart_])) {
    $_SESSION[$cart_] = array();
  }

  if ($action == 0) {
    // Product is in the cart, remove it
    $_SESSION[$cart_] = array_diff($_SESSION[$cart_], array($product_id));
  } else {
    // Product is not in the cart, add it
    $_SESSION[$cart_][] = $product_id;
  }

  // Return the total number of carted products
  $response = array('total' => count($_SESSION[$cart_]));

  // Set the appropriate headers for JSON response
  header('Content-Type: application/json');

  echo json_encode($response);

} else if (isset($_POST['reload_cart'])) {
    $total_cart_items = count($_SESSION["nivas_cart$user_id"]);
    $total_cart_price = 0;

    echo '
    <div class="row flex-grow">
        <div class="col-sm-8 grid-margin stretch-card">
            <div class="card card-rounded shadow-sm">
                <div class="card-body">
                    <div class="table-responsive mt-1">
                        <table class="table table-hover table-striped select-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Due Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>';
    
    foreach ($_SESSION["nivas_cart$user_id"] as $cart_item_id) {
        // Fetch details of the carted item based on $cart_item_id
        $cart_item = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM manuals_$school_id WHERE id = $cart_item_id"));
        
        // Retrieve and format the due date
        $due_date = date('j M, Y', strtotime($cart_item['due_date']));
        $total_cart_price += $cart_item['price'];

        echo '
            <tr>
                <td>
                    <div class="d-flex">
                        <div>
                            <h6>' . $cart_item['course_code'] . '</h6>
                            <p>ID: <span class="fw-bold">' . $cart_item['code'] . '</span></p>
                        </div>
                    </div>
                </td>
                <td>
                    <h6>&#8358; ' . $cart_item['price'] . '</h6>
                </td>
                <td>
                    <h6>' . $due_date . '</h6>
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-primary mb-0 btn-block remove-cart" data-mdb-ripple-duration="0" href="model/cart.php?product_id=' . $cart_item_id . '&action=0">Remove</a>
                </td>
            </tr>';
    }

    echo '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4 grid-margin">
            <div class="card card-rounded shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title card-title-dash">Cart Summary</h4>
                        </div>
                    </div><hr>
                    <div class="d-flex justify-content-between my-3 fw-bold">
                        <p>Subtotal</p>
                        <h3>₦ ' . $total_cart_price . '</h3>
                    </div>';

    if ($total_cart_price > 0) {
        echo '
                    <button class="btn fw-bold btn-primary w-100 mb-0 btn-block py-3 checkout-cart" data-mdb-ripple-duration="0">CHECKOUT (₦ ' . $total_cart_price . ')</button>';
    } else {
        echo '
                    <button class="btn fw-bold btn-primary w-100 mb-0 btn-block py-3" disabled>CHECKOUT</button>';
    }

    echo '
                </div>
            </div>
        </div>
    </div>';
} else {
  $product_id = $_GET['product_id'];
  $action = $_GET['action'];

  // Simulate adding/removing the product to/from the cart
  if (!isset($_SESSION[$cart_])) {
    $_SESSION[$cart_] = array();
  }

  if ($action == 0) {
    // Product is in the cart, remove it
    $_SESSION[$cart_] = array_diff($_SESSION[$cart_], array($product_id));
  } else {
    // Product is not in the cart, add it
    $_SESSION[$cart_][] = $product_id;
  }

  // Return the total number of carted products
  $response = array('total' => count($_SESSION[$cart_]));

  header('Location: ../store.php');
}
?>
