<?php
session_start();
include('../model/config.php');
include('../model/page_config.php');

if ($_SESSION['nivas_userRole'] == 'student') {
  header('Location: ../store.php');
  exit();
}

$transaction_query = mysqli_query($conn, "SELECT DISTINCT ref_id, buyer FROM manuals_bought_$school_id WHERE seller = $user_id ORDER BY `created_at` DESC");
$transaction_query2 = mysqli_query($conn, "SELECT DISTINCT ref_id, buyer FROM manuals_bought_$school_id WHERE seller = $user_id ORDER BY `created_at` DESC");

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Transactions - Nivasity</title>

  <!-- plugins:css -->
  <link rel="stylesheet" href="../assets/vendors/feather/feather.css">
  <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="../assets/vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../assets/vendors/typicons/typicons.css">
  <link rel="stylesheet" href="../assets/vendors/simple-line-icons/css/simple-line-icons.css">
  <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
  <!-- endinject -->
  <!-- Plugin css for this page -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="../assets/css/dashboard/style.css">
  <!-- endinject -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>

  <!-- main js -->
  <script src="../assets/js/main.js"></script>
  
  <!-- Google Sign-In API library -->
  <script src="https://accounts.google.com/gsi/client" async defer></script>

  <link rel="shortcut icon" href="../favicon.ico" />

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-30QJ6DSHBN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-30QJ6DSHBN');
  </script>

</head>

<body>
  <div class="container-scroller sidebar-fixed">
    <!-- partial:partials/_navbar.html -->
    <?php include('../partials/_navbar.php') ?>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_sidebar_admin.php -->
      <?php include('../partials/_sidebar_admin.php') ?>
      <!-- partial -->
      <div class="main-panel">

        <div class="content-wrapper">
          <div class="row flex-grow">
            <div class="col-12 grid-margin stretch-card">
              <div class="card card-rounded shadow-sm">
                <div class="card-body px-2">
                  <div class="table-responsive  mt-1">
                    <table id="transaction_table"
                      class="table table-hover table-striped select-table datatable-opt">
                      <thead>
                        <tr>
                          <th class="d-sm-none-2">Transaction Id</th>
                          <th>Student Details</th>
                          <th>Quantity</th>
                          <th>Price</th>
                          <th class="d-sm-none-2">Date & Time</th>
                          <th class="d-sm-none-2">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                      <?php
                      while ($transaction = mysqli_fetch_array($transaction_query)) {
                        $transaction_id = $transaction['ref_id'];
                        $buyer_id = $transaction['buyer'];

                        $buyer = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $buyer_id"));

                        $transactions_bought_cnt = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(ref_id) FROM manuals_bought_$school_id WHERE ref_id = '$transaction_id'"))[0];
                        $transactions_bought_price = mysqli_fetch_array(mysqli_query($conn, "SELECT SUM(price) FROM manuals_bought_$school_id WHERE ref_id = '$transaction_id'"))[0];
                        $created_at = mysqli_fetch_array(mysqli_query($conn, "SELECT created_at FROM manuals_bought_$school_id WHERE ref_id = '$transaction_id' LIMIT 1"))[0];
                        
                        // Retrieve and format the due date
                        $created_date = date('j M, Y', strtotime($created_at));
                        $created_time = date('h:i a', strtotime($created_at));
                        // Retrieve the status
                        $status = mysqli_fetch_array(mysqli_query($conn, "SELECT status FROM manuals_bought_$school_id WHERE ref_id = '$transaction_id' LIMIT 1"))[0];
                        $status_bg = 'danger';

                        if ($status == 'successful') {
                          $status_bg = 'success';
                        } else if ($status == 'pending') {
                          $status_bg = 'warning';
                        }
                        ?>
                          <tr>
                            <td class="d-sm-none-2">
                              <h6 class="pl-3">#<?php echo $transaction['ref_id'] ?></h6>
                            </td>
                            <td>
                              <h6 class="text-uppercase"><?php echo $buyer['first_name'] . ' ' . $buyer['last_name'] ?></h6>
                              <p>Matric no: <span class="fw-bold"><?php echo $buyer['matric_no'] ?></span></p>
                            </td>
                            <td>
                              <h6><?php echo $transactions_bought_cnt ?></h6>
                            </td>
                            <td>
                              <h6 class="text-success fw-bold">&#8358; <?php echo number_format($transactions_bought_price) ?></h6>
                            </td>
                            <td class="d-sm-none-2">
                              <h6><?php echo $created_date ?></h6>
                              <p class="fw-bold"><?php echo $created_time ?></p>
                            </td>
                            <td class="d-sm-none-2">
                              <div class="badge bg-<?php echo $status_bg ?>"><?php echo $status ?></div>
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
        <!-- content-wrapper ends -->
         
        <!-- partial:partials/_footer.php -->
        <?php include('../partials/_footer.php') ?>
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
  <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="../assets/vendors/chart.js/Chart.min.js"></script>
  <script src="../assets/vendors/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.1/mdb.min.js"></script>
  <script src="../assets/vendors/progressbar.js/progressbar.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="../assets/js/js/off-canvas.js"></script>
  <script src="../assets/js/js/hoverable-collapse.js"></script>
  <script src="../assets/js/js/template.js"></script>
  <script src="../assets/js/js/settings.js"></script>
  <script src="../assets/js/js/data-table.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="../assets/js/script.js"></script>
  <script src="../assets/js/js/dashboard.js"></script>

  <script>
    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');
    });
  </script>
  <!-- End custom js for this page-->
</body>

</html>