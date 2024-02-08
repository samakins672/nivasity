<?php
session_start();
include('config.php');

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$cart_ = "nivas_cart$user_id";
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
        $due_date2 = date('Y-m-d', strtotime($cart_item['due_date']));
        // Retrieve the status
        $status = $cart_item['status'];
        $status_c = '';

        $seller = $cart_item['user_id'];
        $seller_code = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM settlement_accounts WHERE user_id = $seller"))['subaccount_code'];

        if ($date > $due_date2 || $status == 'closed') {
            $status = 'disabled';
            $status_c = 'danger';
        } else {
            $total_cart_price = $total_cart_price + $cart_item['price'];
        }

        echo '
            <tr>
                <td>
                    <div class="d-flex">
                        <div>
                            <h6>' . $cart_item['course_code'] . '</h6>'; ?>
                                    <?php if ($status_c == 'danger'): ?>
                                            <p class="text-danger fw-bold">Item Overdue</p>
                                    <?php endif; ?>
                                        <?php
                                        echo '</div>
                    </div>
                </td>
                <td>
                    <h6>&#8358; ' . number_format($cart_item['price']) . '</h6>
                </td>
                <td>
                    <h6>' . $due_date . '</h6>
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-primary mb-0 btn-block remove-cart" data-mdb-ripple-duration="0" href="model/cart.php?product_id=' . $cart_item_id . '&action=0">Remove</a>
                </td>
            </tr>';
    }

    // Assuming $transferAmount contains the transfer amount
    $transferAmount = $total_cart_price;

    $charge = 0;
    if ($transferAmount == 0) {
        $charge = 0;
    } elseif ($transferAmount < 2500) {
        $charge = 45;
    } elseif ($transferAmount >= 2500) {
        // Add 1.4% to the transferAmount
        $charge += ($transferAmount * 0.014);

        // Adjust the charge accordingly
        if ($transferAmount >= 2500 && $transferAmount < 5000) {
            $charge += 20;
        } elseif ($transferAmount >= 5000 && $transferAmount < 10000) {
            $charge += 30;
        } else {
            $charge += 35;
        }
    }

    // Add the charge to the total
    $transferAmount += $charge;

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
                    <div class="d-flex justify-content-between mt-3 mb-1 fw-bold">
                        <p>Subtotal</p>
                        <h4>₦ ' . number_format($total_cart_price) . '</h4>
                    </div>

                    <div class="d-flex justify-content-between mt-0 mb-3 fw-bold">
                        <p>Handling fee</p>
                        <h5>₦ ' . $charge . '</h5>
                    </div>                    
                    <div class="d-flex justify-content-between my-3 text-secondary fw-bold">
                        <h5 class="fw-bold">Total Due</h5>
                        <h5 class="fw-bold">₦ '.number_format($transferAmount).'</h5>
                    </div>
                    ';
    if ($total_cart_price > 0) {
        echo '
                    <button class="btn fw-bold btn-primary w-100 mb-0 btn-block py-3 checkout-cart" data-charge="' . $charge . '" data-seller="'.$seller_code.'" data-subaccount_amount="'.$total_cart_price.'" data-transfer_amount="' . $transferAmount . '" data-mdb-ripple-duration="0" >CHECKOUT</button>';
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
