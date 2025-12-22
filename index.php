<?php
session_start();
include('model/config.php');
include('model/page_config.php');
include('model/system_alerts.php');
include('model/payment_freeze.php');

// Fetch active system alerts
$system_alerts = get_active_system_alerts($conn);

// Check payment freeze status
$payment_freeze_info = get_payment_freeze_info();

// Simulate adding/removing the product to/from the cart
if (!isset($_SESSION["nivas_cart$user_id"])) {
  $_SESSION["nivas_cart$user_id"] = array();
}
if (!isset($_SESSION["nivas_cart_event$user_id"])) {
  $_SESSION["nivas_cart_event$user_id"] = array();
}
$total_cart_items = count($_SESSION["nivas_cart$user_id"]) + count($_SESSION["nivas_cart_event$user_id"]);
$total_cart_price = 0;

$t_manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(id) FROM manuals WHERE dept = $user_dept AND status = 'open' AND school_id = $school_id"))[0];

$manual_query = mysqli_query($conn, "SELECT * FROM manuals WHERE dept = $user_dept AND status = 'open' AND school_id = $school_id ORDER BY `id` DESC");

$event_query = mysqli_query($conn, "SELECT * FROM events WHERE status = 'open' ORDER BY `id` DESC");

// Determine if the Store tab should be shown
$show_store = (isset($_SESSION['nivas_userRole']) && $_SESSION['nivas_userRole'] !== 'org_admin' && $_SESSION['nivas_userRole'] !== 'visitor');
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
          <?php 
          // Display system alerts at the top of the page
          if (!empty($system_alerts)) {
            echo render_system_alerts($system_alerts);
          }
          ?>
          <div class="row">
            <div class="col-sm-12 px-2">
              <div class="home-tab">
                <div class="d-flex align-items-center justify-content-between border-bottom">
                  <ul class="nav nav-tabs d-flex" role="tablist">
                    <?php if ($show_store): ?>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold active" id="store-tab" data-bs-toggle="tab" href="#store"
                        role="tab" aria-controls="store" aria-selected="true">Store</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold<?php echo $show_store ? '' : ' active'; ?>" id="events-tab" data-bs-toggle="tab" href="#events"
                        role="tab" aria-controls="events" aria-selected="<?php echo $show_store ? 'false' : 'true'; ?>">Events</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold" id="cart-tab" data-bs-toggle="tab" href="#cart" role="tab"
                        aria-selected="false">Cart (<span id="cart-count"><?php echo $total_cart_items; ?></span>)</a>
                    </li>
                  </ul>
                </div>
                <div class="tab-content tab-content-basic">
                  <?php if ($show_store): ?>
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
                              $is_bought_query = mysqli_query($conn, "SELECT COUNT(*) AS count FROM manuals_bought WHERE manual_id = $manual_id AND buyer = $user_id AND school_id = $school_id");
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
                                if (abs(strtotime($date) - strtotime($due_date2)) > 10 * 24 * 60 * 60) {
                                  $count_row = $count_row - 1;
                                  continue;
                                }
                              }

                              // Check if the manual is already in the cart
                              $is_in_cart = in_array($manual_id, $_SESSION["nivas_cart$user_id"]);

                              // Update the Add to Cart button based on cart status
                              $button_text = $is_in_cart ? 'Remove' : 'Add to Cart';
                              $button_class = $is_in_cart ? 'btn-primary' : 'btn-outline-primary';

                              ?>
                                  <div class="col-12 col-md-6 col-lg-4 col-xl-3 grid-margin px-2 stretch-card sortable-card">
                                    <div class="card card-rounded shadow-sm">
                                      <div class="card-body">
                                        <h4 class="card-title"><?php echo $manual['title'] ?> <span class="text-secondary">- <?php echo $manual['course_code'] ?></span></h4>
                                        <div class="media">
                                          <i class="mdi mdi-book icon-lg text-secondary d-flex align-self-start me-3"></i>
                                          <div class="media-body">
                                            <h3 class="fw-bold price">₦ <?php echo number_format($manual['price']) ?></h3>
                                            <p class="card-text">
                                              Due date:<span class="fw-bold text-<?php echo $status_c ?> due_date"> <?php echo $due_date ?></span><br>
                                              <span class="text-secondary"><?php echo $seller_fn . ' ' . $seller_ln ?> (HOC/Lecturer)</span>
                                            </p>
                                          </div>
                                        </div>
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                          <?php if ($status != 'disabled'): ?>
                                                <a href="javascript:;" title="Copy share link">
                                                  <i class="mdi mdi-share-variant icon-md text-muted share_button" data-title="<?php echo $manual['title']; ?>" data-product_id="<?php echo $manual['id']; ?>" data-type="product"></i>
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
                                              <h5 class="card-title">All materials have been bought</h5>
                                              <p class="card-text">Check back later when your HOC/Lecturer uploads a new manual.</p>
                                            </div>
                                          </div>
                                      </div>
                                <?php }
                          } else {
                            // Display a message when no materials are found
                            ?>
                                  <div class="col-12">
                                      <div class="card card-rounded shadow-sm">
                                        <div class="card-body">
                                          <h5 class="card-title text-center">No material available.</h5>
                                          <p class="card-text text-center">Check back later when your HOC/Lecturer uploads a new manual.</p>
                                        </div>
                                      </div>
                                  </div>
                              <?php } ?>
                        </div>
                      </div>
                    </div>
                  </div>
                  <?php endif; ?>
                  <div class="tab-pane fade <?php echo $show_store ? 'hide' : 'show active'; ?>" id="events" role="tabpanel" aria-labelledby="events">
                    <div class="row">
                      <div class="col-5 col-md-3 offset-md-9 form-group me-2">
                        <p class="text-muted">Sort By:</p>
                        <select class="form-control w-100" name="sort-by" id="sort-by">
                          <option value="1">Event Date</option>
                          <option value="2">Price: Low to High</option>
                          <option value="3">Price: High to Low</option>
                        </select>
                      </div>
                    </div>

                    <div class="row">
                      <div class="col-lg-12 d-flex flex-column">
                        <div class="row flex-grow sortables">
                          <?php
                          if (mysqli_num_rows($event_query) > 0) {
                            $count_row = mysqli_num_rows($event_query);

                            while ($event = mysqli_fetch_array($event_query)) {
                              $event_id = $event['id'];
                              $seller_id = $event['user_id'];

                              // Check if the event has been bought by the current user
                              $is_bought_query = mysqli_query($conn, "SELECT COUNT(*) AS count FROM event_tickets WHERE event_id = $event_id AND buyer = $user_id");
                              $is_bought_result = mysqli_fetch_assoc($is_bought_query);

                              // If the event has been bought, skip it
                              if ($is_bought_result['count'] > 0) {
                                $count_row = $count_row - 1;
                                continue;
                              }

                              $seller_q = mysqli_fetch_array(mysqli_query($conn, "SELECT first_name, last_name FROM users WHERE id = $seller_id"));
                              $organisation = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM organisation WHERE user_id = $seller_id"));
                              $seller_fn = $seller_q['first_name'];
                              $seller_ln = $seller_q['last_name'];

                              // Retrieve and format the event_date and time
                              $event_date = date('j M', strtotime($event['event_date']));
                              $event_date2 = date('Y-m-d', strtotime($event['event_date']));
                                    
                              $event_time = date('g:i A', strtotime($event['event_time']));
                              $event_time2 = date('H:i', strtotime($event['event_time']));

                              // Retrieve the status
                              $status = $event['status'];
                              $status_c = 'success';

                              if ($date > $event_date2) {
                                $status = 'disabled';
                                $status_c = 'danger';
                                if (abs(strtotime($date) - strtotime($event_date2)) > 10 * 24 * 60 * 60) {
                                  $count_row = $count_row - 1;
                                  continue;
                                }
                              }

                              if ($event['event_type'] == 'school') {
                                $location = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM schools WHERE id = ".$event['school']))['code'];
                              } elseif ($event['event_type'] == 'public') {
                                $location = $event['location'];
                              } else {
                                $location = "Online Event";
                              }

                              // Check if the event is already in the cart
                              $is_in_cart = in_array($event_id, $_SESSION["nivas_cart_event$user_id"]);

                              // Update the Add to Cart button based on cart status
                              $button_text = $is_in_cart ? 'Remove' : 'Get Ticket';
                              $button_class = $is_in_cart ? 'btn-primary' : 'btn-outline-primary';
                              $event_price = number_format($event['price']);
                              $event_price = $event_price > 0 ? "₦ $event_price" : 'FREE';

                              ?>
                                  <div class="col-12 col-md-6 col-lg-4 col-xl-3 grid-margin px-2 stretch-card">
                                    <div class="card card-rounded shadow-sm">
                                      <div class="card-body p-0">
                                        <img src="assets/images/events/<?php echo $event['event_banner'] ?>" class="img-fluid rounded-top w-100" style="max-height: 140px; object-fit: cover;">
                                        <div class="p-3">
                                          <p class="fw-bold text-secondary"><i class="mdi mdi-map-marker menu-icon"></i> <?php echo $location ?></p>
                                          <h4 class="fw-bold text-uppercase"><?php echo $event['title'] ?></h4>
                                          <small class="fw-bold"><?php echo $event_date ?> • <?php echo $event_time ?></small><br>
                                          <small class="badge badge-success fw-bold text-uppercase mt-2"><?php echo $event_price ?></small>
                                          <p>Host: <span class="fw-bold text-secondary"><?php echo $organisation['business_name'] ?></span></p>
                                          <hr>
                                          <div class="d-flex justify-content-between">
                                            <a href="javascript:;">
                                              <i class="mdi mdi-share-variant icon-md text-muted share_button" title="Copy share link" data-title="<?php echo $event['title']; ?>" data-product_id="<?php echo $event['id']; ?>" data-type="event"></i>
                                            </a>
                                            <button class="btn <?php echo $button_class; ?>  btn-lg m-0 cart-event-button" data-event-id="<?php echo $event['id'] ?>" data-mdb-ripple-duration="0"><?php echo $button_text; ?></button>
                                          </div>
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
                                              <h5 class="card-title">All events have been bought</h5>
                                              <p class="card-text">Check back later when a new event is uploaded.</p>
                                            </div>
                                          </div>
                                      </div>
                                <?php }
                          } else {
                            // Display a message when no events are found
                            ?>
                                  <div class="col-12">
                                      <div class="card card-rounded shadow-sm">
                                        <div class="card-body">
                                          <h5 class="card-title text-center">No event available.</h5>
                                          <p class="card-text text-center">Check back later when a new event is uploaded.</p>
                                        </div>
                                      </div>
                                  </div>
                              <?php } ?>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade hide" id="cart" role="tabpanel" aria-labelledby="cart">
                    
                  </div>
                  

                  <!-- User verifyTransaction Modal -->
                  <div class="modal fade" id="verifyTransaction" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="verifyTransactionLabel"
                    aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h4 class="modal-title fw-bold" id="verifyTransactionLabel">Verifying transaction...</h4>
                        </div>
                        <div class="modal-body">
                          <h4 class="text-center">
                            <div class="spinner-grow text-secondary spinner-1 me-1 mb-3" role="status">
                              <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="spinner-grow text-secondary spinner-2 me-1 mb-3" role="status">
                              <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="spinner-grow text-secondary spinner-3 mb-3" role="status">
                              <span class="visually-hidden">Loading...</span>
                            </div>
                            <br>
                            Please hang on in just a few seconds so we can verify your payment...
                          </h4>
                        </div>
                      </div>
                    </div>
                  </div>

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
  <script src="assets/js/main.js"></script>
  <script>
    const urlParams = new URLSearchParams(window.location.search);
    // Get the logout parameter from the URL
    const cart = urlParams.get('cart');
    const manualId = urlParams.get('manual_id') || urlParams.get('product_id');

    // Check if the verify parameter is present
    if (cart) {
      $('.nav-link').removeClass('active');
      $('#cart-tab').addClass('active');

      // Show the corresponding tab content
      $('.tab-pane').removeClass('show active');
      $('#cart').addClass('show active');
    }
    
    // If a manual is specified in the URL, open Store and show modal
    if (manualId) {
      $('.nav-link').removeClass('active');
      $('#store-tab').addClass('active');

      $('.tab-pane').removeClass('show active');
      $('#store').addClass('show active');

      // Load and show the manual details modal
      $.ajax({
        type: 'GET',
        url: 'model/manual_details.php',
        data: { manual_id: manualId },
        success: function (html) {
          $('#manualModal .modal-content').html(html);
          $('#manualModal').modal('show');
        },
        error: function () {
          console.error('Failed to load material details');
        }
      });
    }

    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');

      // $('#sort-by').change(function () {
      //   var sortByValue = $(this).val();
      //   sortCards(sortByValue);
      // });

      $('.go-to-cart-button').on('click', function () {
          $('#cart-tab').tab('show');
      });

      function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(function(){
            $('#alertBanner').removeClass('alert-info alert-danger').addClass('alert-success');
            $('#alertBanner').html('Link copied to clipboard');
            if (typeof showAlert === 'function') { showAlert(); }
          }, function(){
            // Fallback
            var temp = $('<input>');
            $('body').append(temp);
            temp.val(text).select();
            document.execCommand('copy');
            temp.remove();
            $('#alertBanner').removeClass('alert-info alert-danger').addClass('alert-success');
            $('#alertBanner').html('Link copied to clipboard');
            if (typeof showAlert === 'function') { showAlert(); }
          });
        } else {
          var temp = $('<input>');
          $('body').append(temp);
          temp.val(text).select();
          document.execCommand('copy');
          temp.remove();
          $('#alertBanner').removeClass('alert-info alert-danger').addClass('alert-success');
          $('#alertBanner').html('Link copied to clipboard');
          if (typeof showAlert === 'function') { showAlert(); }
        }
      }

      function isMobileDevice() {
        return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
      }

      $(document).on('click', '.share_button', function (e) {
        var button = $(this);
        var product_id = button.data('product_id');
        var type = button.data('type');
        var title = button.data('title');
        var shareText = 'Check out '+title+' on nivasity and order now!';

        if (type == 'product') {
          var shareUrl = "https://funaab.nivasity.com/store_share.php?manual_id="+product_id;
        } else {
          var shareUrl = "https://funaab.nivasity.com/event_details.php?event_id="+product_id;
        }

        // Mobile uses native share; desktop copies link
        if (isMobileDevice() && navigator.share) {
          navigator.share({ title: document.title, text: shareText, url: shareUrl })
            .catch(function(){ copyToClipboard(shareUrl); });
        } else {
          copyToClipboard(shareUrl);
        }
      });

      reloadCartTable()

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
      $(document).on('click', '.remove-cart', function (e) {
        var button = $(this);
        var type = button.data('type');
        var product_id = button.data('cart_id');

        // Make AJAX request to PHP file
        $.ajax({
          type: 'POST',
          url: 'model/cart.php', // Replace with your PHP file handling the cart logic
          data: { product_id: product_id, action: 0, type: type },
          success: function (data) {
            // Update the total number of carted products
            $('#cart-count').text(data.total);

            // Reload the cart table
            reloadCartTable();

            // Change the button text of the tag with data-product-id as the removed product ID
            btn_text = 'Add to Cart';
            if (type == 'event') {
              btn_text = 'Get Ticket';
            }
            $('button[data-'+type+'-id="' + product_id + '"]').toggleClass('btn-outline-primary btn-primary').text(btn_text);
          },
          error: function () {
            // Handle error
            console.error('Error in AJAX request');
          }
        });
      });

      // Add to Cart button click event (delegated for dynamic content)
      $(document).on('click', '.cart-button', function () {
        var button = $(this);
        if (button.prop('disabled') || button.hasClass('disabled')) {
          return;
        }
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
          url: 'model/cart_manual.php', // Replace with your PHP file handling the cart logic
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

      // Add to cart-event-button click event
      $('.cart-event-button').on('click', function () {
        var button = $(this);
        var event_id = button.data('event-id');

        // Toggle button appearance and text
        if (button.hasClass('btn-outline-primary')) {
          button.toggleClass('btn-outline-primary btn-primary').text('Remove');
          action = 1;
        } else {
          button.toggleClass('btn-outline-primary btn-primary').text('Get Ticket');
          action = 0;
        }

        // Make AJAX request to PHP file
        $.ajax({
          type: 'POST',
          url: 'model/cart_event.php', // Replace with your PHP file handling the cart logic
          data: { event_id: event_id, action: action },
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

      // Pending payments: cancel
      $('#cart').on('click', '.pending-cancel', function() {
        var btn = $(this);
        var ref = btn.data('ref_id');
        btn.prop('disabled', true);
        $.ajax({
          type: 'POST',
          url: 'model/verify-pending-payment.php',
          dataType: 'json',
          data: { ref_id: ref, action: 'cancel' },
          success: function (res) {
            var $ab = $('#alertBanner');
            if ($ab.length) {
              $ab.removeClass('alert-info alert-danger alert-success alert-warning');
              $ab.addClass('alert-success');
              $ab.text('Pending payment cancelled.');
            }
            if (typeof showAlert === 'function') { showAlert(); }
            reloadCartTable();
          },
          complete: function () {
            btn.prop('disabled', false);
          },
          error: function () {
            console.error('Error cancelling pending payment');
          }
        });
      });

      // Pending payments: verify via Flutterwave
      $('#cart').on('click', '.pending-verify', function() {
        var btn = $(this);
        var ref = btn.data('ref_id');
        var orig = btn.text();
        btn.prop('disabled', true).text('Checking...');
        $.ajax({
          type: 'POST',
          url: 'model/verify-pending-payment.php',
          dataType: 'json',
          data: { ref_id: ref, action: 'verify' },
          success: function (res) {
            if (res.status === 'success') {
              location.reload();
            } else {
              var $ab = $('#alertBanner');
              if ($ab.length) {
                $ab.removeClass('alert-info alert-danger alert-success alert-warning');
                $ab.addClass('alert-warning');
                $ab.text(res.message || 'Payment not found yet. If you paid, please try again later.');
              }
              if (typeof showAlert === 'function') { showAlert(); }
              reloadCartTable();
            }
          },
          complete: function () {
            btn.prop('disabled', false).text(orig);
          },
          error: function () {
            console.error('Error verifying payment');
          }
        });
      });

      // Add to Cart button click event
      $('#cart').on('click', '.checkout-cart', function() {
        // Check if payments are frozen
        <?php if ($payment_freeze_info): ?>
          // Show payment freeze modal
          $('#paymentFreezeMessage').text(<?php echo json_encode($payment_freeze_info['message'] ?? 'Payments are currently paused.'); ?>);
          $('#paymentFreezeModal').modal('show');
          return; // Stop checkout process
        <?php endif; ?>

        email = "<?php echo $user_email ?>";
        phone = "<?php echo $user_phone ?>";
        u_name = "<?php echo $user_name ?>";
        transfer_amount = $(this).data('transfer_amount');
            
        // Retrieve and parse session data safely
        sessionData = $(this).data('session_data');
        // Check if sessionData is an object or a string
        let parsedSessionData;
        if (typeof sessionData === "string") {
            try {
                parsedSessionData = JSON.parse(sessionData); // Parse if it's a string
            } catch (error) {
                console.error("Error parsing session data:", error);
                return; // Exit if parsing fails
            }
        } else {
            parsedSessionData = sessionData; // Use as is if it's already an object
        }
                
        // Convert parsedSessionData to an array if it's an object
        if (typeof parsedSessionData === "object" && !Array.isArray(parsedSessionData)) {
          parsedSessionData = Object.values(parsedSessionData);
        }

        // Check the type and log the parsed session data for debugging
        console.log('parsedSessionData:', parsedSessionData);
        console.log('Type of parsedSessionData:', typeof parsedSessionData);

        function generateUniqueID() {
            const currentDate = new Date();
            const uniqueID = `nivas_<?php echo $user_id ?>_${currentDate.getTime()}`;
            return uniqueID;
        }

        const myUniqueID = generateUniqueID();

        // Create the subaccounts array from parsed session data
        let subaccounts = [];
        let sellerTotals = {};

        $.each(parsedSessionData, function(key, item) {
            const price = parseFloat(item.price);
            if (sellerTotals[item.seller]) {
                sellerTotals[item.seller] += price;
            } else {
                sellerTotals[item.seller] = price;
            }
        });

        for (const seller in sellerTotals) {
            subaccounts.push({
                id: seller,
                transaction_charge_type: "flat_subaccount",
                transaction_charge: sellerTotals[seller]
            });
        }

        console.log('Subaccounts:', subaccounts);

        // Get payment gateway keys first to determine active gateway
        $.ajax({
          url: 'model/getKey.php',
          type: 'POST',
          data: { getKey: 'get-Key'},
          success: function (data) {
            // Check if payment is frozen (server-side double-check)
            if (data.payment_frozen || data.error) {
              $('#paymentFreezeMessage').text(data.message || 'Payments are currently paused.');
              $('#paymentFreezeModal').modal('show');
              return;
            }

            var activeGateway = data.active_gateway || 'flutterwave';
            var flw_pk = data.flw_pk;
            var ps_pk = data.paystack_pk;
            
            console.log('Active Gateway:', activeGateway);

            // Now save cart with gateway information
            $.ajax({
              url: 'model/saveCart.php',
              type: 'POST',
              contentType: 'application/json',
              data: JSON.stringify({
                ref_id: myUniqueID,
                user_id: "<?php echo $user_id; ?>",
                gateway: activeGateway,
                items: parsedSessionData.map(item => ({
                  item_id: item.product_id,
                  type: item.type
                }))
              }),
              success: function(response) {
                if (response.success) {
                  console.log("Cart saved with gateway:", activeGateway, response.message);
                } else {
                  console.error("Error saving cart:", response.message);
                }
              }
            });
            
            // Route to the appropriate payment gateway
            if (activeGateway === 'flutterwave') {
              // Call FlutterwaveCheckout with the retrieved flw_pk and dynamically generated subaccounts
              FlutterwaveCheckout({
                public_key: flw_pk,
                tx_ref: myUniqueID,
                amount: transfer_amount,
                currency: "NGN",
                subaccounts: subaccounts,
                payment_options: "card, banktransfer, ussd",
                // redirect_url: "https://funaab.nivasity.com/model/handle-fw-payment.php",
                callback: function(payment) {
                  console.log(payment);
                  // Send AJAX verification request to backend
                  verifyTransactionOnBackend(payment.transaction_id, payment.tx_ref);
                },
                onclose: function(status) {
                  if (!status) {
                    console.log(status);

                    // Show the modal with jQuery
                    $('#verifyTransaction').modal({
                      backdrop: 'static',
                      keyboard: false
                    }).modal('show');
                    
                    $('.spinner-grow').hide();
                    
                    // Show each spinner with a delay for a staggered effect
                    setTimeout(function() { $('.spinner-1').show(); }, 100);
                    setTimeout(function() { $('.spinner-2').show(); }, 300);
                    setTimeout(function() { $('.spinner-3').show(); }, 600);
                  }
                },
                customer: {
                    email: email,
                    phone_number: phone,
                    name: u_name,
                },
              });
            } else if (activeGateway === 'paystack') {
              // Prepare Paystack split: request flat split_code before inline launch
              const amountKobo = Math.round(transfer_amount * 100);
              const sellerPayload = [];
              for (const seller in sellerTotals) {
                sellerPayload.push({ id: seller, total: sellerTotals[seller] });
              }

              function launchPaystack(splitCode) {
                var options = {
                  key: ps_pk,
                  email: email,
                  amount: amountKobo,
                  ref: myUniqueID,
                  callback: function(response) {
                    console.log(response);
                    verifyTransactionOnBackend(null, response.reference);
                  },
                  onClose: function() {
                    console.log('Payment window closed');
                    $('#verifyTransaction').modal({
                      backdrop: 'static',
                      keyboard: false
                    }).modal('show');
                  }
                };
                if (splitCode) {
                  options.split_code = splitCode;
                } else if (subaccounts.length > 0) {
                  // Fallback to single subaccount if split creation fails
                  options.subaccount = subaccounts[0].id;
                }
                var handler = PaystackPop.setup(options);
                handler.openIframe();
              }

              $.ajax({
                url: 'model/create-ps-split.php',
                type: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({
                  sellers: sellerPayload,
                  amount_kobo: amountKobo,
                  bearer_type: 'account'
                }),
                success: function(res) {
                  if (res && res.status === 'success' && res.split_code) {
                    launchPaystack(res.split_code);
                  } else {
                    console.warn('Split creation failed, falling back to single subaccount', res);
                    launchPaystack(null);
                  }
                },
                error: function(err) {
                  console.error('Split creation error', err);
                  launchPaystack(null);
                }
              });
            } else if (activeGateway === 'interswitch') {
              // Interswitch payment flow
              // Note: Interswitch typically requires server-side initialization and redirect
              console.log('Interswitch payment - server-side initialization required');
              
              // Save cart and redirect to Interswitch initialization endpoint
              window.location.href = 'model/handle-isw-init.php?ref=' + myUniqueID + '&amount=' + transfer_amount;
            } else {
              alert('Unknown payment gateway: ' + activeGateway);
            }
          }
        });
      });

      // free checkout button click event
      $('#cart').on('click', '.free-cart-checkout', function() {
        // Check if payments are frozen
        <?php if ($payment_freeze_info): ?>
          // Show payment freeze modal
          $('#paymentFreezeMessage').text(<?php echo json_encode($payment_freeze_info['message'] ?? 'Payments are currently paused.'); ?>);
          $('#paymentFreezeModal').modal('show');
          return; // Stop checkout process
        <?php endif; ?>

        // Define event button
        var button = $(this);
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border text-white" style="width: 1.5rem; height: 1.5rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        function generateUniqueID() {
          const currentDate = new Date();
          const uniqueID = `nivas_<?php echo $user_id ?>_${currentDate.getTime()}`;
          return uniqueID;
        }

        const tx_ref = generateUniqueID();

        // Now make the Flutterwave API call
        $.ajax({
          url: 'model/handle-free-payment.php',
          type: 'GET',
          data: { tx_ref: tx_ref},
          success: function (response) {
            if (response.status === 'success') {
              location.reload();
            }

            // AJAX call successful, stop the spinner and update button text
            button.html(originalText);
            button.prop("disabled", false);
          },
          error: function () {
            // Handle error
            console.error('Error checking out!');
          }
        });
      });

      function verifyTransactionOnBackend(transaction_id, tx_ref) {
        // Use unified payment handler for all gateways
        var params = { tx_ref: tx_ref, callback: 1 };
        if (transaction_id) {
          params.transaction_id = transaction_id;
        } else {
          // For Paystack, use reference parameter
          params.reference = tx_ref;
        }
        
        $.ajax({
          url: 'model/handle-payment.php',
          type: 'GET',
          data: params,
          success: function (response) {
            if (response.status === 'success') {
              location.reload();
            }
          },
          error: function () {
            // Handle error
            console.error('Error verifying payment!');
          }
        });
      }

    });
  </script>
  <!-- End custom js for this page-->

  <!-- Manual Details Modal -->
  <div class="modal fade" id="manualModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <!-- dynamic content loads here via AJAX -->
      </div>
    </div>
  </div>

  <!-- Payment Freeze Modal -->
  <div class="modal fade" id="paymentFreezeModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" role="dialog" aria-labelledby="paymentFreezeLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header bg-warning text-dark">
          <h4 class="modal-title fw-bold" id="paymentFreezeLabel">
            <i class="mdi mdi-alert-circle me-2"></i>Payments Currently Paused
          </h4>
        </div>
        <div class="modal-body">
          <p id="paymentFreezeMessage" class="mb-0"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Okay, I Understand</button>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
