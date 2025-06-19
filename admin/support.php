<?php
session_start();
include('../model/config.php');
include('../model/page_config.php');

if ($_SESSION['nivas_userRole'] == 'student') {
  header('Location: /');
  exit();
}

$support_query = mysqli_query($conn, "SELECT * FROM support_tickets WHERE user_id = $user_id ORDER BY `created_at` DESC");
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
            <div class="col-sm-12 px-2">
              <div class="home-tab">
                <div class="tab-content tab-content-basic py-0">
                  <div class="tab-pane fade show active" id="support" role="tabpanel" aria-labelledby="support">
                    <div class="row flex-grow">
                      <div class="col-12 card card-rounded shadow-sm">
                        <div class="card-header">
                          <h4 class="fw-bold my-3">Support Tickets</h4> 
                        </div>
                        <div class="card-body">
                          <!-- Button to open ticket modal -->
                          <button type="button" class="btn btn-primary btn-lg mb-3"
                            data-bs-toggle="modal" data-bs-target="#ticketModal">
                            Create New Ticket
                          </button>

                          <!-- Support Ticket Table -->
                          <div class="table-responsive  mt-1">
                            <table id="support_table" class="table table-striped table-hover select-table datatable-opt">
                              <thead>
                                <tr>
                                  <th class="d-sm-none-2">Ticket ID</th>
                                  <th>Subject</th>
                                  <th class="d-sm-none-2">Date Opened</th>
                                  <th>Status</th>
                                  <th>Actions</th>
                                </tr>
                              </thead>
                              <tbody>
                              <?php
                              while ($support = mysqli_fetch_array($support_query)) {
                                $subject = $support['subject'];

                                if (strlen($subject) > 22) {
                                  // If yes, truncate the text
                                  $subject = substr($subject, 0, 22) . '...';
                                }
                                
                                $created_at = $support['created_at'];

                                // Retrieve and format the due date
                                $created_date = date('M j, Y', strtotime($created_at));
                                $created_time = date('h:i a', strtotime($created_at));
                                // Retrieve the status
                                $status = $support['status'];
                                ?>
                                <tr>
                                  <td class="py-3 d-sm-none-2">
                                    <h6>#<?php echo $support['code'] ?></h6>
                                  </td>
                                  <td class="py-3">
                                    <h6><?php echo $subject ?> </h6>
                                  </td>
                                  <td class="d-sm-none-2">
                                    <h6><?php echo $created_date ?></h6>
                                    <p class="fw-bold"><?php echo $created_time ?></p>
                                  </td>
                                  <td class="py-3">
                                    <div class="badge badge-opacity-<?php echo ($status == 'open') ? 'warning' : 'success'; ?>"><?php echo $status ?></div>
                                  </td>
                                  <td class="py-3">
                                    <button data-code="<?php echo $support['code'] ?>" data-subject="<?php echo $subject ?>"
                                      data-message="<?php echo $support['message'] ?>" data-response="<?php echo $support['response'] ?>"
                                      data-response_time="<?php echo $support['response_time'] ?>"
                                      data-date="<?php echo $created_date ?>" data-bs-toggle="modal" data-bs-target="#addSupport"
                                      class="btn btn-primary btn-lg fw-bold mb-0 view-support">View</button>
                                  </td>
                                </tr>
                                <?php } ?>
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
                                  <input type="hidden" name="support_id" value="0">
                                  <div class="modal-body">
                                    <div class="form-outline mb-4">
                                      <input type="text" name="subject" class="form-control form-control-lg w-100" required>
                                      <label class="form-label" for="subject">Subject</label>
                                    </div>
                                    <div class="wysi-editor mb-4">
                                      <label class="form-label" for="message">Message</label>
                                      <textarea class="form-control w-100 px-3 py-2" name="message" required></textarea>
                                    </div>

                                    <div>
                                      <label for="attachment" class="form-label fw-bold">Attach files (<span
                                          class="attach_ment">no file selected</span>)</label>
                                      <div>
                                        <input type="file" id="attachment" name="attachment" class="form-control"
                                          accept=".pdf,.jpeg,.jpg,.png" style="display: none">
                                        <label for="attachment"
                                          class="btn btn-lg btn-secondary text-light">
                                          <i class="mdi mdi-upload-outline"></i> Upload
                                        </label>
                                      </div>
                                    </div>
                                  </div>
                                  <div class="modal-footer">
                                    <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Close</button>
                                    <button id="support_submit" type="submit" class="btn btn-lg btn-primary">Submit</button>
                                  </div>
                                </form>
                              </div>
                            </div>
                          </div>
                                    
                          <!-- View Support Manual Modal -->
                          <div class="modal fade" id="addSupport" tabindex="-1" role="dialog" aria-labelledby="supportLabel"
                            aria-hidden="true">
                            <div class="modal-dialog" role="document">
                              <div class="modal-content">
                                <div class="modal-header">
                                  <h4 class="modal-title fw-bold" id="supportLabel"><span class="subject"></span> - #<span class="ticket_id"></span></h4>
                                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                  </button>
                                </div>
                                <div class="modal-body">
                                  <div>
                                    <h4>Response:</h4>
                                    <p class="lh-base fw-bold p-3 bg-secondary text-light rounded response_text">No response yet!</p>
                                    <p class="text-muted response_time"></p><br>
                                    <h4>Complain:</h4>
                                    <p class="lh-base bg-light p-3 rounded complaint_text"></p>
                                    <p class="text-muted complaint_time"> Date: 23 Dec, 2023</p><br>
                                  </div>
                                </div>
                                <div class="modal-footer">
                                  <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Close</button>
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
        </div>
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
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
  <script src="../assets/js/js/dashboard.js"></script>
  <script src="../assets/js/script.js"></script>
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

      // Handle click event of View button
      $('.view-support').on('click', function () {
        // Get the manual details from the data- attributes
        var code = $(this).data('code');
        var subject = $(this).data('subject');
        var message = $(this).data('message');        
        // retain rending format
        message = message.replace(/\n/g, '<br>');
        var date = $(this).data('date');
        var response = $(this).data('response');
        var response_time = $(this).data('response_time');
        
        if (response !== '') {
          $('.response_text').html(response);
          $('.response_time').html(response_time);
        }

        // Set the values in the support modal
        $('.subject').html(subject);
        $('.ticket_id').html(code);
        $('.complaint_text').html(message);
        $('.complaint_time').html(date);
      });

      // Use AJAX to submit the support form
      $('#support-form').submit(function (event) {
        event.preventDefault(); // Prevent the default form submission

        var button = $('#support_submit');
        var originalText = button.html();

        button.html(originalText + '  <div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        var formData = new FormData($('#support-form')[0]);

        $.ajax({
            type: 'POST',
            url: '../model/support.php',
            data: formData,
            contentType: false,
            processData: false,
            success: function (data) {
              $('#alertBanner').html(data.message);

              if (data.status == 'success') {
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').removeClass('alert-danger');
                $('#alertBanner').addClass('alert-success');

                setTimeout(function () {
                  location.reload();
                }, 2000);
              } else {
                $('#alertBanner').removeClass('alert-success');
                $('#alertBanner').removeClass('alert-info');
                $('#alertBanner').addClass('alert-danger');
              }

              $('#alertBanner').fadeIn();

              setTimeout(function () {
                  $('#alertBanner').fadeOut();
              }, 5000);

              button.html(originalText);
              button.prop("disabled", false);
            }
        });
      });
    });
  </script>
</body>

</html>