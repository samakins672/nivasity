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
  <link rel="shortcut icon" href="favicon.ico" />
</head>

<body>
  <div class="container-scroller sidebar-fixed">
    <!-- partial:partials/_navbar.html -->
    <nav class="navbar default-layout col-lg-12 col-12 p-0 fixed-top d-flex align-items-top flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-start">
        <div class="me-3">
          <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-bs-toggle="minimize">
            <span class="icon-menu"></span>
          </button>
        </div>
        <div>
          <a class="navbar-brand brand-logo" href="index.html">
            <img src="../assets/images/nivasity-manuals.png" alt="logo" />
          </a>
          <a class="navbar-brand brand-logo-mini" href="index.html">
            <img src="../assets/images/nivasity-logo-tr.png" alt="logo" />
          </a>
        </div>
      </div>
      <div class="navbar-menu-wrapper d-flex align-items-top">
        <ul class="navbar-nav">
          <li class="nav-item font-weight-semibold d-none d-lg-block ms-0">
            <h1 class="welcome-text">Good Morning, <span class="text-black fw-bold">John Doe</span></h1>
          </li>
        </ul>
        <ul class="navbar-nav ms-auto">
          <li class="nav-item d-none d-lg-block">
            <div id="datepicker-popup" class="input-group date datepicker navbar-date-picker">
              <span class="input-group-addon input-group-prepend border-right">
                <span class="icon-calendar input-group-text calendar-icon"></span>
              </span>
              <input type="text" class="form-control">
            </div>
          </li>
          <li class="nav-item me-5">
            <form class="search-form" action="#">
              <i class="icon-search"></i>
              <input type="search" class="form-control" placeholder="Search Here" title="Search here">
            </form>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link count-indicator" id="countDropdown" href="#" data-bs-toggle="dropdown"
              aria-expanded="false">
              <i class="icon-bell"></i>
              <span class="count"></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list pb-0"
              aria-labelledby="countDropdown">
              <a class="dropdown-item py-3">
                <p class="mb-0 font-weight-medium float-left">You have 7 unread mails </p>
                <span class="badge badge-pill badge-primary float-right">View all</span>
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                  <img src="../assets/images/faces/face10.jpg" alt="image" class="img-sm profile-pic">
                </div>
                <div class="preview-item-content flex-grow py-2">
                  <p class="preview-subject ellipsis font-weight-medium text-dark">Marian Garner </p>
                  <p class="fw-light small-text mb-0"> The meeting is cancelled </p>
                </div>
              </a>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                  <img src="../assets/images/faces/face12.jpg" alt="image" class="img-sm profile-pic">
                </div>
                <div class="preview-item-content flex-grow py-2">
                  <p class="preview-subject ellipsis font-weight-medium text-dark">David Grey </p>
                  <p class="fw-light small-text mb-0"> The meeting is cancelled </p>
                </div>
              </a>
              <a class="dropdown-item preview-item">
                <div class="preview-thumbnail">
                  <img src="../assets/images/faces/face1.jpg" alt="image" class="img-sm profile-pic">
                </div>
                <div class="preview-item-content flex-grow py-2">
                  <p class="preview-subject ellipsis font-weight-medium text-dark">Travis Jenkins </p>
                  <p class="fw-light small-text mb-0"> The meeting is cancelled </p>
                </div>
              </a>
            </div>
          </li>
          <li class="nav-item dropdown d-none d-lg-block user-dropdown">
            <a class="nav-link" id="UserDropdown" href="#" data-bs-toggle="dropdown" aria-expanded="false">
              <img class="img-xs rounded rounded-7" src="../assets/images/faces/face8.jpg" alt="Profile image"> </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="UserDropdown">
              <div class="dropdown-header text-center">
                <img class="img-md rounded rounded-7" src="../assets/images/faces/face8.jpg" alt="Profile image">
                <p class="mb-1 mt-3 fw-bold">Samuel Akinyemi</p>
                <p class="font-weight-semibold text-muted mb-0">ID: Samakins01</p>
              </div>
              <a class="dropdown-item" href="user.html"><i
                  class="dropdown-item-icon mdi mdi-account-outline text-primary me-2"></i> My
                Profile</a>
              <a class="dropdown-item" href="support.html"><i
                  class="dropdown-item-icon mdi mdi-comment-outline text-primary me-2"></i>
                Support Tickets</a>
              <a class="dropdown-item" href="faq.html"><i
                  class="dropdown-item-icon mdi mdi-help-circle-outline text-primary me-2"></i>
                FAQ</a>
              <a class="dropdown-item" href="signin.html?logout"><i
                  class="dropdown-item-icon mdi mdi-power text-primary me-2"></i>Sign Out</a>
            </div>
          </li>
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button"
          data-bs-toggle="offcanvas">
          <span class="mdi mdi-menu"></span>
        </button>
      </div>
    </nav>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_sidebar.html -->
      <nav class="sidebar sidebar-offcanvas border-start border-2 border-secondary" id="sidebar">
        <ul class="nav">
          <li class="nav-item active">
            <a class="nav-link bg-primary" href="javascript:;" data-bs-toggle="modal" data-bs-target="#addManual">
              <i class="mdi mdi-plus menu-icon text-white"></i>
              <span class="menu-title text-white fw-bold">New Manual</span>
            </a>
          </li>
          <li class="nav-item nav-category">Dashboard</li>
          <li class="nav-item">
            <a class="nav-link" href="/admin">
              <i class="mdi mdi-grid-large menu-icon"></i>
              <span class="menu-title">Overview</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="user.html">
              <i class="mdi mdi-account-outline menu-icon"></i>
              <span class="menu-title">Profile Settings</span>
            </a>
          </li>

          <li class="nav-item nav-category">Support</li>
          <li class="nav-item">
            <a class="nav-link" href="support.html">
              <i class="mdi mdi-comment-outline menu-icon"></i>
              <span class="menu-title">Support Tickets</span>
            </a>
          </li>

          <li class="nav-item nav-category">Sign out</li>
          <li class="nav-item">
            <a class="nav-link" href="../signin.html?logout">
              <i class="menu-icon mdi mdi-power"></i>
              <span class="menu-title">Sign Out</span>
            </a>
          </li>
        </ul>
      </nav>
      <!-- partial -->
      <div class="main-panel">

        <div class="content-wrapper">
          <div class="row">
            <div class="col-sm-12">
              <div class="home-tab">
                <div class="d-sm-flex align-items-center justify-content-between border-bottom">
                  <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                      <a class="nav-link px-3 active ps-0" id="home-tab" data-bs-toggle="tab" href="#overview"
                        role="tab" aria-controls="overview" aria-selected="true">Overview</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link px-3" id="profile-tab" data-bs-toggle="tab" href="#manuals" role="tab"
                        aria-selected="false">Manuals</a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link px-3" id="contact-tab" data-bs-toggle="tab" href="#transactions" role="tab"
                        aria-selected="false">Transactions</a>
                    </li>
                  </ul>
                  <div>
                    <!-- <div class="btn-wrapper">
                      <a href="#" class="btn btn-otline-dark align-items-center"><i class="icon-share"></i> Share</a>
                      <a href="#" class="btn btn-otline-dark"><i class="icon-printer"></i> Print</a>
                      <a href="#" class="btn btn-primary text-white me-0"><i class="icon-download"></i> Export</a>
                    </div> -->
                  </div>
                </div>
                <div class="tab-content tab-content-basic">
                  <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview">
                    <div class="row flex-grow">
                      <div class="col-12 d-none d-md-block">
                        <div
                          class="statistics-details d-flex justify-content-between align-items-center mt-0 mt-md-2 mb-2">
                          <div>
                            <p class="statistics-title">Revenue Earned</p>
                            <h3 class="rate-percentage">302,500k</h3>
                            <p class="text-danger d-flex"><i class="mdi mdi-menu-down"></i><span>-0.5%</span></p>
                          </div>
                          <div>
                            <p class="statistics-title">Total Manuals</p>
                            <h3 class="rate-percentage">12</h3>
                            <p class="text-success d-flex"><i class="mdi mdi-menu-up"></i><span>+0.1%</span></p>
                          </div>
                          <div>
                            <p class="statistics-title">Weekly Revenue</p>
                            <h3 class="rate-percentage">45,000k</h3>
                            <p class="text-danger d-flex"><i class="mdi mdi-menu-down"></i><span>68.8</span></p>
                          </div>
                          <div>
                            <p class="statistics-title">Total Sales</p>
                            <h3 class="rate-percentage">459</h3>
                            <p class="text-danger d-flex"><i class="mdi mdi-menu-down"></i><span>68.8</span></p>
                          </div>
                          <div>
                            <p class="statistics-title">Total Students</p>
                            <h3 class="rate-percentage">4500</h3>
                            <p class="text-danger d-flex"><i class="mdi mdi-menu-down"></i><span>68.8</span></p>
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
                            <div class="card card-rounded shadow-sm table-darkBGImg">
                              <div class="card-body">
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
                        </div>
                        <div class="row flex-grow">
                          <div class="col-12 grid-margin stretch-card">
                            <div class="card card-rounded shadow-sm">
                              <div class="card-body">
                                <div class="d-sm-flex justify-content-between align-items-start">
                                  <div>
                                    <h4 class="card-title card-title-dash">Best Selling Manuals</h4>
                                    <p class="card-subtitle card-subtitle-dash">You have <span
                                        class="text-success fw-bold">13 open</span> manuals and <span
                                        class="text-warning fw-bold">16 closed</span> manuals.</p>
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
                                        <th>Availability</th>
                                        <th>Status</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <tr>
                                        <td>
                                          <div class="d-flex ">
                                            <div>
                                              <h6>Electro-magnetic Field (EEP 201)</h6>
                                              <p>ID: <span class="fw-bold">X2I-WER</span></p>
                                            </div>
                                          </div>
                                        </td>
                                        <td>
                                          <h6 class="text-secondary">&#8358; 35,000</h6>
                                          <p>Qty Sold: <span class="fw-bold">23</span></p>
                                        </td>
                                        <td>
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
                                        <td>
                                          <div class="badge badge-opacity-success">Open</div>
                                        </td>
                                      </tr>

                                      <tr>
                                        <td>
                                          <div class="d-flex ">
                                            <div>
                                              <h6>Electro-magnetic Field (EEP 201)</h6>
                                              <p>ID: <span class="fw-bold">X2I-WER</span></p>
                                            </div>
                                          </div>
                                        </td>
                                        <td>
                                          <h6 class="text-secondary">&#8358; 35,000</h6>
                                          <p>Qty Sold: <span class="fw-bold">23</span></p>
                                        </td>
                                        <td>
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
                                        <td>
                                          <div class="badge badge-opacity-danger">Closed</div>
                                        </td>
                                      </tr>

                                      <tr>
                                        <td>
                                          <div class="d-flex ">
                                            <div>
                                              <h6>Electro-magnetic Field (EEP 201)</h6>
                                              <p>ID: <span class="fw-bold">X2I-WER</span></p>
                                            </div>
                                          </div>
                                        </td>
                                        <td>
                                          <h6 class="text-secondary">&#8358; 35,000</h6>
                                          <p>Qty Sold: <span class="fw-bold">23</span></p>
                                        </td>
                                        <td>
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
                                        <td>
                                          <div class="badge badge-opacity-success">Open</div>
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
                      <div class="col-lg-4 d-flex flex-column">
                        <div class="row flex-grow">
                          <div class="col-lg-12">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                              <div>
                                <h4 class="card-title card-title-dash">Latest Transactions</h4>
                              </div>
                            </div>
                            <div class="mt-3">
                              <div class="wrapper d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div class="d-flex">
                                  <img class="img-sm rounded-10" src="../assets/images/faces/face1.jpg" alt="profile">
                                  <div class="wrapper ms-3">
                                    <p class="ms-1 mb-1 fw-bold">3 manuals bought by Toyosi</p>
                                    <small class="text-secondary mb-0">&#8358; 4,900.00</small>
                                  </div>
                                </div>
                                <div class="text-muted text-small">
                                  Oct 21<br>08:34:52
                                </div>
                              </div>
                              <div class="wrapper d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div class="d-flex">
                                  <img class="img-sm rounded-10" src="../assets/images/faces/face1.jpg" alt="profile">
                                  <div class="wrapper ms-3">
                                    <p class="ms-1 mb-1 fw-bold">3 manuals bought by Toyosi</p>
                                    <small class="text-secondary mb-0">&#8358; 4,900.00</small>
                                  </div>
                                </div>
                                <div class="text-muted text-small">
                                  Oct 21<br>08:34:52
                                </div>
                              </div>
                              <div class="wrapper d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div class="d-flex">
                                  <img class="img-sm rounded-10" src="../assets/images/faces/face1.jpg" alt="profile">
                                  <div class="wrapper ms-3">
                                    <p class="ms-1 mb-1 fw-bold">3 manuals bought by Toyosi</p>
                                    <small class="text-secondary mb-0">&#8358; 4,900.00</small>
                                  </div>
                                </div>
                                <div class="text-muted text-small">
                                  Oct 21<br>08:34:52
                                </div>
                              </div>
                              <div class="wrapper d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div class="d-flex">
                                  <img class="img-sm rounded-10" src="../assets/images/faces/face1.jpg" alt="profile">
                                  <div class="wrapper ms-3">
                                    <p class="ms-1 mb-1 fw-bold">3 manuals bought by Toyosi</p>
                                    <small class="text-secondary mb-0">&#8358; 4,900.00</small>
                                  </div>
                                </div>
                                <div class="text-muted text-small">
                                  Oct 21<br>08:34:52
                                </div>
                              </div>
                              <div class="wrapper d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div class="d-flex">
                                  <img class="img-sm rounded-10" src="../assets/images/faces/face1.jpg" alt="profile">
                                  <div class="wrapper ms-3">
                                    <p class="ms-1 mb-1 fw-bold">3 manuals bought by Toyosi</p>
                                    <small class="text-secondary mb-0">&#8358; 4,900.00</small>
                                  </div>
                                </div>
                                <div class="text-muted text-small">
                                  Oct 21<br>08:34:52
                                </div>
                              </div>
                              <div class="wrapper d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div class="d-flex">
                                  <img class="img-sm rounded-10" src="../assets/images/faces/face1.jpg" alt="profile">
                                  <div class="wrapper ms-3">
                                    <p class="ms-1 mb-1 fw-bold">3 manuals bought by Toyosi</p>
                                    <small class="text-secondary mb-0">&#8358; 4,900.00</small>
                                  </div>
                                </div>
                                <div class="text-muted text-small">
                                  Oct 21<br>08:34:52
                                </div>
                              </div>
                              <div class="wrapper d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div class="d-flex">
                                  <img class="img-sm rounded-10" src="../assets/images/faces/face1.jpg" alt="profile">
                                  <div class="wrapper ms-3">
                                    <p class="ms-1 mb-1 fw-bold">3 manuals bought by Toyosi</p>
                                    <small class="text-secondary mb-0">&#8358; 4,900.00</small>
                                  </div>
                                </div>
                                <div class="text-muted text-small">
                                  Oct 21<br>08:34:52
                                </div>
                              </div>
                              <div class="wrapper d-flex align-items-center justify-content-between py-2 border-bottom">
                                <div class="d-flex">
                                  <img class="img-sm rounded-10" src="../assets/images/faces/face1.jpg" alt="profile">
                                  <div class="wrapper ms-3">
                                    <p class="ms-1 mb-1 fw-bold">3 manuals bought by Samuel</p>
                                    <small class="text-secondary mb-0">&#8358; 4,900.00</small>
                                  </div>
                                </div>
                                <div class="text-muted text-small">
                                  Oct 21<br>08:34:52
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="tab-pane fade hide" id="manuals" role="tabpanel" aria-labelledby="manuals">
                    <div class="row flex-grow">
                      <div class="col-12 grid-margin stretch-card">
                        <div class="card card-rounded shadow-sm">
                          <div class="card-body">
                            <div class="d-sm-flex justify-content-end">
                              <div>
                                <button class="btn btn-primary btn-lg text-white mb-0 me-0"
                                  type="button" data-bs-toggle="modal" data-bs-target="#addManual"><i
                                    class="mdi mdi-book"></i>Add new manual</button>
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
                  <div class="tab-pane fade hide" id="transactions" role="tabpanel" aria-labelledby="transactions">
                    <div class="row flex-grow">
                      <div class="col-12 grid-margin stretch-card">
                        <div class="card card-rounded shadow-sm">
                          <div class="card-body">
                            <div class="table-responsive  mt-1">
                              <table id="transaction_table"
                                class="table table-hover table-striped select-table datatable-opt">
                                <thead>
                                  <tr>
                                    <th class="d-sm-none-2">Transaction Id</th>
                                    <th>Student Details</th>
                                    <th>Amount</th>
                                    <th class="d-sm-none-2">Date & Time</th>
                                    <th class="d-sm-none-2">Status</th>
                                    <th>Action</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <tr>
                                    <td class="d-sm-none-2">
                                      <h6 class="pl-3">#6YU1-2460-9E1Q</h6>
                                    </td>
                                    <td>
                                      <h6>Samuel Akinyemi</h6>
                                      <p>Matric no: <span class="fw-bold">190303003</span></p>
                                    </td>
                                    <td>
                                      <h6 class="text-success">&#8358; 12,000</h6>
                                    </td>
                                    <td class="d-sm-none-2">
                                      <h6>21 October, 2023</h6>
                                      <p class="fw-bold">09:28:09</p>
                                    </td>
                                    <td class="d-sm-none-2">
                                      <div class="badge badge-opacity-success">Successful</div>
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
                </div>

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
                                <label class="form-label" for="price">Unit Place</label>
                              </div>
                            </div>
                          </div>
                          <div class="row">
                            <div class="col-md-6">
                              <div class="form-outline mb-4">
                                <input type="number" name="qty" class="form-control form-control-lg w-100" required="">
                                <label class="form-label" for="qty">Quantity</label>
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
                          <button type="button" class="btn btn-lg btn-light"
                            data-bs-dismiss="modal">Close</button>
                          <button id="manual_submit" type="submit" data-mdb-ripple-duration="0"
                            class="btn btn-lg btn-primary">Submit</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
        <footer class="footer">
          <div class="d-sm-flex justify-content-center justify-content-sm-between">
            <span class="text-muted text-center text-sm-left d-block d-sm-inline-block">Premium <a
                href="https://www.bootstrapdash.com/" target="_blank">Bootstrap admin template</a> from
              BootstrapDash.</span>
            <span class="float-none float-sm-right d-block mt-1 mt-sm-0 text-center">Copyright © 2021. All rights
              reserved.</span>
          </div>
        </footer>
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
  <script src="../assets/js/js/dashboard.js"></script>
  <script src="../assets/js/script.js"></script>

  <script>
    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');
      
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
            url: 'model/user.php',
            data: $('#manual-form').serialize(),
            success: function (data) {
              $('#alertBanner').html(data.message);

              if (data.status == 'success') {
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').removeClass('alert-danger');
                $('#alertBanner').addClass('alert-success');
                // setTimeout(function () {
                //   // $(".main-card").load("views/_vote.php?code=" + election_code + "&voter=" + data.voter);
                // }, 1000);
              } else {
                $('#alertBanner').removeClass('alert-success');
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').addClass('alert-danger');
              }

              // Automatically show and hide the alert after 5 seconds
              $('#alertBanner').fadeIn();

              setTimeout(function () {
                $('#alertBanner').fadeOut();
              }, 5000);

              // AJAX call successful, stop the spinner and update button text
              button.html(originalText);
              button.prop("disabled", false);
            }
          });
        }, 2000); // Simulated AJAX delay of 2 seconds
      });
    });
  </script>
  <!-- End custom js for this page-->
</body>

</html>