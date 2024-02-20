<?php
session_start();
include('../model/config.php');
include('../model/page_config.php');

if ($_SESSION['nivas_userRole'] == 'student') {
  header('Location: ../store.php');
  exit();
}

$t_manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(id) FROM manuals_$school_id WHERE user_id = $user_id"))[0];
$t_manuals_sold = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(manual_id) FROM manuals_bought_$school_id WHERE seller = $user_id"))[0];
$t_manuals_price = mysqli_fetch_array(mysqli_query($conn, "SELECT SUM(price) FROM manuals_bought_$school_id WHERE seller = $user_id"))[0];
$t_manuals_price = mysqli_fetch_array(mysqli_query($conn, "SELECT SUM(price) FROM manuals_bought_$school_id WHERE seller = $user_id"))[0];
$t_students = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(id) FROM users WHERE school = $school_id AND dept = $user_dept"))[0];

$open_manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(id) FROM manuals_$school_id WHERE user_id = $user_id AND status = 'open'"))[0];
$closed_manuals = $t_manuals - $open_manuals;

$manual_query = mysqli_query($conn, "SELECT * FROM manuals_$school_id WHERE user_id = $user_id ORDER BY `id` DESC");
$manual_query2 = mysqli_query($conn, "SELECT manual_id, SUM(price) AS total_sales
    FROM manuals_bought_$school_id
    WHERE seller  = $user_id
    GROUP BY manual_id
    ORDER BY total_sales DESC
    LIMIT 3");

$transaction_query = mysqli_query($conn, "SELECT DISTINCT ref_id, buyer FROM manuals_bought_$school_id WHERE seller = $user_id ORDER BY `created_at` DESC");
$transaction_query2 = mysqli_query($conn, "SELECT DISTINCT ref_id, buyer FROM manuals_bought_$school_id WHERE seller = $user_id ORDER BY `created_at` DESC");

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Dashboard - Nivasity</title>

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
          <div class="row">
            <div class="col-sm-12 px-2">
              <div class="home-tab">
                <div class="d-sm-flex align-items-center justify-content-between border-bottom">
                  <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                      <a class="nav-link px-3 active ps-0 fw-bold" id="home-tab" data-bs-toggle="tab" href="#overview"
                        role="tab" aria-controls="overview" aria-selected="true">Overview</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold" id="profile-tab" data-bs-toggle="tab" href="#manuals" role="tab"
                        aria-selected="false">Manuals</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold" id="contact-tab" data-bs-toggle="tab" href="#transactions" role="tab"
                        aria-selected="false">Transactions</a>
                    </li>
                  </ul>
                  <div>
                  </div>
                </div>
                <div class="tab-content tab-content-basic">
                  <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview">
                    <?php if ($user_status == 'verified'): ?>
                    <div class="row flex-grow">
                      <div class="col-12">
                        <div
                          class="statistics-details d-flex justify-content-between align-items-center mt-0 mt-md-2 mb-4">
                          <div>
                            <p class="statistics-title fw-bold">Revenue Earned</p>
                            <h3 class="rate-percentage">&#8358; <?php echo number_format($t_manuals_price) ?></h3>
                          </div>
                          <div>
                            <p class="statistics-title fw-bold">Total Manuals</p>
                            <h3 class="rate-percentage"><?php echo $t_manuals ?></h3>
                          </div>
                          <div class="d-none d-md-block">
                            <p class="statistics-title fw-bold">Total Sales</p>
                            <h3 class="rate-percentage"><?php echo $t_manuals_sold ?></h3>
                          </div>
                          <div class="d-none d-md-block">
                            <p class="statistics-title fw-bold">Total Students</p>
                            <h3 class="rate-percentage"><?php echo $t_students ?></h3>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-lg-12 d-flex flex-column">
                        <div class="row flex-grow">
                          <div class="col-12 grid-margin stretch-card">
                            <div class="card card-rounded shadow-sm">
                              <div class="card-body px-2">
                                <div class="d-sm-flex justify-content-between align-items-start">
                                  <div>
                                    <h4 class="card-title card-title-dash">Weekly Income Overview</h4>
                                    <h5 class="card-subtitle card-subtitle-dash">Weekly Income Snapshot: See your week's
                                      earnings at a glance</h5>
                                  </div>
                                  <div id="performance-line-legend"></div>
                                </div>
                                <div class="chartjs-wrapper mt-5">
                                  <canvas id="performaneLine"></canvas>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="row">
                      <div class="col-lg-8 d-flex flex-column">
                        <!-- <div class="row flex-grow">
                          <div class="col-12 grid-margin stretch-card">
                            <div class="card card-rounded shadow-sm table-darkBGImg">
                              <div class="card-body px-2">
                                <div class="col-sm-8">
                                  <h3 class="text-white upgrade-info mb-0">
                                    Enhance your <span class="fw-bold">Campaign</span> for better outreach
                                  </h3>
                                  <a href="#" class="btn btn-info upgrade-btn">Upgrade
                                    Account!</a>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div> -->
                        <div class="row flex-grow">
                          <div class="col-12 grid-margin stretch-card">
                            <div class="card card-rounded shadow-sm">
                              <div class="card-body px-2">
                                <div class="d-sm-flex justify-content-between align-items-start">
                                  <div>
                                    <h4 class="card-title card-title-dash">Best Selling Manuals</h4>
                                    <p class="card-subtitle card-subtitle-dash">You have <span
                                        class="text-success fw-bold"><?php echo $open_manuals ?> open</span> manuals and <span
                                        class="text-warning fw-bold"><?php echo $closed_manuals ?> closed</span> manuals.</p>
                                  </div>
                                  <!-- <div>
                                    <button class="btn btn-primary btn-lg text-white mb-0 me-0" type="button"><i
                                        class="mdi mdi-account-plus"></i>Add new member</button>
                                  </div> -->
                                </div>
                                <div class="table-responsive  mt-1">
                                  <table class="table select-table">
                                    <thead>
                                      <tr>
                                        <th>Name</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <?php
                                      if (mysqli_num_rows($manual_query2)) {
                                      while ($manual = mysqli_fetch_array($manual_query2)) {
                                        $manual_id = $manual['manual_id'];

                                        $manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM manuals_$school_id WHERE id = $manual_id"));
                                        $manuals_bought_cnt = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(manual_id) FROM manuals_bought_$school_id WHERE manual_id = $manual_id"))[0];

                                        // Retrieve the status
                                        $status = $manuals['status'];
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
                                          <h6 class="text-secondary">&#8358; <?php echo number_format($manual['total_sales']) ?></h6>
                                          <p>Qty Sold: <span class="fw-bold"><?php echo $manuals_bought_cnt ?></span></p>
                                        </td>
                                        <td class="text-center">
                                            <div class="badge <?php echo ($status == 'open') ? 'bg-success' : 'bg-danger'; ?>"> </div>
                                        </td>
                                      </tr>
                                      <?php } } else { ?>
                                        <tr>
                                          <td colspan="3">
                                          NO TRANSACTIONS YET!
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
                      <div class="col-lg-4 d-flex flex-column">
                        <div class="row flex-grow">
                          <div class="col-lg-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                              <div>
                                <h4 class="card-title card-title-dash">Latest Transactions</h4>
                              </div>
                            </div>
                            <div class="mt-3">
                            <?php
                            if (mysqli_num_rows($transaction_query2)) {
                            while ($transaction = mysqli_fetch_array($transaction_query2)) {
                              $transaction_id = $transaction['ref_id'];
                              $buyer_id = $transaction['buyer'];

                              $buyer = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $buyer_id"));

                              $transactions_bought_cnt = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(ref_id) FROM manuals_bought_$school_id WHERE ref_id = '$transaction_id'"))[0];
                              $transactions_bought_price = mysqli_fetch_array(mysqli_query($conn, "SELECT SUM(price) FROM manuals_bought_$school_id WHERE ref_id = '$transaction_id'"))[0];
                              $created_at = mysqli_fetch_array(mysqli_query($conn, "SELECT created_at FROM manuals_bought_$school_id WHERE ref_id = '$transaction_id' LIMIT 1"))[0];
                              
                              // Retrieve and format the due date
                              $created_date = date('M j', strtotime($created_at));
                              $created_time = date('h:i a', strtotime($created_at));
                              ?>
                              <div class="wrapper d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div class="d-flex">
                                  
                                  <div class="wrapper ms-3">
                                    <p class="mb-1 fw-bold"><?php echo $transactions_bought_cnt ?> manuals bought by <span class="text-capitalize"><?php echo $buyer['first_name']?></span></p>
                                    <p class="text-secondary mb-0 fw-bold">&#8358; <?php echo number_format($transactions_bought_price) ?></p>
                                  </div>
                                </div>
                                <div class="text-muted fw-bold">
                                  <?php echo $created_date?><br><?php echo $created_time?>
                                </div>
                              </div>
                              <?php } } else { ?>
                                <div class="text-center py-2 border-bottom">
                                  NO TRANSACTIONS YET!
                                </div>                              
                              <?php } ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>        
                    <?php else: ?>
                    <div class="row">
                      <div class="col-12 d-flex flex-column">
                        <div class="card card-rounded shadow-sm bg-secondary">
                          <div class="card-body px-2">
                            <div class="d-sm-flex justify-content-center align-items-center">
                              <div>
                                
                                <h4 class="card-title text-center text-white fw-bold"><i class="mdi mdi-account-clock h1"></i><br><br>VERIFICATION IN PROGRESS...</h4>
                              </div>
                            </div>
                            <div>
                              <h4 class="lh-base text-center text-white">Our Support team is currently verifying your role at your school. This should be sorted within <span class="text-primary">48 working hours after registration</span>.<br><br>However, please go on to <a href="user.php" class="text-primary fw-bold">profile settings</a> to add your Settlement Account.</h4>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>  
                    <?php endif; ?>      
                  </div>
                  <div class="tab-pane fade hide" id="manuals" role="tabpanel" aria-labelledby="manuals">
                    <div class="row flex-grow">
                      <div class="col-12 grid-margin stretch-card">
                        <div class="card card-rounded shadow-sm">
                          <div class="card-body px-2">
                            <div class="d-sm-flex justify-content-end">
                              <div>
                                <button class="btn btn-primary btn-lg text-white mb-0 me-0" type="button"
                                  data-bs-toggle="modal" data-bs-target="#<?php echo $manual_modal = ($user_status == 'verified') ? 'addManual' : 'verificationManual' ?>"><i class="mdi mdi-book"></i>Add new
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
                                    <th>Actions</th>
                                  </tr>
                                </thead>
                                <tbody id="manual_tbody">
                                <?php
                                while ($manual = mysqli_fetch_array($manual_query)) {
                                  $manual_id = $manual['id'];

                                  $manuals_bought_cnt = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(manual_id) FROM manuals_bought_$school_id WHERE manual_id = $manual_id"))[0];
                                  $manuals_bought_price = mysqli_fetch_array(mysqli_query($conn, "SELECT SUM(price) FROM manuals_bought_$school_id WHERE manual_id = $manual_id"))[0];

                                  // Calculate the percentage and total sold/quantity text
                                  $percentage_sold = ($manuals_bought_cnt / $manual['quantity']) * 100;
                                  $sold_quantity_text = $manuals_bought_cnt . '/' . $manual['quantity'];
                                  
                                  // Retrieve and format the due date
                                  $due_date = date('j M, Y', strtotime($manual['due_date']));
                                  $due_date2 = date('Y-m-d', strtotime($manual['due_date']));
                                  // Retrieve the status
                                  $status = $manual['status'];
                                  $status_2 = $status;
                                  
                                  if ($date > $due_date2) {
                                    $status = 'overdue';
                                  }
                                  ?>
                                    <tr>
                                      <td>
                                        <div class="d-flex ">
                                          <div>
                                            <h6><span class="d-sm-none-2"><?php echo $manual['title'] ?> -</span> <?php echo $manual['course_code'] ?></h6>
                                            <p class="d-sm-none-2">ID: <span class="fw-bold"><?php echo $manual['code'] ?></span></p>
                                          </div>
                                        </div>
                                      </td>
                                      <td class="d-sm-none-2">
                                        <h6>&#8358; <?php echo number_format($manual['price']) ?></h6>
                                      </td>
                                      <td>
                                        <h6 class="text-secondary">&#8358; <?php echo number_format($manuals_bought_price) ?></h6>
                                        <p>Qty Sold: <span class="fw-bold"><?php echo $manuals_bought_cnt ?></span></p>
                                      </td>
                                      <td class="d-sm-none-2">
                                          <div>
                                            <div class="d-flex justify-content-between align-items-center mb-1 max-width-progress-wrap">
                                              <p class="text-success"><?php echo round($percentage_sold) . '%' ?></p>
                                              <p><?php echo $sold_quantity_text ?></p>
                                            </div>
                                            <div class="progress progress-md">
                                              <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage_sold ?>%"
                                                  aria-valuenow="<?php echo $percentage_sold ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                          </div>
                                        </td>
                                        <td class="d-sm-none-2">
                                          <h6><?php echo $due_date ?></h6>
                                        </td>
                                        <td class="text-center">
                                          <div class="badge <?php echo ($status == 'open') ? 'bg-success' : 'bg-danger'; ?>"> </div>
                                        </td>
                                        <td class="pe-1">
                                          <button class="btn btn-md btn-primary mb-0 btn-block view-edit-manual"
                                            data-manual_id="<?php echo $manual['id']; ?>" data-title="<?php echo $manual['title']; ?>"
                                            data-course_code="<?php echo $manual['course_code']; ?>" data-price="<?php echo $manual['price']; ?>"
                                            data-quantity="<?php echo $manual['quantity']; ?>"
                                            data-due_date="<?php echo date('Y-m-d', strtotime($manual['due_date'])); ?>" 
                                            data-bs-toggle="modal" data-bs-target="#<?php echo $manual_modal = ($user_status == 'verified') ? 'addManual': 'verificationManual'?>">Edit</button>
                                            <button class="btn btn-md btn-dark mb-0 btn-block export-manual" data-bs-toggle="modal" data-bs-target="#exportManual"
                                              data-manual_id="<?php echo $manual['id']; ?>" data-code="<?php echo $manual['course_code']; ?>"><i class="mdi mdi-file-export m-0 text-white"></i></button>
                                          <?php if($status != 'overdue'): ?>
                                            <button class="btn btn-md btn-secondary mb-0 btn-block close-manual"
                                              data-manual_id="<?php echo $manual['id']; ?>" data-title="<?php echo $manual['title']; ?>" data-action="<?php echo ($status_2 != 'open') ? 1 : 0; ?>"
                                                  data-bs-toggle="modal" data-bs-target="#closeManual"><i class="mdi mdi-eye<?php echo ($status_2 != 'open') ? '-off' : ''; ?> m-0 text-white"></i></button>
                                          <?php endif; ?>
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
                  <div class="tab-pane fade hide" id="transactions" role="tabpanel" aria-labelledby="transactions">
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
                </div>

                <!-- Close Manual Modal -->
                <div class="modal fade" id="closeManual" tabindex="-1" role="dialog" aria-labelledby="closeManualLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h4 class="modal-title fw-bold" id="closeManualLabel">Change Manual Visibility</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="close-manual-form">
                        <input type="hidden" name="close_manual" value="1">
                        <input type="hidden" name="manual_id" value="0">
                        <div class="modal-body">
                          <div>
                            <h4 class="lh-base">Are you sure you want to change <span class="manual_title text-primary">Manual Title</span> manual visibility before the due date?</h4>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Cancel</button>
                          <button id="close_manual_submit" type="submit" data-mdb-ripple-duration="0"
                            class="btn btn-lg btn-danger">Confirm</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- Export Manual Modal -->
                <div class="modal fade" id="exportManual" tabindex="-1" role="dialog" aria-labelledby="exportManualLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h4 class="modal-title fw-bold" id="exportManualLabel">Got a RRR Number?</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="export-manual-form">
                        <input type="hidden" name="code" value="0">
                        <input type="hidden" name="manual_id" value="0">
                        <div class="modal-body">
                          <div class="form-outline mb-4">
                            <input type="text" name="rrr" class="form-control form-control-lg w-100">
                            <label class="form-label" for="rrr">RRR Number (Optional)</label>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Cancel</button>
                          <button id="export_manual_submit" type="submit" class="btn btn-lg btn-dark"><i class="mdi mdi-file-export text-white"></i> Proceed Export</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- User Verification Modal -->
                <div class="modal fade" id="verificationManual" tabindex="-1" role="dialog" aria-labelledby="verificationManualLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h4 class="modal-title fw-bold" id="verificationManualLabel">VERIFICATION IN PROGRESS...</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <div class="modal-body">
                        <div>
                          <h4 class="lh-base">Our Support team is currently verifying your role at your school. This should be sorted within <span class="text-primary">48 working hours after registration</span>.<br><br>However, to speed up the proccess, you can use the support tickets and upload means of verification regarding your role at your school.</h4>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>

                <?php if ($user_status == 'verified'): ?>
                <!-- Add New Manual Modal -->
                <div class="modal fade" id="addManual" tabindex="-1" role="dialog" aria-labelledby="addManualLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="addManualLabel">New Manual</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="manual-form">
                        <input type="hidden" name="manual_id" value="0">
                        <div class="modal-body">
                          <div class="form-outline mb-4">
                            <input type="text" name="title" class="form-control form-control-lg w-100" required="">
                            <label class="form-label" for="title">Manual Title</label>
                          </div>
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="text" name="course_code" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="course_code">Course Code</label>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="number" name="price" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="price">Unit Price</label>
                              </div>
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="number" name="quantity" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="quantity">Quantity</label>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="date" name="due_date" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="due_date">Due Date</label>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Cancel</button>
                          <button id="manual_submit" type="submit" data-mdb-ripple-duration="0"
                            class="btn btn-lg btn-primary">Submit</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>                
                <?php endif; ?>
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

      $('#addManual').on('hidden.bs.modal', function () {
        // Reset the form by setting its values to empty
        $('#manual-form input[name="manual_id"]').val(0);
        $('#manual-form')[0].reset();
      });
      
      $('#exportManual').on('hidden.bs.modal', function () {
        // Reset the form by setting its values to empty
        $('#export-manual-form')[0].reset();
      });

      // Handle click event of View/Edit button
      $('.view-edit-manual').on('click', function () {
        // Get the manual details from the data- attributes
        var manualId = $(this).data('manual_id');
        var title = $(this).data('title');
        var courseCode = $(this).data('course_code');
        var price = $(this).data('price');
        var quantity = $(this).data('quantity');
        var dueDateISO = $(this).data('due_date');

        // Set the values in the edit manual modal
        $('#manual-form input[name="manual_id"]').val(manualId);
        $('#manual-form input[name="title"]').val(title);
        $('#manual-form input[name="course_code"]').val(courseCode);
        $('#manual-form input[name="price"]').val(price);
        $('#manual-form input[name="quantity"]').val(quantity);
        $('#manual-form input[name="due_date"]').val(dueDateISO);
      });

      // Use AJAX to submit the manual form
      $('#manual-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        // Define manual button
        var button = $('#manual_submit');
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border text-white" style="width: 1.5rem; height: 1.5rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        // Simulate an AJAX call using setTimeout
        setTimeout(function () {
          $.ajax({
            type: 'POST',
            url: 'model/manuals.php',
            data: $('#manual-form').serialize(),
            success: function (data) {
              $('#alertBanner').html(data.message);

              if (data.status == 'success') {
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').removeClass('alert-danger');
                $('#alertBanner').addClass('alert-success');

                location.reload();
              } else {
                $('#alertBanner').removeClass('alert-success');
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').addClass('alert-danger');
              }

              // Show alert for verified email address
              showAlert();

              // AJAX call successful, stop the spinner and update button text
              button.html(originalText);
              button.prop("disabled", false);
            }
          });
        }, 2000); // Simulated AJAX delay of 2 seconds
      });

      // Handle click event of View/Edit button
      $('.close-manual').on('click', function () {
        // Get the manual details from the data- attributes
        var manualId = $(this).data('manual_id');
        var title = $(this).data('title');
        var action = $(this).data('action');

        // Set the values in the edit manual modal
        $('#close-manual-form input[name="manual_id"]').val(manualId);
        $('#close-manual-form input[name="close_manual"]').val(action);
        $('.manual_title').html(title);
      });
      
      $('#close-manual-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        // Define manual button
        var button = $('#close_manual_submit');
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border text-white" style="width: 1.5rem; height: 1.5rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        // Simulate an AJAX call using setTimeout
        setTimeout(function () {
          $.ajax({
            type: 'POST',
            url: 'model/manuals.php',
            data: $('#close-manual-form').serialize(),
            success: function (data) {
              $('#alertBanner').html(data.message);

              if (data.status == 'success') {
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').removeClass('alert-danger');
                $('#alertBanner').addClass('alert-success');

                location.reload();
              } else {
                $('#alertBanner').removeClass('alert-success');
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').addClass('alert-danger');
              }

              // Show alert for verified email address
              showAlert();

              // AJAX call successful, stop the spinner and update button text
              button.html(originalText);
              button.prop("disabled", false);
            }
          });
        }, 2000); // Simulated AJAX delay of 2 seconds
      });

      // Event listener for the export button click
      $(".export-manual").click(function () {
        // Get the manual details from the data- attributes
        var manualId = $(this).data('manual_id');
        var code = $(this).data('code');

        // Set the values in the edit manual modal
        $('#export-manual-form input[name="manual_id"]').val(manualId);
        $('#export-manual-form input[name="code"]').val(code);
      });

      $('#export-manual-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        var manualId = $('#export-manual-form input[name="manual_id"]').val();
        var code = $('#export-manual-form input[name="code"]').val();
        var rrr = $('#export-manual-form input[name="rrr"]').val();

        // Define export button
        var button = $('#export_manual_submit');
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        // Simulate an AJAX call using setTimeout
        setTimeout(function () {
          // Call the Ajax function to get data
          $.ajax({
            url: "../model/export.php", // Replace with your server-side script to fetch data
            type: "POST",
            data: {manual_id: manualId},
            success: function (data) {
              heading = "<center><h2 style='text-transform: uppercase'>PAYMENTS FOR "+code+" MANUAL</h2></center>"
              
              
              // Sort usersData based on matric_no before rendering
              data.sort(function (a, b) {
                return a.matric_no.localeCompare(b.matric_no);
              });
              
              if (rrr === '') {
                // Format data into a table
                var table = "<table><tr><th>S/N</th><th>NAMES</th><th>MATRIC NO</th></tr>";

                $.each(data, function (index, item) {
                  table += "<tr><td>" + (index + 1) + "</td><td>" + item.name + "</td><td>" + item.matric_no + "</td></tr>";
                });
              } else {
                // Format data into a table
                var table = "<table><tr><th>S/N</th><th>NAMES</th><th>MATRIC NO</th><th>RRR</th></tr>";
                
                $.each(data, function (index, item) {
                  table += "<tr><td>" + (index + 1) + "</td><td>" + item.name + "</td><td>" + item.matric_no + "</td><td>" + rrr + "</td></tr>";
                });
              }

              table += "</table>";

              // Open a new window with the formatted data
              var exportWindow = window.open("", "_blank");
              exportWindow.document.write("<html><head><title>Exported Data</title> <style>body {padding: 50px;margin: 0;width: 100%;font-family: sans-serif;box-sizing: border-box;} table{width: 80%} th{text-align: left}</style></head><body>");
              exportWindow.document.write(heading+table);
              exportWindow.document.write("</body></html>");

              // Add a print button in the new window
              exportWindow.document.write("<script>window.print();</scr" + "ipt>");
                // AJAX call successful, stop the spinner and update button text
                button.html(originalText);
                button.prop("disabled", false);
            },
            error: function () {
              alert("Error fetching data.");
            },
          });
        }, 2000); // Simulated AJAX delay of 2 seconds
      });
    });
  </script>
  <!-- End custom js for this page-->
</body>

</html>