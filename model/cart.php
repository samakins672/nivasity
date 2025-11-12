<?php
session_start();
include('config.php');

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
$cart_ = "nivas_cart$user_id";
$cart_2 = "nivas_cart_event$user_id";
$date = date('Y-m-d');

$_SESSION['cart_sellers'] = [];


if (isset($_SESSION["nivas_cart_event"])) {
    $guest_cart = $_SESSION["nivas_cart_event"];
    
    foreach ($guest_cart as $guestItem) {
        if (!in_array($guestItem, $_SESSION[$cart_2])) {
            $_SESSION[$cart_2][] = $guestItem;
        }
    }
    unset($_SESSION["nivas_cart_event"]);
} 

if (isset($_SESSION["nivas_cart"])) {
    $guest_cart = $_SESSION["nivas_cart"];
    
    foreach ($guest_cart as $guestItem) {
        if (!in_array($guestItem, $_SESSION[$cart_])) {
            $_SESSION[$cart_][] = $guestItem;
        }
    }

    unset($_SESSION["nivas_cart"]);
}


// Get the product ID from the AJAX request
if (isset($_POST['reload_cart'])) {
    $total_cart_items = count($_SESSION["nivas_cart$user_id"]) + count($_SESSION["nivas_cart_event$user_id"]);
    $total_cart_price = 0;
    $total_cart_event = 0;

    echo '
    <div class="row flex-grow">
        <div class="col-sm-8 grid-margin stretch-card">
            <div class="card card-rounded shadow-sm">
                <div class="card-body">
                    <div class="table-responsive mt-1">
                        <table class="table table-hover table-striped select-table">
                            <thead>
                                <tr>
                                    <th>Product/Event</th>
                                    <th>Price</th>
                                    <th>Due Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>';

    // Loop through cart items (products)
    foreach ($_SESSION["nivas_cart$user_id"] as $cart_item_id) {
        // Fetch details of the carted item based on $cart_item_id
        $cart_item = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM manuals WHERE id = $cart_item_id"));

        // Retrieve and format the due date
        $due_date = date('j M, Y', strtotime($cart_item['due_date']));
        $due_date2 = date('Y-m-d', strtotime($cart_item['due_date']));
        $status = $cart_item['status'];
        $status_c = '';

        $seller = $cart_item['user_id'];
        $sett_query = mysqli_query($conn, "SELECT subaccount_code FROM settlement_accounts WHERE school_id = $school_id AND type = 'school' ORDER BY id DESC LIMIT 1");
        if (mysqli_num_rows($sett_query) == 0) {
            $sett_query = mysqli_query($conn, "SELECT subaccount_code FROM settlement_accounts WHERE user_id = $seller ORDER BY id DESC LIMIT 1");
        }
        $seller_code = mysqli_fetch_array($sett_query)['subaccount_code'];

        if ($date > $due_date2 || $status == 'closed') {
            $status = 'disabled';
            $status_c = 'danger';
        } else {
            $total_cart_price += $cart_item['price'];
            $total_cart_event += 1;

            // Store item details for checkout
            $_SESSION['cart_sellers'][] = [
                'seller' => $seller_code,
                'price' => $cart_item['price'],
                'type' => 'manual',
                'product_id' => $cart_item_id,
            ];
        }

        echo '
            <tr>
                <td>
                    <div class="d-flex">
                        <div>
                            <h6>' . $cart_item['course_code'] . '</h6>';
        if ($status_c == 'danger') {
            echo '<p class="text-danger fw-bold">Item Overdue</p>';
        }
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
                    <a class="btn btn-sm btn-outline-primary mb-0 btn-block remove-cart" data-mdb-ripple-duration="0" data-type="product" data-cart_id="' . $cart_item_id . '">Remove</a>
                </td>
            </tr>';
    }

    // Loop through event cart items (from $cart_2)
    foreach ($_SESSION["nivas_cart_event$user_id"] as $cart_item_id) {
        // Fetch details of the event based on $cart_item_id
        $cart_event = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM events WHERE id = $cart_item_id"));

        // Retrieve and format the event date
        $event_date = date('j M, Y', strtotime($cart_event['event_date']));
        $event_date2 = date('Y-m-d', strtotime($cart_event['event_date']));

        $total_cart_price += $cart_event['price'];
        $total_cart_event += 1;

        // Store the seller and price in the session array
        $event_seller = $cart_event['user_id'];
        $event_seller_code = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM settlement_accounts WHERE user_id = $event_seller"))['subaccount_code'];

        // Store item details for checkout
        $_SESSION['cart_sellers'][] = [
            'seller' => $event_seller_code,
            'price' => $cart_event['price'],
            'type' => 'event',
            'product_id' => $cart_item_id,
        ];

        echo '
            <tr>
                <td>
                    <div class="d-flex">
                        <div>
                            <h6>' . $cart_event['title'] . '</h6>
                        </div>
                    </div>
                </td>
                <td>
                    <h6>&#8358; ' . number_format($cart_event['price']) . '</h6>
                </td>
                <td>
                    <h6>' . $event_date . '</h6>
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-primary mb-0 btn-block remove-cart" data-mdb-ripple-duration="0" data-type="event" data-cart_id="' . $cart_item_id . '">Remove</a>
                </td>
            </tr>';
    }

    // Handling fee based on existing charge structure
    $transferAmount = $total_cart_price;
    $charge = 0;
    if ($transferAmount == 0) {
        $charge = 0;
    } elseif ($transferAmount < 2500) {
        // Flat fee for transactions less than ₦2500
        $charge = 70;
    } else {
        // Previous calculation for higher amounts
        $charge += ($transferAmount * 0.02);
        if ($transferAmount >= 2500 && $transferAmount < 5000) {
            $charge += 20;
        } elseif ($transferAmount >= 5000 && $transferAmount < 10000) {
            $charge += 30;
        } else {
            $charge += 50;
        }
    }
    $transferAmount += $charge;

    // Build pending payments accordion HTML grouped by ref_id
    $pendingHtml = '';
    $pending_q = mysqli_query($conn, "SELECT ref_id, item_id, type, created_at FROM cart WHERE user_id = $user_id AND status = 'pending' ORDER BY created_at DESC");
    if ($pending_q && mysqli_num_rows($pending_q) > 0) {
        $groups = [];
        while ($row = mysqli_fetch_assoc($pending_q)) {
            $rid = $row['ref_id'];
            if (!isset($groups[$rid])) { $groups[$rid] = []; }
            $groups[$rid][] = $row;
        }

        $pendingHtml .= "\r\n<div class=\"accordion\" id=\"pendingPaymentsAccordion\">";

        $accIndex = 0;
        foreach ($groups as $rid => $items) {
            $accIndex++;
            $headingId = 'pendingHeading' . $accIndex;
            $collapseId = 'pendingCollapse' . $accIndex;

            // Build list items with names
            $itemLines = '';
            foreach ($items as $it) {
                $name = '';
                $iid = (int)$it['item_id'];
                if ($it['type'] === 'manual') {
                    $mres = mysqli_query($conn, "SELECT title, course_code FROM manuals WHERE id = $iid");
                    if ($mres && mysqli_num_rows($mres) > 0) {
                        $m = mysqli_fetch_assoc($mres);
                        $title = htmlspecialchars($m['title']);
                        $code = htmlspecialchars($m['course_code']);
                        $name = 'Material: ' . ($code ? ($code . ' - ') : '') . $title;
                    } else {
                        $name = 'Material ID #' . $iid;
                    }
                } else { // event
                    $eres = mysqli_query($conn, "SELECT title FROM events WHERE id = $iid");
                    if ($eres && mysqli_num_rows($eres) > 0) {
                        $e = mysqli_fetch_assoc($eres);
                        $name = 'Event: ' . htmlspecialchars($e['title']);
                    } else {
                        $name = 'Event ID #' . $iid;
                    }
                }
                $itemLines .= '<li class="small">' . $name . '</li>';
            }

            $pendingHtml .= "\r\n    <div class=\"accordion-item\">\r\n      <h2 class=\"accordion-header\" id=\"$headingId\">\r\n        <button class=\"accordion-button collapsed py-2\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#$collapseId\" aria-expanded=\"false\" aria-controls=\"$collapseId\">\r\n          Ref: " . htmlspecialchars($rid) . " — " . count($items) . " item(s)\r\n        </button>\r\n      </h2>\r\n      <div id=\"$collapseId\" class=\"accordion-collapse collapse\" aria-labelledby=\"$headingId\" data-bs-parent=\"#pendingPaymentsAccordion\">\r\n        <div class=\"accordion-body py-3\">\r\n          <ul class=\"mb-3\">$itemLines</ul>\r\n          <div class=\"d-flex gap-2\">\r\n            <button class=\"btn btn-success btn-sm pending-verify\" data-mdb-ripple-duration="."0"." data-ref_id=\"" . htmlspecialchars($rid) . "\">Yes</button>\r\n            <button class=\"btn btn-outline-secondary btn-sm pending-cancel\" data-mdb-ripple-duration="."0"." data-ref_id=\"" . htmlspecialchars($rid) . "\">Not at all</button>\r\n          </div>\r\n        </div>\r\n      </div>\r\n    </div>";
        }

        $pendingHtml .= "\r\n  </div>\r\n  <hr class=\"mt-3\"/>\r\n</div>";
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
                        <h5 class="fw-bold">₦ ' . number_format($transferAmount) . '</h5>
                    </div>';
    if ($total_cart_price > 0) {
        $sessionData = htmlspecialchars(json_encode($_SESSION['cart_sellers']), ENT_QUOTES, 'UTF-8');
        echo '
                    <button class="btn fw-bold btn-primary w-100 mb-0 btn-block py-3 checkout-cart" data-session_data="'.$sessionData.'" data-charge="'.$charge.'" data-transfer_amount="'.$transferAmount.'" data-mdb-ripple-duration="0" >CHECKOUT</button>';
    } else if ($total_cart_price == 0 && $total_cart_event > 0) {
        echo '
                    <button class="btn fw-bold btn-primary w-100 mb-0 btn-block py-3 free-cart-checkout" data-mdb-ripple-duration="0" >CHECKOUT</button>';
    } else {
        echo '
                    <button class="btn fw-bold btn-primary w-100 mb-0 btn-block py-3" disabled>CHECKOUT</button>';
    }

    if (!empty($pendingHtml)) {
    echo "</div></div>";
    echo '<div class="card card-rounded shadow-sm mt-3"><div class="card-body"><h4 class="card-title card-title-dash">Pending Payments</h4><hr class="my-2"/>' . $pendingHtml . '</div></div>';
    echo "</div></div>";
} else {
    echo "</div></div></div></div>";
}
} else {
    $product_id = $_POST['product_id'];
    $action = $_POST['action'];
    $type = $_POST['type'];

    // Simulate adding/removing the product/event to/from the cart
    if ($type == 'product') {
        $cart = "nivas_cart$user_id";
    } else {
        $cart = "nivas_cart_event$user_id";
    }
    
    // Initialize cart if it doesn't exist
    if (!isset($_SESSION[$cart])) {
        $_SESSION[$cart] = [];
    }

    if ($action == 0) {
        // Remove product or event from cart (session) only
        $_SESSION[$cart] = array_diff($_SESSION[$cart], array($product_id));
    } else {
        // Add product or event to cart
        $_SESSION[$cart][] = $product_id;
    }

    $total = count($_SESSION[$cart_]) + count($_SESSION[$cart_2]);
    // Return the total number of carted products/events
    $response = array('total' => $total, 'cart' => $cart_2);

    // Set the appropriate headers for JSON response
    header('Content-Type: application/json');

    echo json_encode($response);
}
?>
