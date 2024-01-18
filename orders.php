<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$manual_query = mysqli_query($conn, "SELECT * FROM manuals_bought_$school_id WHERE buyer = $user_id ORDER BY created_at DESC");

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Orders - Nivasity</title>

  <?php include('partials/_head.php') ?>
  </head>
</head>

<body>
  <div class="container-scroller">
    <!-- partial:partials/_navbar.php -->
    <?php include('partials/_navbar.php') ?>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_sidebar_user.php -->
      <?php include('partials/_sidebar_user.php') ?>
      <!-- partial -->
      <div class="main-panel">
  
        <div class="content-wrapper py-0">
          <div class="row">
            <div class="col-sm-12 px-2">
              <div class="home-tab">
                <div class="tab-content tab-content-basic py-0">
                  <div class="tab-pane fade show active" id="order" role="tabpanel" aria-labelledby="order">
                    <div class="row flex-grow">
                      <div class="col-12 card card-rounded shadow-sm px-2">
                        <div class="card-body px-2">
                          <!-- order Ticket Table -->
                          <div class="table-responsive  mt-1">
                            <table id="order_table" class="table table-striped table-hover select-table datatable-opt">
                              <thead>
                                <tr>
                                  <th>Name</th>
                                  <th>Price</th>
                                  <th>Date Bought</th>
                                  <th>Status</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php
                              while ($manual = mysqli_fetch_array($manual_query)) {
                                $manual_id = $manual['manual_id'];

                                $manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM manuals_$school_id WHERE id = $manual_id"));

                                // Retrieve and format the due date
                                $created_date = date('j M, Y', strtotime($manual['created_at']));
                                $created_time = date('h:i a', strtotime($manual['created_at']));
                                // Retrieve the status
                                $status = $manual['status'];
                                ?>
                              <tr>
                                <td>
                                  <div class="d-flex ">
                                    <div>
                                      <h6><span class="d-sm-none-2"><?php echo $manuals['title'] ?> -</span> <?php echo $manuals['course_code'] ?></h6>
                                      <p class="d-sm-none-2">ID: <span class="fw-bold"><?php echo $manuals['code'] ?></span></p>
                                    </div>
                                  </div>
                                </td>
                                <td>
                                  <h6 class="text-success fw-bold">&#8358; <?php echo $manuals['price'] ?></h6>
                                </td>
                                <td>
                                  <h6><?php echo $created_date ?></h6>
                                  <p class="fw-bold"><?php echo $created_time ?></p>
                                </td>
                                <td class="text-center">
                                  <div class="badge <?php echo ($status == 'successful') ? 'bg-success' : 'bg-danger'; ?>"><?php echo $status; ?></div>
                                </td>
                              </tr>
                              <?php } ?>
                              </tbody>
                            </table>
                          </div>

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
        <!-- partial:partials/_footer.html -->
        <?php include('partials/_footer.php') ?>
        <!-- partial -->
      </div>
      <!-- Bootstrap alert container -->
      <div id="alertBanner"
        class="alert alert-info text-center fw-bold alert-dismissible end-2 top-2 fade show position-fixed w-auto p-2 px-4"
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
    });
  </script>
</body>

</html>