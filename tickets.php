<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$event_query = mysqli_query($conn, "SELECT * FROM event_tickets WHERE buyer = $user_id ORDER BY created_at DESC");

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
                        <div class="card-header">
                          <h4 class="fw-bold my-3">Event Tickets</h4> 
                        </div>
                        <div class="card-body">
                          <!-- order Ticket Table -->
                          <div class="table-responsive  mt-1">
                            <table id="order_table" class="table table-striped table-hover select-table datatable-opt">
                              <thead>
                                <tr>
                                  <th>Trans. ID</th>
                                  <th>Details</th>
                                  <th>Event Date</th>
                                  <th>Price</th>
                                  <th>Date Bought</th>
                                  <th>Status</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php
                              while ($event = mysqli_fetch_array($event_query)) {
                                $event_id = $event['event_id'];

                                $events = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM events WHERE id = $event_id"));

                                // Retrieve and format the event date
                                $event_date = date('j M, Y', strtotime($events['event_date']));
                                
                                $event_time = date('g:i A', strtotime($events['event_time']));

                                // Retrieve and format the due date
                                $created_date = date('j M, Y', strtotime($event['created_at']));
                                $created_time = date('h:i a', strtotime($event['created_at']));
                                // Retrieve the status
                                $status = $event['status'];
                                ?>
                              <tr>
                                <td>
                                  #<?php echo $event['ref_id'] ?>
                                </td>
                                <td>
                                  <div class="d-flex justify-content-start">
                                    <div>
                                      <img src="assets/images/events/<?php echo $events['event_banner'] ?>" alt="<?php echo $events['title'] ?>" class="img-fluid rounded-2" style="min-width: 100px">
                                    </div>
                                    <div>
                                      <h6><?php echo $events['title'] ?></h6>
                                      <p class="d-sm-none-2">ID: <span class="fw-bold"><?php echo $events['code'] ?></span></p>
                                    </div>
                                  </div>
                                </td>
                                <td>
                                  <h6><?php echo $event_date ?></h6>
                                  <p class="fw-bold"><?php echo $event_time ?></p>
                                </td>
                                <td>
                                  <h6 class="text-success fw-bold">&#8358; <?php echo number_format($events['price']) ?></h6>
                                </td>
                                <td>
                                  <h6><?php echo $created_date ?></h6>
                                  <p class="fw-bold"><?php echo $created_time ?></p>
                                </td>
                                <td>
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