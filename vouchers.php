<?php
session_start();
include('model/config.php');
include('model/page_config.php');

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Dashboard - Nivasity</title>

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
                        aria-selected="false">Shopping Cart (<span id="cart-count">0</span>)</a>
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

                          <div class="col-12 col-md-4 grid-margin stretch-card sortable-card">
                            <div class="card card-rounded shadow-sm">
                              <div class="card-body">
                                <h4 class="card-title">Introduction to Mathematics <span class="text-secondary">- MAT
                                    100</span></h4>
                                <div class="media">
                                  <i class="mdi mdi-book icon-lg text-secondary d-flex align-self-start me-3"></i>
                                  <div class="media-body">
                                    <h3 class="fw-bold price">₦ 8,000</h3>
                                    <p class="card-text">
                                      Due date:<span class="fw-bold text-danger due_date"> Fri, Dec 15</span><br>
                                      <span class="text-secondary">Samuel Akinyemi (HOC)</span>
                                    </p>
                                  </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                  <a href="javascript:;">
                                    <i class="mdi mdi-share-variant icon-md text-muted"></i>
                                  </a>
                                  <button class="btn btn-outline-primary btn-lg m-0 cart-button" data-product-id="1">
                                    Add to Cart
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>

                          <div class="col-12 col-md-4 grid-margin stretch-card sortable-card">
                            <div class="card card-rounded shadow-sm">
                              <div class="card-body">
                                <h4 class="card-title">Introduction to Mathematics <span class="text-secondary">- MAT
                                    100</span></h4>
                                <div class="media">
                                  <i class="mdi mdi-book icon-lg text-secondary d-flex align-self-start me-3"></i>
                                  <div class="media-body">
                                    <h3 class="fw-bold price">₦ 8,000</h3>
                                    <p class="card-text">
                                      Due date:<span class="fw-bold text-danger due_date"> Fri, Dec 15</span><br>
                                      <span class="text-secondary">Samuel Akinyemi (HOC)</span>
                                    </p>
                                  </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                  <a href="javascript:;">
                                    <i class="mdi mdi-share-variant icon-md text-muted"></i>
                                  </a>
                                  <button class="btn btn-outline-primary btn-lg m-0 cart-button" data-product-id="2">
                                    Add to Cart
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>

                          <div class="col-12 col-md-4 grid-margin stretch-card sortable-card">
                            <div class="card card-rounded shadow-sm">
                              <div class="card-body">
                                <h4 class="card-title">Introduction to Mathematics <span class="text-secondary">- MAT
                                    100</span></h4>
                                <div class="media">
                                  <i class="mdi mdi-book icon-lg text-secondary d-flex align-self-start me-3"></i>
                                  <div class="media-body">
                                    <h3 class="fw-bold price">₦ 8,000</h3>
                                    <p class="card-text">
                                      Due date:<span class="fw-bold text-danger due_date"> Fri, Dec 15</span><br>
                                      <span class="text-secondary">Samuel Akinyemi (HOC)</span>
                                    </p>
                                  </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                  <a href="javascript:;">
                                    <i class="mdi mdi-share-variant icon-md text-muted"></i>
                                  </a>
                                  <button class="btn btn-outline-primary btn-lg m-0 cart-button" data-product-id="3">
                                    Add to Cart
                                  </button>
                                </div>
                              </div>
                            </div>
                          </div>

                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade hide" id="cart" role="tabpanel" aria-labelledby="cart">
                    <div class="row flex-grow">
                      <div class="col-12 grid-margin stretch-card">
                        <div class="card card-rounded shadow-sm">
                          <div class="card-body">
                            <div class="d-sm-flex justify-content-end">
                              <div>
                                <button class="btn btn-primary btn-lg text-white mb-0 me-0" type="button"
                                  data-bs-toggle="modal" data-bs-target="#addManual"><i class="mdi mdi-book"></i>Add new
                                  manual</button>
                              </div>
                            </div>
                            <div class="table-responsive  mt-1">
                              <table class="table table-hover table-striped select-table datatable-opt">
                                <thead>
                                  <tr>
                                    <th>Name</th>
                                    <th class="d-sm-none-2">Unit Price</th>
                                    <th>Revenue</th>
                                    <th class="d-sm-none-2">Availability</th>
                                    <th class="d-sm-none-2">Due Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <tr>
                                    <td>
                                      <div class="d-flex ">
                                        <div>
                                          <h6><span class="d-sm-none-2">Electro-magnetic Field -</span> EEP 201</h6>
                                          <p class="d-sm-none-2">ID: <span class="fw-bold">X2I-WER</span></p>
                                        </div>
                                      </div>
                                    </td>
                                    <td class="d-sm-none-2">
                                      <h6>&#8358; 2,000</h6>
                                    </td>
                                    <td>
                                      <h6 class="text-secondary">&#8358; 35,000</h6>
                                      <p>Qty Sold: <span class="fw-bold">23</span></p>
                                    </td>
                                    <td class="d-sm-none-2">
                                      <div>
                                        <div
                                          class="d-flex justify-content-between align-items-center mb-1 max-width-progress-wrap">
                                          <p class="text-success">52%</p>
                                          <p>23/40</p>
                                        </div>
                                        <div class="progress progress-md">
                                          <div class="progress-bar bg-success" role="progressbar" style="width: 52%"
                                            aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                      </div>
                                    </td>
                                    <td class="d-sm-none-2">
                                      <h6>23 Nov, 2023</h6>
                                    </td>
                                    <td>
                                      <div class="badge badge-opacity-success">Open</div>
                                    </td>
                                    <td>
                                      <button data-mdb-ripple-duration="0"
                                        class="btn btn-md btn-primary mb-0 btn-block">View</button>
                                    </td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
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
        <!-- partial:partials/_footer.html -->
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
      $('.cart-button').on('click', function () {
        var button = $(this);
        var product_id = button.data('product-id');

        // Toggle button appearance and text
        if (button.hasClass('btn-outline-primary')) {
          button.toggleClass('btn-outline-primary btn-primary').text('Added');
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
            // reloadCartTable();
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
          type: 'GET',
          url: 'model/cart.php',
          success: function (html) {
            $('#cart-table-container').html(html);
          },
          error: function () {
            // Handle error
            console.error('Error in reloading cart table');
          }
        });
      }

    });
  </script>
  <!-- End custom js for this page-->
</body>

</html>