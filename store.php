<?php
session_start();
include('model/config.php');
include('model/page_config.php');

// Simulate adding/removing the product to/from the cart
if (!isset($_SESSION["nivas_cart$user_id"])) {
  $_SESSION["nivas_cart$user_id"] = array();
}
$total_cart_items = count($_SESSION["nivas_cart$user_id"]);
$total_cart_price = 0;

$t_manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(id) FROM manuals_$school_id WHERE dept = $user_dept AND status = 'open'"))[0];

$manual_query = mysqli_query($conn, "SELECT * FROM manuals_$school_id WHERE dept = $user_dept AND status = 'open' ORDER BY `id` DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Store - Nivasity</title>

  <?php include('partials/_head.php') ?>
</head>

<body>
  <div class="container-scroller sidebar-fixed">
    <!-- partial:partials/_navbar.html -->
    <?php include('partials/_navbar.php') ?>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_sidebar_user.php -->
      <?php include('partials/_sidebar_user.php') ?>
      <!-- partial -->
      <div class="main-panel">

        <div class="content-wrapper">
          <div class="row">
            <div class="col-sm-12">
              <div class="home-tab">
                <div class="d-flex align-items-center justify-content-between border-bottom">
                  <ul class="nav nav-tabs d-flex" role="tablist">
                    <li class="nav-item">
                      <a class="nav-link px-3 active ps-0 fw-bold" id="store-tab" data-bs-toggle="tab" href="#store"
                        role="tab" aria-controls="store" aria-selected="true">Store</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold" id="cart-tab" data-bs-toggle="tab" href="#cart" role="tab"
                        aria-selected="false">Shopping Cart (<span id="cart-count"><?php echo $total_cart_items; ?></span>)</a>
                    </li>
                  </ul>
                </div>
                <div class="tab-content tab-content-basic">
                  <div class="tab-pane fade show active" id="store" role="tabpanel" aria-labelledby="store">
                    <div class="row">
                      <div class="col-5 col-md-3 offset-md-9 form-group me-2">
                        <p class="text-muted">Sort By:</p>
                        <select class="form-control w-100" name="sort-by" id="sort-by">
                          <option value="1">Due Date</option>
                          <option value="2">Price: Low to High</option>
                          <option value="3">Price: High to Low</option>
                        </select>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-lg-12 d-flex flex-column">
                        <div class="row flex-grow sortables">
                          <?php
                          if (mysqli_num_rows($manual_query) > 0) {
                          $count_row = mysqli_num_rows($manual_query);

                          while ($manual = mysqli_fetch_array($manual_query)) {
                            $manual_id = $manual['id'];
                            $seller_id = $manual['user_id'];

                            // Check if the manual has been bought by the current user
                            $is_bought_query = mysqli_query($conn, "SELECT COUNT(*) AS count FROM manuals_bought_$school_id WHERE manual_id = $manual_id AND buyer = $user_id");
                            $is_bought_result = mysqli_fetch_assoc($is_bought_query);

                            // If the manual has been bought, skip it
                            if ($is_bought_result['count'] > 0) {
                              $count_row = $count_row - 1;
                              continue;
                            }

                            $seller_q = mysqli_fetch_array(mysqli_query($conn, "SELECT first_name, last_name FROM users WHERE id = $seller_id"));
                            $seller_fn = $seller_q['first_name'];
                            $seller_ln = $seller_q['last_name'];

                            // Retrieve and format the due date
                            $due_date = date('j M, Y', strtotime($manual['due_date']));
                            $due_date2 = date('Y-m-d', strtotime($manual['due_date']));
                            // Retrieve the status
                            $status = $manual['status'];
                            $status_c = 'success';

                            if ($date > $due_date2) {
                              $status = 'disabled';
                              $status_c = 'danger';
                            }

                            // Check if the manual is already in the cart
                            $is_in_cart = in_array($manual_id, $_SESSION["nivas_cart$user_id"]);

                            // Update the Add to Cart button based on cart status
                            $button_text = $is_in_cart ? 'Remove' : 'Add to Cart';
                            $button_class = $is_in_cart ? 'btn-primary' : 'btn-outline-primary';

                            ?>
                          <div class="col-12 col-md-4 grid-margin stretch-card sortable-card">
                            <div class="card card-rounded shadow-sm">
                              <div class="card-body">
                                <h4 class="card-title"><?php echo $manual['title'] ?> <span class="text-secondary">- <?php echo $manual['course_code'] ?></span></h4>
                                <div class="media">
                                  <i class="mdi mdi-book icon-lg text-secondary d-flex align-self-start me-3"></i>
                                  <div class="media-body">
                                    <h3 class="fw-bold price">₦ <?php echo $manual['price'] ?></h3>
                                    <p class="card-text">
                                      Due date:<span class="fw-bold text-<?php echo $status_c ?> due_date"> <?php echo $due_date ?></span><br>
                                      <span class="text-secondary"><?php echo $seller_fn.' '. $seller_ln ?> (HOC)</span>
                                    </p>
                                  </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                  <?php if($status != 'disabled'):?>
                                    <a href="javascript:;">
                                      <i class="mdi mdi-share-variant icon-md text-muted" data-title="<?php echo $manual['title']; ?>" data-manual_id="<?php echo $manual['id']; ?>"></i>
                                    </a>
                                    <button class="btn <?php echo $button_class; ?> btn-lg m-0 cart-button" data-product-id="<?php echo $manual['id']; ?>">
                                      <?php echo $button_text; ?>
                                    </button>
                                  <?php else: ?>
                                    <h4 class="fw-bold text-danger">Overdue !</h4>
                                  <?php endif; ?>
                                  </div>
                                </div>
                              </div>
                            </div>

                          <?php
                              }
                            if ($count_row == 0) { ?>
                              <div class="col-12">
                                  <div class="card card-rounded shadow-sm">
                                    <div class="card-body">
                                      <h5 class="card-title">All manuals have been bought</h5>
                                      <p class="card-text">Check back later when your HOC uploads a new manual.</p>
                                    </div>
                                  </div>
                              </div>
                            <?php }
                          } else {
                              // Display a message when no manuals are found
                              ?>
                              <div class="col-12">
                                  <div class="card card-rounded shadow-sm">
                                    <div class="card-body">
                                      <h5 class="card-title text-center">No manuals available.</h5>
                                      <p class="card-text text-center">Check back later when your HOC uploads a new manual.</p>
                                    </div>
                                  </div>
                              </div>
                              <?php } ?>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade hide" id="cart" role="tabpanel" aria-labelledby="cart">
                    <div class="row flex-grow">
                      <div class="col-sm-8 grid-margin stretch-card">
                        <div class="card card-rounded shadow-sm">
                          <div class="card-body">
                            <div class="table-responsive  mt-1">
                              <table class="table table-hover select-table">
                                <thead>
                                  <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Due Date</th>
                                    <th>Action</th>
                                  </tr>
                                </thead>
                                <tbody>
                                <?php
                                foreach ($_SESSION["nivas_cart$user_id"] as $cart_item_id) {
                                  // Fetch details of the carted item based on $cart_item_id
                                  $cart_item = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM manuals_$school_id WHERE id = $cart_item_id"));
                                  
                                  // Retrieve and format the due date
                                  $due_date = date('j M, Y', strtotime($cart_item['due_date']));
                                  $due_date2 = date('Y-m-d', strtotime($cart_item['due_date']));
                                  // Retrieve the status
                                  $status = $cart_item['status'];
                                  $status_c = '';

                                  if ($date > $due_date2 || $status == 'closed') {
                                    $status = 'disabled';
                                    $status_c = 'danger';
                                  } else {
                                    $total_cart_price = $total_cart_price + $cart_item['price'];
                                  }
                                  ?>
                                  <tr>
                                    <td>
                                      <div class="d-flex">
                                        <div>
                                          <h6><?php echo $cart_item['course_code'] ?></h6>
                                          <?php if($status_c == 'danger'): ?>
                                            <p class="text-danger fw-bold">Item Overdue</p>
                                          <?php endif; ?>
                                          </div>
                                        </div>
                                    </td>
                                    <td>
                                      <h6>&#8358; <?php echo $cart_item['price'] ?></h6>
                                    </td>
                                    <td>
                                      <h6 class="text-<?php echo $status_c ?>"><?php echo $due_date ?></h6>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary mb-0 btn-block remove-cart" data-cart_id="<?php echo $cart_item_id?>">Remove</button>
                                    </td>
                                  </tr>
                                <?php } ?>
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
                                <h4 class="card-title card-title-dash">Order Summary</h4>
                              </div>
                            </div><hr>
                            <div class="d-flex justify-content-between my-3 fw-bold">
                              <p>Subtotal</p>
                              <h3>₦ <?php echo $total_cart_price ?></h3>
                            </div>
                            <?php 
                              // Assuming $transferAmount contains the transfer amount
                              $transferAmount = $total_cart_price;

                              if ($transferAmount == 0) {
                                $charge = 0;
                              } elseif ($transferAmount <= 5000) {
                                $charge = 25;
                              } elseif ($transferAmount <= 50000) {
                                $charge = 65;
                              } else {
                                $charge = 150;
                              }

                              // Add the charge to the total
                              $transferAmount += $charge;
                            ?>
                            <div class="d-flex justify-content-between my-3 fw-bold">
                              <p>Handling fee</p>
                              <h5>₦ <?php echo $charge ?></h5>
                            </div>
                            <?php if($total_cart_price > 0): ?>
                              <button class="btn fw-bold btn-primary w-100 mb-0 btn-block py-3 checkout-cart" data-total_amount="<?php echo $total_cart_price ?>" data-transfer_amount="<?php echo $transferAmount ?>">CHECKOUT (₦ <?php echo $transferAmount ?>)</button>
                            <?php else: ?>
                              <button class="btn fw-bold btn-primary w-100 mb-0 btn-block py-3" disabled>CHECKOUT</button>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Spinner Start -->
                  <!-- <div id="spinner"
                    class="show position-fixed translate-middle top-50 start-50 d-flex align-items-center justify-content-center">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                      <span class="sr-only"></span>
                    </div>
                  </div> -->
                  <!-- Spinner End -->
                </div>

              </div>
            </div>
          </div>

        </div>
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.php -->
        <?php include('partials/_footer.php') ?>
        <!-- partial -->
      </div>

      <!-- Bootstrap alert container -->
      <div id="alertBanner"
        class="alert alert-success text-center alert-dismissible end-2 top-2 fade show position-fixed w-auto p-2 px-4"
        role="alert" style="z-index: 5000; display: none;">
        An error occurred during the AJAX request.
      </div>
      <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->

  <!-- plugins:js -->
  <script src="assets/vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="assets/vendors/chart.js/Chart.min.js"></script>
  <script src="assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.1/mdb.min.js"></script>
  <script src="assets/vendors/progressbar.js/progressbar.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="assets/js/js/off-canvas.js"></script>
  <script src="assets/js/js/hoverable-collapse.js"></script>
  <script src="assets/js/js/template.js"></script>
  <script src="assets/js/js/settings.js"></script>
  <script src="assets/js/js/data-table.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="assets/js/js/dashboard.js"></script>
  <script src="assets/js/script.js"></script>

  <script>
    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');

      $('#sort-by').change(function () {
        var sortByValue = $(this).val();
        sortCards(sortByValue);
      });

      $('.go-to-cart-button').on('click', function () {
          $('#cart-tab').tab('show');
      });

      $('.mdi-share-variant').on('click', function () {
        var button = $(this);
        var manual_id = button.data('manual_id');
        var title = button.data('title');
        var shareText = 'Check out "' + title + '" Manual! Get all the details and order now.';
        var shareUrl = "https://nivasity.com/store.php?manual="+manual_id;

        // Check if the Web Share API is available
        if (navigator.share) {
          navigator.share({
            title: document.title,
            text: shareText,
            url: shareUrl,
          })
            .then(() => console.log('Shared successfully'))
            .catch((error) => console.error('Error sharing:', error));
        } else {
          // Fallback for platforms that do not support Web Share API
          // You can add specific share URLs for each platform here
          alert('Web Share API not supported. You can manually share the link.');
        }
      });

      function sortCards(sortBy) {
        var $container = $('.sortables');
        var $cards = $container.children('.sortable-card');

        // Fade out the cards before sorting
        $cards.fadeOut(400, function () {

          $cards.sort(function (a, b) {
            var aValue, bValue;

            // Extract values based on the selected option
            switch (sortBy) {
              case '1': // Latest product (Assuming the due date is in the format 'Sun, Dec 4')
                aValue = new Date($(a).find('.due_date').text()).getTime();
                bValue = new Date($(b).find('.due_date').text()).getTime();
                break;
              case '2': // Lowest price
                aValue = parseFloat($(a).find('.price').text().replace('₦ ', ''));
                bValue = parseFloat($(b).find('.price').text().replace('₦ ', ''));
                break;
              case '3': // Highest price
                aValue = parseFloat($(b).find('.price').text().replace('₦ ', ''));
                bValue = parseFloat($(a).find('.price').text().replace('₦ ', ''));
                break;
              default:
                break;
            }

            // Compare the values
            return aValue - bValue;
          });

          $container.html($cards);

          // Fade in the cards after sorting
          $cards.fadeIn(400);
        });
      }

      // Add to Cart button click event
      $('.remove-cart').on('click', function () {
        var button = $(this);
        var product_id = button.data('cart_id');

        // Make AJAX request to PHP file
        $.ajax({
          type: 'POST',
          url: 'model/cart.php', // Replace with your PHP file handling the cart logic
          data: { product_id: product_id, action: 0 },
          success: function (data) {
            // Update the total number of carted products
            $('#cart-count').text(data.total);

            // Reload the cart table
            reloadCartTable();
            location.reload();
          },
          error: function () {
            // Handle error
            console.error('Error in AJAX request');
          }
        });
      });

      // Add to Cart button click event
      $('.cart-button').on('click', function () {
        var button = $(this);
        var product_id = button.data('product-id');

        // Toggle button appearance and text
        if (button.hasClass('btn-outline-primary')) {
          button.toggleClass('btn-outline-primary btn-primary').text('Remove');
          action = 1;
        } else {
          button.toggleClass('btn-outline-primary btn-primary').text('Add to Cart');
          action = 0;
        }

        // Make AJAX request to PHP file
        $.ajax({
          type: 'POST',
          url: 'model/cart.php', // Replace with your PHP file handling the cart logic
          data: { product_id: product_id, action: action },
          success: function (data) {
            // Update the total number of carted products
            $('#cart-count').text(data.total);

            // Reload the cart table
            reloadCartTable();
          },
          error: function () {
            // Handle error
            console.error('Error in AJAX request');
          }
        });
      });

      // Function to reload the cart table
      function reloadCartTable() {
        $.ajax({
          type: 'POST',
          url: 'model/cart.php',
          data: { reload_cart: 'reload_cart' },
          success: function (html) {
            $('#cart').html(html);
          },
          error: function () {
            // Handle error
            console.error('Error in reloading cart table');
          }
        });
      }

      // Add to Cart button click event
      $('#cart').on('click', '.checkout-cart', function() {
        amount = $(this).data('transfer_amount');
        subaccount_amount = $(this).data('total_amount');

        function generateUniqueID() {
          const currentDate = new Date();
          const uniqueID = `nivas_<?php echo $user_id ?>_${currentDate.getTime()}`;
          return uniqueID;
        }

        const myUniqueID = generateUniqueID();

        $.ajax({
          url: 'model/getKey.php',
          type: 'POST',
          data: { getKey: 'get-Key'},
          success: function (data) {
            var flw_pk = data.flw_pk;

            // Call FlutterwaveCheckout with the retrieved flw_pk
            FlutterwaveCheckout({
              public_key: flw_pk,
              tx_ref: myUniqueID,
              amount: amount,
              currency: "NGN",
              subaccounts: [
                {
                  id: "RS_96A0AE6B6C1F9347B538451A1E4F6C0E",
                  transaction_charge_type: "flat_subaccount",
                  transaction_charge: subaccount_amount,
                }
              ],
              payment_options: "card, banktransfer, ussd",
              redirect_url: "https://stage.nivasity.com/model/handle-fw-payment.php",
              meta: {
                consumer_id: 23,
                consumer_mac: "92a3-912ba-1192a",
              },
              customer: {
                email: "akinyemisamuel170@gmail.com",
                phone_number: "07048706198",
                name: "Samuel Akinyemi",
              },
              customizations: {
                title: "NIVASITY PAY",
                description: "Student manual payment",
                logo: "https://stage.nivasity.com/favicon.ico",
              },
            });
          }
        });
      });

    });
  </script>
  <!-- End custom js for this page-->
</body>

</html>