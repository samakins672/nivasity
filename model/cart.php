<?php
session_start();
include('config.php');
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/internal_wallet_service.php';

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
        $sett_query = mysqli_query($conn, "SELECT subaccount_code, gateway FROM settlement_accounts WHERE school_id = $school_id AND type = 'school' ORDER BY id DESC LIMIT 1");
        if (mysqli_num_rows($sett_query) == 0) {
            $sett_query = mysqli_query($conn, "SELECT subaccount_code, gateway FROM settlement_accounts WHERE user_id = $seller ORDER BY id DESC LIMIT 1");
        }
        $sett_row = mysqli_fetch_array($sett_query);
        $seller_code = $sett_row['subaccount_code'];
        $seller_gateway = $sett_row['gateway'] ?? 'paystack';

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
                'gateway' => $seller_gateway,
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
                    <h6>---</h6>
                </td>
                <td>
                    <a class="btn btn-sm btn-outline-primary mb-0 btn-block remove-cart" data-mdb-ripple-duration="0ms" data-type="product" data-cart_id="' . $cart_item_id . '">Remove</a>
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
        $event_sett_query = mysqli_query($conn, "SELECT subaccount_code, gateway FROM settlement_accounts WHERE user_id = $event_seller ORDER BY id DESC LIMIT 1");
        $event_sett_row = mysqli_fetch_array($event_sett_query);
        $event_seller_code = $event_sett_row['subaccount_code'];
        $event_seller_gateway = $event_sett_row['gateway'] ?? 'paystack';

        // Store item details for checkout
        $_SESSION['cart_sellers'][] = [
            'seller' => $event_seller_code,
            'price' => $cart_event['price'],
            'type' => 'event',
            'product_id' => $cart_item_id,
            'gateway' => $event_seller_gateway,
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
                    <a class="btn btn-sm btn-outline-primary mb-0 btn-block remove-cart" data-mdb-ripple-duration="0ms" data-type="event" data-cart_id="' . $cart_item_id . '">Remove</a>
                </td>
            </tr>';
    }

    // Handling fee based on existing charge structure
    $transferAmount = $total_cart_price;
    $charge = 0;
    $wallet = nivasityGetUserWallet($conn, (int)$user_id);
    $walletBalance = (int)($wallet['balance'] ?? 0);
    if ($transferAmount > 0) {
        // Use active gateway pricing (Paystack/Flutterwave/Interswitch) for accuracy
        $gatewayCharges = calculateGatewayCharges($transferAmount);
        $charge = $gatewayCharges['charge'] ?? 0;
        $transferAmount = $gatewayCharges['total_amount'] ?? ($transferAmount + $charge);
    }
    $walletFee = nivasityGetWalletHandlingFeeBreakdown($conn, $total_cart_price, $charge);
    $walletCharge = (int)($walletFee['charge'] ?? 0);
    $walletTotalAmount = (int)($walletFee['total_amount'] ?? $total_cart_price);
    $canPayWithWallet = $wallet !== null && $walletBalance >= $walletTotalAmount;
    $walletSavings = max(0, (int)$transferAmount - (int)$walletTotalAmount);
    $walletShortfall = max(0, (int)$walletTotalAmount - (int)$walletBalance);
    $walletExists = $wallet !== null;
    $walletBalanceLabel = '&#8358; ' . number_format($walletBalance);
    $gatewayTotalLabel = '&#8358; ' . number_format($transferAmount);
    $walletTotalLabel = '&#8358; ' . number_format($walletTotalAmount);
    $gatewayChargeLabel = '&#8358; ' . number_format($charge);
    $walletChargeLabel = '&#8358; ' . number_format($walletCharge);
    $walletSavingsLabel = '&#8358; ' . number_format($walletSavings);
    $walletSavingsNegativeLabel = '-&#8358; ' . number_format($walletSavings);
    $walletShortfallLabel = '&#8358; ' . number_format($walletShortfall);


    echo '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4 grid-margin">
            <div class="card card-rounded shadow-sm cart-payment-summary">
                <div class="card-body">
                    <div class="cart-summary-heading">
                        <div>
                            <h4 class="card-title card-title-dash mb-1">Cart Summary</h4>
                            <p class="summary-muted mb-0">Review your preferred checkout route before you pay.</p>
                        </div>';
    if ($walletSavings > 0) {
        echo '<span class="wallet-savings-pill">Wallet saves ' . $walletSavingsLabel . '</span>';
    }
    echo '
                    </div>
                    <div class="cart-summary-breakdown">
                        <div class="summary-row">
                            <p class="summary-row-label">Base Price</p>
                            <h4 class="summary-row-value">&#8358; ' . number_format($total_cart_price) . '</h4>
                        </div>
                        <div class="summary-row summary-row-stack">
                            <div>
                                <p class="summary-row-label">Wallet Fee</p>';
    if ($walletSavings > 0) {
        echo '<span class="summary-row-caption">(' . $walletSavingsNegativeLabel . ' standard fee)</span>';
    }
    echo '
                            </div>
                            <h4 class="summary-row-value">' . $walletChargeLabel . '</h4>
                        </div>
                        <div class="summary-row is-total">
                            <p class="summary-row-label">Total</p>
                            <h4 class="summary-row-value">' . $walletTotalLabel . '</h4>
                        </div>
                    </div>
                    <div class="summary-divider"></div>
                    <div class="payment-options-heading">
                        <h5 class="payment-options-title">Payment Options</h5>
                        <p class="summary-muted mb-0">Choose wallet checkout for the lower final total, or continue with card or bank transfer.</p>
                    </div>
                    <div class="cart-payment-options">';
    if ($total_cart_price > 0) {
        $sessionData = htmlspecialchars(json_encode($_SESSION['cart_sellers']), ENT_QUOTES, 'UTF-8');
        echo '
                    <div class="cart-payment-option is-wallet' . (!$canPayWithWallet ? ' is-disabled' : '') . '">
                        <div class="cart-payment-option-header">
                            <div>
                                <h5 class="cart-payment-option-title">Nivasity Wallet</h5>
                                <span class="cart-payment-option-subtitle">Fastest route for the best checkout price.</span>
                            </div>
                            <span class="cart-payment-badge is-wallet">Recommended</span>
                        </div>
                        <div class="cart-payment-meta">
                            <div class="cart-payment-meta-row">
                                <span>Wallet Discount</span>
                                <strong>' . $walletSavingsNegativeLabel . '</strong>
                            </div>
                            <div class="cart-payment-meta-row is-final-total">
                                <span>Final Total</span>
                                <strong>' . $walletTotalLabel . '</strong>
                            </div>';
        echo '
                        </div>';
        if ($wallet !== null) {
            if ($canPayWithWallet) {
                echo '
                        <button class="btn w-100 cart-payment-action wallet-primary wallet-cart-checkout" data-session_data="'.$sessionData.'" data-wallet_amount="'.$walletTotalAmount.'" data-wallet_charge="'.$walletCharge.'" data-mdb-ripple-duration="0ms">Pay ' . $walletTotalLabel . ' with Wallet</button>';
            } else {
                echo '
                        <p class="cart-payment-note">Your wallet is short by ' . $walletShortfallLabel . '. Fund it first if you want the lower total.</p>
                        <button class="btn w-100 cart-payment-action wallet-disabled" disabled>Need ' . $walletShortfallLabel . ' more to use wallet</button>
                        <a class="cart-wallet-helper-link mt-3" href="wallet.php">Open wallet page to fund your wallet</a>';
            }
        } else {
            echo '
                        <p class="cart-payment-note">Create your wallet to unlock the lower ' . $walletTotalLabel . ' total for this cart.</p>
                        <a class="btn w-100 cart-payment-action wallet-primary" href="wallet.php">Set up wallet to pay ' . $walletTotalLabel . '</a>';
        }
        echo '
                    </div>
                    <div class="cart-payment-option">
                        <div class="cart-payment-option-header">
                            <div>
                                <h5 class="cart-payment-option-title">Gateway Checkout</h5>
                                <span class="cart-payment-option-subtitle">Card / Bank Transfer</span>
                            </div>
                            <span class="cart-payment-badge is-neutral">Online</span>
                        </div>
                        <div class="cart-payment-meta">
                            <div class="cart-payment-meta-row is-final-total">
                                <span>Final Total</span>
                                <strong>' . $gatewayTotalLabel . '</strong>
                            </div>
                        </div>
                        <button class="btn w-100 cart-payment-action gateway-secondary checkout-cart" data-session_data="'.$sessionData.'" data-charge="'.$charge.'" data-transfer_amount="'.$transferAmount.'" data-mdb-ripple-duration="0ms">Pay ' . $gatewayTotalLabel . ' online</button>
                    </div>';
    } else if ($total_cart_price == 0 && $total_cart_event > 0) {
        echo '
                    <div class="cart-payment-option is-wallet">
                        <div class="cart-payment-option-header">
                            <div>
                                <h5 class="cart-payment-option-title">Free Checkout</h5>
                                <span class="cart-payment-option-subtitle">No payment is required for the items currently in your cart.</span>
                            </div>
                            <span class="cart-payment-badge is-wallet">Free</span>
                        </div>
                        <div class="cart-payment-total">
                            <span class="cart-payment-total-label">Amount due</span>
                            <span class="cart-payment-total-amount">&#8358; 0</span>
                        </div>
                        <button class="btn w-100 cart-payment-action wallet-primary free-cart-checkout" data-mdb-ripple-duration="0ms">Complete free checkout</button>
                    </div>';
    } else {
        echo '
                    <div class="cart-payment-option">
                        <div class="cart-payment-option-header">
                            <div>
                                <h5 class="cart-payment-option-title">Your cart is empty</h5>
                                <span class="cart-payment-option-subtitle">Add materials or events to see payment options here.</span>
                            </div>
                        </div>
                        <button class="btn w-100 cart-payment-action wallet-disabled" disabled>Checkout unavailable</button>
                    </div>';
    }
    echo "</div></div></div></div>";

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
