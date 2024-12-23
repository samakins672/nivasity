<?php
session_start();
include('../model/config.php');
include('../model/page_config.php');

if ($_SESSION['nivas_userRole'] == 'student' || $_SESSION['nivas_userRole'] == 'visitor') {
  header('Location: ../store.php');
  exit();
} elseif ($_SESSION['nivas_userRole'] == 'hoc') {
  $item_table = "manuals";
  $item_table2 = "manuals_bought";
  $column_id = "manual_id";
} else {
  $item_table = "events";
  $item_table2 = "event_tickets";
  $column_id = "event_id";
}

$t_items = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(id) FROM $item_table WHERE user_id = $user_id"))[0];
$t_items_sold = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT($column_id) FROM $item_table2 WHERE seller = $user_id"))[0];
$t_items_price = mysqli_fetch_array(mysqli_query($conn, "SELECT SUM(price) FROM $item_table2 WHERE seller = $user_id"))[0];
$t_students = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(id) FROM users WHERE school = $school_id AND dept = $user_dept"))[0];

$open_manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(id) FROM $item_table WHERE user_id = $user_id AND status = 'open'"))[0];
$closed_manuals = $t_items - $open_manuals;

$manual_query2 = mysqli_query($conn, "SELECT $column_id, SUM(price) AS total_sales
    FROM $item_table2
    WHERE seller  = $user_id
    GROUP BY $column_id
    ORDER BY total_sales DESC
    LIMIT 3");


$manual_query = mysqli_query($conn, "SELECT * FROM $item_table WHERE user_id = $user_id ORDER BY `id` DESC");
$event_query = mysqli_query($conn, "SELECT * FROM events WHERE user_id = $user_id ORDER BY `id` DESC");

$transaction_query = mysqli_query($conn, "SELECT DISTINCT ref_id, buyer FROM $item_table2 WHERE seller = $user_id ORDER BY `created_at` DESC");

$settlement_query = mysqli_query($conn, "SELECT * FROM settlement_accounts WHERE user_id = $user_id ORDER BY `id` DESC LIMIT 1");

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Dashboard - Nivasity</title>

  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="Dashboard - Nivasity">
  <meta property="og:description" content="Nivasity is a platform dedicated to enhancing the educational experience, connecting students, educators, and event organizers in a seamless and innovative way.">
  <meta property="og:image" content="https://nivasity.com/assets/images/nivasity-main.png">
  <meta property="og:url" content="https://nivasity.com">
  <meta property="og:type" content="website">

  <!-- Twitter Meta Tags -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Dashboard - Nivasity">
  <meta name="twitter:description" content="Nivasity is a platform dedicated to enhancing the educational experience, connecting students, educators, and event organizers in a seamless and innovative way.">
  <meta name="twitter:image" content="https://nivasity.com/assets/images/nivasity-main.png">

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
                      <a class="nav-link px-3 active fw-bold" id="home-tab" data-bs-toggle="tab" href="#overview"
                        role="tab" aria-controls="overview" aria-selected="true">Overview</a>
                    </li>
                    <?php if ($user_status == 'verified' && $_SESSION['nivas_userRole'] == 'hoc'): ?>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold" id="profile-tab" data-bs-toggle="tab" href="#manuals" role="tab"
                        aria-selected="false">Materials</a>
                    </li>
                    <?php elseif ($user_status == 'verified' && $_SESSION['nivas_userRole'] == 'org_admin'): ?>
                    <li class="nav-item">
                      <a class="nav-link px-3 fw-bold" id="contact-tab" data-bs-toggle="tab" href="#events" role="tab"
                        aria-selected="false">Events</a>
                    </li>
                    <?php endif; ?>
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
                            <h3 class="rate-percentage">&#8358; <?php echo number_format($t_items_price) ?></h3>
                          </div>
                          <div>
                            <p class="statistics-title fw-bold">Total<?php echo ($column_id == 'manual_id') ? ' Materials' : ' Events'; ?></p>
                            <h3 class="rate-percentage"><?php echo $t_items ?></h3>
                          </div>
                          <div class="d-none d-md-block">
                            <p class="statistics-title fw-bold">Total Sales</p>
                            <h3 class="rate-percentage"><?php echo $t_items_sold ?></h3>
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
                              <div class="card-body">
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
                        <div class="row flex-grow">
                          <div class="col-12 grid-margin stretch-card">
                            <div class="card card-rounded shadow-sm">
                              <div class="card-body">
                                <div class="d-sm-flex justify-content-between align-items-start">
                                  <div>
                                    <h4 class="card-title card-title-dash">Best Selling<?php echo ($column_id == 'manual_id') ? ' Materials' : ' Events'; ?></h4>
                                    <p class="card-subtitle card-subtitle-dash">You have <span
                                        class="text-success fw-bold"><?php echo $open_manuals ?> active</span><?php echo ($column_id == 'manual_id') ? ' materials' : ' events'; ?> and <span
                                        class="text-warning fw-bold"><?php echo $closed_manuals ?> expired</span><?php echo ($column_id == 'manual_id') ? ' Materials' : ' Events'; ?>.</p>
                                  </div>
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
                                        $manual_id = $manual[$column_id];

                                        $manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM $item_table WHERE id = $manual_id"));
                                        $manuals_bought_cnt = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT($column_id) FROM $item_table2 WHERE $column_id = $manual_id"))[0];

                                        // Retrieve the status
                                        $status = $manuals['status'];
                                        ?>
                                      <tr>
                                        <td>
                                          <div class="d-flex ">
                                            <div>
                                              <h6><?php echo $manuals['title'] ?></h6>
                                              <p class="d-sm-none-2">ID: <span class="fw-bold"><?php echo $manuals['code'] ?></span></p>
                                            </div>
                                          </div>
                                        </td>
                                        <td>
                                          <h6 class="text-secondary">&#8358; <?php echo number_format($manual['total_sales']) ?></h6>
                                          <p>Qty Sold: <span class="fw-bold"><?php echo $manuals_bought_cnt ?></span></p>
                                        </td>
                                        <td>
                                            <div class="badge <?php echo ($status == 'open') ? 'bg-success' : 'bg-danger'; ?>"> <?php echo ($status == 'open') ? 'Active' : 'Closed'; ?> </div>
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
                            if (mysqli_num_rows($transaction_query)) {
                            while ($transaction = mysqli_fetch_array($transaction_query)) {
                              $transaction_id = $transaction['ref_id'];
                              $buyer_id = $transaction['buyer'];

                              $buyer = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM users WHERE id = $buyer_id"));

                              $transactions_bought_cnt = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(ref_id) FROM $item_table2 WHERE ref_id = '$transaction_id' AND seller = $user_id"))[0];
                              $transactions_bought_price = mysqli_fetch_array(mysqli_query($conn, "SELECT SUM(price) FROM $item_table2 WHERE ref_id = '$transaction_id' AND seller = $user_id"))[0];
                              $created_at = mysqli_fetch_array(mysqli_query($conn, "SELECT created_at FROM $item_table2 WHERE ref_id = '$transaction_id' LIMIT 1"))[0];
                              
                              // Retrieve and format the due date
                              $created_date = date('M j', strtotime($created_at));
                              $created_time = date('h:i a', strtotime($created_at));
                              ?>
                              <div class="wrapper d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div class="d-flex">
                                  
                                  <div class="wrapper ms-3">
                                    <p class="mb-1 fw-bold"><?php echo $transactions_bought_cnt ?><?php echo ($column_id == 'manual_id') ? ' materials' : ' events'; ?> bought by <span class="text-capitalize"><?php echo $buyer['first_name']?></span></p>
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
                          <div class="card-body">
                            <div class="d-sm-flex justify-content-center align-items-center">
                              <div>
                                
                                <h4 class="card-title text-center text-white fw-bold"><i class="mdi mdi-account-clock h1"></i><br><br>VERIFICATION IN PROGRESS...</h4>
                              </div>
                            </div>
                            <div>
                              <?php if ($_SESSION['nivas_userRole'] == 'hoc'): ?>
                              <h4 class="lh-base text-center text-white">Our Support team is currently verifying your role at your school. This should be sorted within <span class="text-primary">a few hours</span>.<br><br>However, please go on to <a href="user.php" class="text-primary fw-bold">profile settings</a> to add your Settlement Account.</h4>
                              <?php else: ?>
                              <h4 class="lh-base text-center text-white">Our Support team is currently verifying your business information. This should be sorted within <span class="text-primary">a few hours</span>.<br><br>However, please go on to <a href="user.php" class="text-primary fw-bold">profile settings</a> to add your Settlement Account.</h4>
                              <?php endif; ?></div>
                          </div>
                        </div>
                      </div>
                    </div>  
                    <?php endif; ?>      
                  </div>
                  <?php if ($user_status == 'verified' && $_SESSION['nivas_userRole'] == 'hoc'): ?>
                  <div class="tab-pane fade hide" id="manuals" role="tabpanel" aria-labelledby="manuals">
                    <div class="row flex-grow">
                      <div class="col-12 grid-margin stretch-card">
                        <div class="card card-rounded shadow-sm">
                          <div class="card-body">
                            <div class="d-sm-flex justify-content-end">
                              <div>
                              <?php if (mysqli_num_rows($settlement_query) > 0): ?>
                                <button class="btn btn-primary btn-lg text-white mb-0 me-0" type="button"
                                  data-bs-toggle="modal" data-bs-target="#<?php echo $manual_modal = ($user_status == 'verified') ? 'addManual' : 'verificationManual' ?>"><i class="mdi mdi-book"></i>Add new
                                  material</button>
                              <?php else: ?> 
                                <button class="btn btn-primary btn-lg text-white mb-0 me-0" type="button"
                                  data-bs-toggle="modal" data-bs-target="#addSettlement"><i class="mdi mdi-book"></i>Add new
                                  material</button>
                              <?php endif; ?> 
                              </div>
                            </div>
                            <div class="table-responsive  mt-1">
                              <table class="table table-hover select-table datatable-opt">
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

                                  $manuals_bought_cnt = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(manual_id) FROM $item_table2 WHERE manual_id = $manual_id"))[0];
                                  $manuals_bought_price = mysqli_fetch_array(mysqli_query($conn, "SELECT SUM(price) FROM $item_table2 WHERE manual_id = $manual_id"))[0];

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
                                        <p>Sold: <span class="fw-bold"><?php echo $manuals_bought_cnt ?></span></p>
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
                                        <td>
                                          <div class="badge <?php echo ($status == 'open') ? 'bg-success' : 'bg-danger'; ?>"> <?php echo ($status == 'open') ? 'Active' : 'Closed'; ?> </div>
                                        </td>
                                        <td>
                                          <div class="dropdown">
                                            <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="true">
                                              <i class="mdi mdi-dots-vertical fs-4"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                              <a class="dropdown-item view-edit-manual border-bottom d-flex" href="javascript:;"
                                                data-manual_id="<?php echo $manual['id']; ?>" data-title="<?php echo $manual['title']; ?>"
                                                data-course_code="<?php echo $manual['course_code']; ?>" data-price="<?php echo $manual['price']; ?>"
                                                data-quantity="<?php echo $manual['quantity']; ?>"
                                                data-due_date="<?php echo date('Y-m-d', strtotime($manual['due_date'])); ?>" 
                                                data-bs-toggle="modal" data-bs-target="#<?php echo $manual_modal = ($user_status == 'verified') ? 'addManual': 'verificationManual'?>">
                                                <i class="mdi mdi-book-edit pe-2"></i> Edit material
                                              </a>
                                              <?php if($manuals_bought_cnt >= 1): ?>
                                                <a class="dropdown-item export-manual border-bottom d-flex" href="javascript:;" data-bs-toggle="modal" data-bs-target="#exportManual"
                                                  data-manual_id="<?php echo $manual['id']; ?>" data-code="<?php echo $manual['course_code']; ?>">
                                                  <i class="mdi mdi-export-variant pe-2"></i> Export list
                                                </a>
                                              <?php endif; ?>
                                              <a class="dropdown-item <?php echo ($manuals_bought_cnt < 1) ? 'border-bottom' : '' ?> share_button d-flex" data-title="<?php echo $manual['title']; ?>" 
                                                data-product_id="<?php echo $manual['id']; ?>" data-type="product" href="javascript:;"> 
                                                <i class="mdi mdi-share pe-2"></i> Share material
                                              </a>
                                              <?php if($manuals_bought_cnt < 1): ?>
                                                <a class="dropdown-item close-manual d-flex" href="javascript:;"
                                                  data-product_id="<?php echo $manual['id']; ?>" data-title="<?php echo $manual['title']; ?>" data-type="product"
                                                  data-bs-toggle="modal" data-bs-target="#closeManual">
                                                  <i class="mdi mdi-delete pe-2"></i> Delete material
                                                </a>
                                              <?php endif; ?>
                                            </div>
                                          </div>
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
                  
                  <?php elseif ($user_status == 'verified' && $_SESSION['nivas_userRole'] == 'org_admin'): ?>
                  <div class="tab-pane fade hide" id="events" role="tabpanel" aria-labelledby="events">
                    <div class="row flex-grow">
                        <div class="col-12 grid-margin stretch-card">
                          <div class="card card-rounded shadow-sm">
                            <div class="card-body p-2">
                              <div class="d-sm-flex justify-content-end">
                                <div>
                                <?php if (mysqli_num_rows($settlement_query) > 0): ?>
                                  <button class="btn btn-primary btn-lg text-white mb-0 me-0" type="button"
                                    data-bs-toggle="modal" data-bs-target="#<?php echo $event_modal = ($user_status == 'verified') ? 'addEvent' : 'verificationManual' ?>"><i class="mdi mdi-book"></i>Add new
                                    event</button>
                                <?php else: ?> 
                                  <button class="btn btn-primary btn-lg text-white mb-0 me-0" type="button"
                                    data-bs-toggle="modal" data-bs-target="#addSettlement"><i class="mdi mdi-book"></i>Add new
                                    event</button>
                                <?php endif; ?> 
                                </div>
                              </div>
                              <div class="table-responsive mt-1">
                                <table class="table table-hover select-table datatable-opt">
                                  <thead>
                                    <tr>
                                      <th>Event</th>
                                      <th class="d-sm-none-2">Unit Price</th>
                                      <th>Revenue</th>
                                      <th class="d-sm-none-2">Availability</th>
                                      <th class="d-sm-none-2">Date & Time</th>
                                      <th>Status</th>
                                      <th>Actions</th>
                                    </tr>
                                  </thead>
                                  <tbody id="event_tbody">
                                  <?php
                                  while ($event = mysqli_fetch_array($event_query)) {
                                    $event_id = $event['id'];

                                    $events_bought_cnt = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(event_id) FROM event_tickets WHERE event_id = $event_id"))[0];
                                    $events_bought_price = mysqli_fetch_array(mysqli_query($conn, "SELECT SUM(price) FROM event_tickets WHERE event_id = $event_id"))[0];

                                    // Calculate the percentage and total sold/quantity text
                                    $percentage_sold = ($events_bought_cnt / $event['quantity']) * 100;
                                    $sold_quantity_text = $events_bought_cnt . '/' . $event['quantity'];
                                    
                                    // Retrieve and format the event date
                                    $event_date = date('j M, Y', strtotime($event['event_date']));
                                    $event_date2 = date('Y-m-d', strtotime($event['event_date']));
                                    
                                    $event_time = date('g:i A', strtotime($event['event_time']));
                                    $event_time2 = date('H:i', strtotime($event['event_time']));
                                    // Retrieve the status
                                    $status = $event['status'];
                                    $status_2 = $status;
                                    
                                    if ($date > $event_date2) {
                                      $status = 'overdue';
                                    }
                                    ?>
                                      <tr>
                                        <td>
                                          <div class="d-md-flex justify-content-start">
                                            <a href="#">
                                              <img src="../assets/images/events/<?php echo $event['event_banner'] ?>" alt="<?php echo $event['title'] ?>" class="img-fluid rounded-2" style="min-width: 100px">
                                            </a>
                                            <div>
                                              <h6><?php echo $event['title'] ?></h6>
                                              <p class="d-sm-none-2">ID: <span class="fw-bold"><?php echo $event['code'] ?></span></p>
                                            </div>
                                          </div>
                                        </td>
                                        <td class="d-sm-none-2">
                                          <h6>&#8358; <?php echo number_format($event['price']) ?></h6>
                                        </td>
                                        <td>
                                          <h6 class="text-secondary">&#8358; <?php echo number_format($events_bought_price) ?></h6>
                                          <p>Sold: <span class="fw-bold"><?php echo $events_bought_cnt ?></span></p>
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
                                            <h6><?php echo $event_date ?></h6>
                                            <p class="fw-bold"><?php echo $event_time ?></p>
                                          </td>
                                          <td>
                                            <div class="badge <?php echo ($status == 'open') ? 'bg-success' : 'bg-danger'; ?>"> <?php echo ($status == 'open') ? 'Active' : 'Closed'; ?> </div>
                                          </td>
                                          <td>
                                            <div class="dropdown">
                                              <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="true">
                                                <i class="mdi mdi-dots-vertical fs-4"></i>
                                              </button>
                                              <div class="dropdown-menu">
                                                <a class="dropdown-item view-edit-event border-bottom d-flex" href="javascript:;"
                                                data-event_id="<?php echo $event['id']; ?>" data-title="<?php echo $event['title']; ?>" data-description="<?php echo $event['description']; ?>"
                                                data-price="<?php echo $event['price']; ?>" data-quantity="<?php echo $event['quantity']; ?>"
                                                data-location="<?php echo $event['location']; ?>" data-image="<?php echo $event['event_banner']; ?>"
                                                data-event_type="<?php echo $event['event_type']; ?>" data-event_link="<?php echo $event['event_link']; ?>"
                                                data-school="<?php echo $event['school']; ?>" data-location="<?php echo $event['location']; ?>"
                                                data-event_time="<?php echo $event_time2; ?>" data-event_date="<?php echo $event_date2; ?>" 
                                                data-bs-toggle="modal" data-bs-target="#<?php echo $event_modal = ($user_status == 'verified') ? 'addEvent': 'verificationManual'?>"> 
                                                  <i class="mdi mdi-calendar-edit pe-2"></i> Edit event
                                                </a>
                                                <?php if($events_bought_cnt >= 1): ?>
                                                  <a class="dropdown-item export_event border-bottom d-flex" href="javascript:;" data-title="<?php echo $event['title']; ?>" data-event_id="<?php echo $event['id']; ?>">
                                                    <i class="mdi mdi-export-variant pe-2"></i> Export guest list
                                                  </a>
                                                  <a class="dropdown-item email_event_guests border-bottom d-flex" href="javascript:;" data-title="<?php echo $event['title']; ?>" data-event_id="<?php echo $event['id']; ?>"
                                                    data-bs-toggle="modal" data-bs-target="#emailGuests">
                                                    <i class="mdi mdi-email-multiple pe-2"></i> Email guests
                                                  </a>
                                                <?php endif; ?>
                                                <a class="dropdown-item <?php echo ($events_bought_cnt < 1) ? 'border-bottom' : '' ?> share_button d-flex" data-title="<?php echo $event['title']; ?>" 
                                                data-product_id="<?php echo $event['id']; ?>" data-type="event" href="javascript:;">
                                                  <i class="mdi mdi-share pe-2"></i> Share event
                                                </a>
                                                <?php if($events_bought_cnt < 1): ?>
                                                  <a class="dropdown-item close-manual d-flex" href="javascript:;"
                                                    data-product_id="<?php echo $event['id']; ?>" data-title="<?php echo $event['title']; ?>" data-type="event"
                                                    data-bs-toggle="modal" data-bs-target="#closeManual">
                                                    <i class="mdi mdi-delete pe-2"></i> Delete event
                                                  </a>
                                                <?php endif; ?>
                                              </div>
                                            </div>
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
                  <?php endif; ?>

                </div>

                <!-- Close Manual Modal -->
                <div class="modal fade" id="closeManual" tabindex="-1" role="dialog" aria-labelledby="closeManualLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h4 class="modal-title fw-bold" id="closeManualLabel">Delete Material</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="close-manual-form">
                        <input type="hidden" name="delete_manual" value="1">
                        <input type="hidden" name="product_type" value="0">
                        <input type="hidden" name="product_id" value="0">
                        <div class="modal-body">
                          <div>
                            <h4 class="lh-base">Are you sure you want to delete <span class="manual_title text-primary">Manual Title</span>?</h4>
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
                          <?php if ($_SESSION['nivas_userRole'] == 'hoc'): ?>
                          <h4 class="lh-base">Our Support team is currently verifying your role at your school. This should be sorted within <span class="text-primary">a few hours</span>.<br><br>However, to speed up the proccess, you can use the support tickets and upload means of verification regarding your role at your school.</h4>
                          <?php else: ?>
                          <h4 class="lh-base">Our Support team is currently verifying your business information. This should be sorted within <span class="text-primary">a few hours</span>.<br><br>However, to speed up the proccess, you can use the support tickets and upload means of verification regarding your role at your school.</h4>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- User addSettlement Modal -->
                <div class="modal fade" id="addSettlement" tabindex="-1" role="dialog" aria-labelledby="addSettlementLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h4 class="modal-title fw-bold" id="addSettlementLabel">Add Settlement Account</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <div class="modal-body">
                        <div>
                          <?php if ($_SESSION['nivas_userRole'] == 'hoc'): ?>
                          <h4 class="lh-base">Please go on to <a href="user.php" class="text-primary fw-bold">profile settings</a> to add your Settlement Account before you can create an manual.</h4>
                          <?php else: ?>
                          <h4 class="lh-base">Please go on to <a href="user.php" class="text-primary fw-bold">profile settings</a> to add your Settlement Account before you can create an event.</h4>
                          <?php endif; ?>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>

                <?php if ($user_status == 'verified' && $_SESSION['nivas_userRole'] == 'hoc'): ?>
                <!-- Add new material Modal -->
                <div class="modal fade" id="addManual" tabindex="-1" role="dialog" aria-labelledby="addManualLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="addManualLabel">New Course Material</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="manual-form">
                        <input type="hidden" name="manual_id" value="0">
                        <div class="modal-body">
                          <div class="form-outline mb-2">
                            <input type="text" name="title" class="form-control form-control-lg w-100" required="">
                            <label class="form-label" for="title">Material Title</label>
                          </div>
                          <div class="row">
                            <div class="col-12">
                              <div class="form-check form-switch">
                                <input class="form-check-input form-check-inline free" type="checkbox" id="free">
                                <label class="form-check-label" for="free">toggle if it is FREE</label>
                              </div>
                            </div>
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

                <?php elseif ($user_status == 'verified' && $_SESSION['nivas_userRole'] == 'org_admin'): ?>
                  
                <!-- Add New Event Modal -->
                <div class="modal fade" id="addEvent" tabindex="-1" role="dialog" aria-labelledby="addEventLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="addEventLabel">New Event</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="event-form">
                        <input type="hidden" name="event_id" value="0">
                        <div class="modal-body">
                          <div class="row mb-4">
                            <div class="col-8 square-img rounded rounded-10 shadow-sm ms-3 p-0">
                              <img src="../assets/images/events/image.png" class="square-img-content" alt="Avatar" />
                            </div>
                            <div class="col-3 my-auto">
                              <input type="file" id="upload" name="upload" class="account-file-input" hidden=""
                                accept="image/png, image/jpeg">
                              <label for="upload" class="btn btn-primary fw-bold btn-lg btn-block">
                                <span class="d-none d-md-block">Upload</span>
                                <i class="icon-upload d-md-none mx-2"></i>
                              </label>
                            </div>
                          </div>
                          <div class="form-outline mb-4">
                            <input type="text" name="title" class="form-control form-control-lg w-100" required="">
                            <label class="form-label" for="title">Title</label>
                          </div>
                          <div class="wysi-editor mb-4">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control w-100 px-3 py-2" name="description" required></textarea>
                          </div>
                          <label class="form-check-label">Event Type</label><br />
                          <div class="form-group">
                            <select id="event_type" name="event_type" class="form-select" required>
                              <option value="school">School Event</option>
                              <option value="online">Online Event</option>
                              <option value="public">Public Event</option>
                            </select>
                          </div>

                          <div class="form-outline mb-4" id="event_link-container">
                            <input type="url" name="event_link" class="form-control form-control-lg w-100">
                            <label class="form-label" for="event_link">Event Link</label>
                          </div>

                          <div class="form-group mb-4" id="school-container">
                            <label class="form-check-label mb-0">School</label><br />
                            <select id="school" name="school" class="form-select" required></select>
                          </div>

                          <div class="form-outline mb-4" id="location-container">
                            <input type="text" name="location" class="form-control form-control-lg w-100">
                            <label class="form-label" for="location">Location</label>
                          </div>
                          <div class="form-check form-switch">
                            <input class="form-check-input form-check-inline free" type="checkbox" id="free">
                            <label class="form-check-label" for="free">toggle if it is FREE</label>
                          </div>

                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="number" name="price" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="price">Unit Price</label>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="number" name="quantity" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="quantity">Number of Tickets</label>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="date" name="event_date" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="event_date">Date</label>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="time" name="event_time" class="form-control form-control-lg w-100"
                                  required="">
                                <label class="form-label" for="event_time">Time</label>
                              </div>
                            </div>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Cancel</button>
                          <button id="event_submit" type="submit" data-mdb-ripple-duration="0"
                            class="btn btn-lg btn-primary">Submit</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

                <!-- Email Event Guests Modal -->
                <div class="modal fade" id="emailGuests" tabindex="-1" role="dialog" aria-labelledby="emailGuestsLabel"
                  aria-hidden="true">
                  <div class="modal-dialog" role="document">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="emailGuestsLabel">Email <span class="event_title_ fw-bold"></span> Guests</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </button>
                      </div>
                      <form id="email-guest-form">
                        <input type="hidden" name="email_guests" value="1">
                        <input type="hidden" name="event_id" value="0">
                        <input type="hidden" name="title" value="0">
                        <div class="modal-body">
                          <div class="wysi-editor mb-4">
                            <label class="form-label" for="message">Message</label>
                            <textarea class="form-control w-100 px-3 py-2" name="message" required></textarea>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Cancel</button>
                          <button id="email_submit" type="submit" data-mdb-ripple-duration="0"
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
  <!--Start of Tawk.to Script-->
  <script type="text/javascript">
    var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
    (function(){
    var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
    s1.async=true;
    s1.src='https://embed.tawk.to/6722bbbb4304e3196adae0cd/1ibfqqm4s';
    s1.charset='UTF-8';
    s1.setAttribute('crossorigin','*');
    s0.parentNode.insertBefore(s1,s0);
    })();
  </script>
  <!--End of Tawk.to Script-->

  <script>
    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');

      $('#addManual').on('hidden.bs.modal', function () {
        // Reset the form by setting its values to empty
        $('#manual-form input[name="manual_id"]').val(0);
        $('#manual-form')[0].reset();
      });

      $('#addEvent').on('hidden.bs.modal', function () {
        // Reset the form by setting its values to empty
        $('#event-form input[name="event_id"]').val(0);
        $('#event-form .square-img-content').attr('src',"../assets/images/events/image.png");
        $('#event-form')[0].reset();
        
        // Trigger change event on page load to set initial state
        $('#event_type').trigger('change');
      });
      
      $('#exportManual').on('hidden.bs.modal', function () {
        // Reset the form by setting its values to empty
        $('#export-manual-form')[0].reset();
      });
      
      $('#upload').on('change', function (event) {
        const file = event.target.files[0]; // Get the uploaded file
        if (file) {
          const reader = new FileReader();

          reader.onload = function (e) {
            $('.square-img-content').attr('src', e.target.result); // Set the src attribute of the image
          };

          reader.readAsDataURL(file); // Read the file as a data URL
        }
      });

      // Listen for changes on the checkbox
      $('.free').change(function () {
        // Check if the checkbox is checked
        if ($(this).is(':checked')) {
          // Set price input to read-only and set value to 0
          $('input[name="price"]').prop('readonly', true).val(0);
        } else {
          // Make price input editable again and clear the value
          $('input[name="price"]').prop('readonly', false).val('');
        }
      });

      $('#event_type').change(function() {
        // Hide all containers and remove the required attribute
        $('#school-container').hide().find('select').prop('required', false);
        $('#event_link-container').hide().find('input').prop('required', false);
        $('#location-container').hide().find('input').prop('required', false);

        // Show the appropriate container and make it required
        if ($(this).val() === 'school') {
            $('#school-container').show().find('select').prop('required', true);
        } else if ($(this).val() === 'online') {
            $('#event_link-container').show().find('input').prop('required', true);
        } else if ($(this).val() === 'public') {
            $('#location-container').show().find('input').prop('required', true);
        }
      });

      // Trigger change event on page load to set initial state
      $('#event_type').trigger('change');

      $.ajax({
        type: 'GET',
        url: '../model/getInfo.php',
        data: { get_data: 'schools' },
        success: function (data) {
          // Get the select element
          var school_select = $('#school');

          // Sort the schools alphabetically by name
          data.schools.sort(function (a, b) {
              return a.name.localeCompare(b.name);
          });

          // Iterate through the departments and add options
          $.each(data.schools, function (index, schools) {
            // Append each department as an option to the select element
            school_select.append($('<option>', {
              value: schools.id,
              text: schools.name
            }));
          });
        }
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
        event.preventDefault();

        // Define manual button
        var button = $('#manual_submit');
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border spinner-border-sm text-white" style="width: 1.5rem; height: 1.5rem;" role="status"><span class="sr-only"></span>');
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
        }, 2000);
      });

      // Handle click event of View/Edit button
      $('.view-edit-event').on('click', function () {
        // Get the event details from the data- attributes
        var eventId = $(this).data('event_id');
        var title = $(this).data('title');
        var price = $(this).data('price');
        var quantity = $(this).data('quantity');
        var event_type = $(this).data('event_type');
        var event_link = $(this).data('event_link');
        var school = $(this).data('school');
        var location = $(this).data('location');
        var event_date = $(this).data('event_date');
        var event_time = $(this).data('event_time');
        var image = $(this).data('image');
        var description = $(this).data('description');

        // Set the values in the edit event modal
        $('#event-form input[name="event_id"]').val(eventId);
        $('#event-form input[name="title"]').val(title);
        $('#event-form textarea[name="description"]').val(description);
        $('#event-form input[name="price"]').val(price);
        $('#event-form select[name="event_type"]').val(event_type);
        $('#event-form input[name="event_link"]').val(event_link);
        $('#event-form input[name="school"]').val(school);
        $('#event-form input[name="location"]').val(location);
        $('#event-form input[name="quantity"]').val(quantity);
        $('#event-form input[name="event_date"]').val(event_date);
        $('#event-form input[name="event_time"]').val(event_time);
        $('#event-form .square-img-content').attr('src',"../assets/images/events/" + image);

        // Trigger change event on page load to set initial state
        $('#event_type').trigger('change');
      });

      // Handle click event of email_event_guests button
      $('.email_event_guests').on('click', function () {
        // Get the event details from the data- attributes
        var eventId = $(this).data('event_id');
        var title = $(this).data('title');

        // Set the values in the email guests modal
        $('#email-guest-form input[name="event_id"]').val(eventId);
        $('#email-guest-form input[name="title"]').val(title);
        $('.event_title_').text(title);

        $('#email-guest-form')[0].reset();
      });

      $(document).on('click', '.share_button', function (e) {
        var button = $(this);
        var product_id = button.data('product_id');
        var type = button.data('type');
        var title = button.data('title');
        var shareText = 'Check out '+title+' on nivasity and order now!';
        if (type == 'product') {
          var shareUrl = "https://nivasity.com/model/cart_guest.php?share=1&action=1&type="+type+"&product_id="+product_id;
        } else {
          var shareUrl = "https://nivasity.com/event_details.php?event_id="+product_id;
        }

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

      // Use AJAX to submit the event form
      $('#event-form').submit(function (event) {
        event.preventDefault();

        // Define event button
        var button = $('#event_submit');
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border spinner-border-sm text-white" style="width: 1.5rem; height: 1.5rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        var formData = new FormData($('#event-form')[0]);

        $.ajax({
          type: 'POST',
          url: 'model/events.php',
          data: formData,
          contentType: false,
          processData: false,
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
      });

      // Use AJAX to submit the email guest form
      $('#email-guest-form').submit(function (event) {
        event.preventDefault();

        // Define event button
        var button = $('#email_submit');
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border spinner-border-sm text-white" style="width: 1.5rem; height: 1.5rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        var formData = new FormData($('#email-guest-form')[0]);

        $.ajax({
          type: 'POST',
          url: 'model/events.php',
          data: formData,
          contentType: false,
          processData: false,
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
      });

      // Handle click event of View/Edit button
      $('.close-manual').on('click', function () {
        // Get the manual details from the data- attributes
        var product_id = $(this).data('product_id');
        var type = $(this).data('type');
        var title = $(this).data('title');

        if (type == 'event') {
          $('#closeManualLabel').html('Delete Event');
        } else {
          $('#closeManualLabel').html('Delete Material');
        }

        // Set the values in the edit manual modal
        $('#close-manual-form input[name="product_id"]').val(product_id);
        $('#close-manual-form input[name="product_type"]').val(type);
        $('.manual_title').html(title);
      });
      
      $('#close-manual-form').submit(function (event) {
        event.preventDefault();

        // Define manual button
        var button = $('#close_manual_submit');
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border spinner-border-sm text-white" style="width: 1.5rem; height: 1.5rem;" role="status"><span class="sr-only"></span>');
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
        }, 2000);
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
        event.preventDefault();

        var manualId = $('#export-manual-form input[name="manual_id"]').val();
        var code = $('#export-manual-form input[name="code"]').val();
        var rrr = $('#export-manual-form input[name="rrr"]').val();

        // Define export button
        var button = $('#export_manual_submit');
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border spinner-border-sm text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
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
              exportWindow.document.write("<html><head><title>Exported Data</title> <style>body {padding: 50px;margin: 0;width: 100%;font-family: sans-serif;box-sizing: border-box;} table{width: 100%} th{text-align: left}</style></head><body>");
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
        }, 2000);
      });

      $('.export_event').click(function (event) {
        var event_id = $(this).data('event_id');
        var title = $(this).data('title');

        // Define export button
        var button = $(this);
        var originalText = button.html();

        // Display the spinner and disable the button
        button.html('<div class="spinner-border spinner-border-sm text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        // Simulate an AJAX call using setTimeout
        setTimeout(function () {
          // Call the Ajax function to get data
          $.ajax({
            url: "../model/export.php", // Replace with your server-side script to fetch data
            type: "POST",
            data: {event_id: event_id},
            success: function (data) {
              heading = "<center><h2 style='text-transform: uppercase'>TICKET LIST FOR "+title+"</h2></center>"
              
              // Format data into a table
              var table = "<table><tr><th>S/N</th><th>NAMES</th><th>DATE</th><th>TICKET ID</th></tr>";

              $.each(data, function (index, item) {
                // Parse the date
                var dateObj = new Date(item.created_at);
                
                // Format date to 'DD MMM.' 
                var day = dateObj.getDate().toString().padStart(2, '0');
                var month = dateObj.toLocaleString("en-US", { month: "short" });
                var formattedDate = `${day} ${month}.`;

                // Format time to 'HH:MM AM/PM'
                var formattedTime = dateObj.toLocaleTimeString("en-US", {
                  hour: '2-digit', minute: '2-digit', hour12: true
                });

                // Combine date and time
                var formattedDateTime = formattedDate + " " + formattedTime;

                table += "<tr><td>" + (index + 1) + "</td><td>" + item.name + "</td><td>" + formattedDateTime + "</td><td>" + item.ref_id + "</td></tr>";
              });
              
              table += "</table>";

              // Open a new window with the formatted data
              var exportWindow = window.open("", "_blank");
              exportWindow.document.write("<html><head><title>Exported Data</title> <style>body {padding: 50px;margin: 0;width: 100%;font-family: sans-serif;box-sizing: border-box;} table{width: 100%} th{text-align: left}</style></head><body>");
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
        }, 2000);
      });
    });
  </script>
  <!-- End custom js for this page-->
</body>

</html>