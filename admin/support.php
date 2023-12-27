<?php
session_start();
include('../model/config.php');
include('../model/page_config.php');

if ($_SESSION['nivas_userRole'] == 'student') {
  header('Location: ../store.php');
  exit();
}

?>
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
  <link rel="shortcut icon" href="../favicon.ico" />
  </head>
</head>

<body>
  <div class="container-scroller">
    <!-- partial:partials/_navbar.php -->
    <?php include('../partials/_navbar.php') ?>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_sidebar_admin.php -->
      <?php include('../partials/_sidebar_admin.php') ?>
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
        <?php include('../partials/_footer.php') ?>
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