<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Support - Nivasity</title>
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
</head>

<body>
  <div class="container-scroller">
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
            <a class="nav-link" href="signin.html?logout">
              <i class="menu-icon mdi mdi-power"></i>
              <span class="menu-title">Sign Out</span>
            </a>
          </li>
        </ul>
      </nav>
      <!-- partial -->
      <div class="main-panel">
  
        <div class="content-wrapper py-0">
          <div class="row">
            <div class="col-sm-12">
              <div class="home-tab">
                <div class="tab-content tab-content-basic py-0">
                  <div class="tab-pane fade show active" id="support" role="tabpanel" aria-labelledby="support">
                    <div class="row flex-grow">
                      <div class="col-12 card card-rounded shadow-sm">
                        <div class="card-body">
                          <!-- Button to open ticket modal -->
                          <button type="button" class="btn btn-primary btn-lg mb-3"
                            data-bs-toggle="modal" data-bs-target="#ticketModal">
                            Create New Ticket
                          </button>

                          <!-- Support Ticket Table -->
                          <div class="table-responsive  mt-1">
                            <table class="table table-striped table-hover select-table datatable-opt">
                              <thead>
                                <tr>
                                  <th class="d-sm-none-2">Ticket ID</th>
                                  <th>Subject</th>
                                  <th class="d-sm-none-2">Last Updated</th>
                                  <th>Status</th>
                                  <th>Actions</th>
                                </tr>
                              </thead>
                              <tbody>
                                <!-- Table rows for existing tickets will go here -->
                                <!-- Example row -->
                                <tr>
                                  <td class="py-3 d-sm-none-2">
                                    <h6>#2460-9E1Q-2460-9E1Q</h6>
                                  </td>
                                  <td class="py-3">
                                    <h6>Issue with login </h6>
                                  </td>
                                  <td class="d-sm-none-2">
                                    <h6>21 October, 2023</h6>
                                    <p class="fw-bold">09:28:09</p>
                                  </td>
                                  <td class="py-3">
                                    <div class="badge badge-opacity-success">Open</div>
                                  </td>
                                  <td class="py-3">
                                    <button data-mdb-ripple-duration="0"
                                      class="btn btn-primary btn-lg fw-bold mb-0">View</button>
                                  </td>
                                </tr>
                                <!-- Example row ends -->
                              </tbody>
                            </table>
                          </div>

                          <!-- Modal for creating new ticket -->
                          <div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel"
                            aria-hidden="true">
                            <div class="modal-dialog">
                              <div class="modal-content">
                                <div class="modal-header">
                                  <h5 class="modal-title" id="ticketModalLabel">New Support Ticket</h5>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                                </div>
                                <form id="support-form">
                                  <div class="modal-body">
                                    <div class="form-outline mb-4">
                                      <input type="text" name="subject" class="form-control form-control-lg w-100"
                                        required>
                                      <label class="form-label" for="subject">Subject</label>
                                    </div>
                                    <div class="wysi-editor mb-4">
                                      <label class="form-label" for="message">Message</label>
                                      <textarea class="form-control w-100 px-3 py-2" id="message"
                                        required></textarea>
                                    </div>

                                    <div>
                                      <label for="attachment" class="form-label fw-bold">Attach files (<span
                                          class="attach_ment">no file selected</span>)</label>
                                      <div>
                                        <input type="file" id="attachment" class="form-control"
                                          accept=".pdf,.jpeg,.jpg,.png" multiple style="display: none">
                                        <label for="attachment"
                                          class="btn btn-lg btn-secondary text-light">
                                          <i class="mdi mdi-upload-outline"></i> Upload
                                        </label>
                                      </div>
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-lg btn-light"
                                      data-bs-dismiss="modal">Close</button>
                                    <button id="support_submit" type="submit" data-mdb-ripple-duration="0"
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
      // Trigger file upload when the icon/button is clicked
      $('#attachment').change(function () {
        // Get the number of files selected and display the count
        var numFiles = $(this)[0].files.length;
        $('.attach_ment').text(numFiles + (numFiles === 1 ? ' file' : ' files') + ' selected');
      });

      // Reset the form when the modal is dismissed
      $('#ticketModal').on('hide.bs.modal', function () {
        $('#support-form')[0].reset(); // Reset the form
        $('.attach_ment').text('no file selected'); // Clear the file count display
      });
    });
  </script>
</body>

</html>