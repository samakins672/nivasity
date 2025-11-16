<?php
session_start();
include('../model/config.php');
include('../model/page_config.php');

if ($_SESSION['nivas_userRole'] == 'student') {
  header('Location: /');
  exit();
}

$support_query = mysqli_query($conn, "SELECT * FROM support_tickets_v2 WHERE user_id = $user_id ORDER BY `last_message_at` DESC, `created_at` DESC");
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
                                  $subject = substr($subject, 0, 22) . '...';
                                }
                                
                                $created_at = $support['created_at'];

                                // Retrieve and format the opened date
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
                                    <h6><?php echo $subject; ?></h6>
                                  </td>
                                  <td class="d-sm-none-2">
                                    <h6><?php echo $created_date; ?></h6>
                                    <p class="fw-bold"><?php echo $created_time; ?></p>
                                  </td>
                                  <td class="py-3">
                                    <div class="badge badge-opacity-<?php echo ($status == 'open' || $status == 'pending') ? 'warning' : 'success'; ?>">
                                      <?php echo $status; ?>
                                    </div>
                                  </td>
                                  <td class="py-3">
                                    <button
                                      data-code="<?php echo $support['code']; ?>"
                                      data-subject="<?php echo htmlspecialchars($support['subject']); ?>"
                                      data-date="<?php echo $created_date . ' ' . $created_time; ?>"
                                      data-bs-toggle="modal"
                                      data-bs-target="#addSupport"
                                      class="btn btn-primary btn-lg fw-bold mb-0 view-support">
                                      View
                                    </button>
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
                                      <label class="form-label" for="category">Category</label>
                                      <select name="category" id="category" class="form-control form-control-lg w-100" required>
                                        <option value="" disabled selected>Select category</option>
                                        <option value="Payments or Transactions">Payments or Transactions</option>
                                        <option value="Account or Access Issues">Account or Access Issues</option>
                                        <option value="Materials or Events">Materials or Events</option>
                                        <option value="Department Requests">Department Requests</option>
                                        <option value="Technical and Other Issues">Technical and Other Issues</option>
                                      </select>
                                    </div>
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
                                  <div class="mb-3">
                                    <p class="text-muted small mb-1">Ticket reference: #<span class="ticket_id"></span></p>
                                    <p class="text-muted small complaint_time"></p>
                                  </div>
                                  <div class="border rounded p-3 mb-3" style="max-height: 350px; overflow-y: auto;">
                                    <div class="ticket-thread-messages small"></div>
                                  </div>
                                  <hr>
                                  <div id="ticket-reply-container">
                                    <form id="ticket-reply-form" enctype="multipart/form-data">
                                      <input type="hidden" name="ticket_code" id="ticketReplyCode" value="">
                                      <div class="mb-3">
                                        <label class="form-label" for="ticket_reply_message">Reply</label>
                                        <textarea class="form-control w-100 px-3 py-2 h-25" id="ticket_reply_message" name="message" rows="6" required></textarea>
                                      </div>
                                      <div class="mb-3">
                                        <label for="ticket_reply_attachment" class="form-label fw-bold">
                                          Attach file (<span class="reply_attach_ment">no file selected</span>)
                                        </label>
                                        <div>
                                          <input type="file" id="ticket_reply_attachment" name="attachment" class="form-control"
                                            accept=".pdf,.jpeg,.jpg,.png" style="display: none">
                                          <label for="ticket_reply_attachment"
                                            class="btn btn-lg btn-secondary text-light">
                                            <i class="mdi mdi-upload-outline"></i> Upload
                                          </label>
                                        </div>
                                      </div>
                                      <div class="d-flex justify-content-end">
                                        <button type="submit" id="ticket_reply_submit" class="btn btn-lg btn-primary">
                                          Send Reply
                                        </button>
                                      </div>
                                    </form>
                                  </div>
                                  <div id="ticket-reopen-container" class="d-none">
                                    <div class="d-flex justify-content-end">
                                      <button type="button" id="ticket_reopen_btn" class="btn btn-lg btn-outline-primary">
                                        Reopen Ticket
                                      </button>
                                    </div>
                                  </div>
                                </div>
                                <!-- <div class="modal-footer">
                                  <button type="button" class="btn btn-lg btn-light" data-bs-dismiss="modal">Close</button>
                                </div> -->
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
  <script>
    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');

      // Trigger file upload when the icon/button is clicked (new ticket)
      $('#attachment').change(function () {
        var numFiles = $(this)[0].files.length;
        $('.attach_ment').text(numFiles + (numFiles === 1 ? ' file' : ' files') + ' selected');
      });

      // Reset the new-ticket form when the modal is dismissed
      $('#ticketModal').on('hide.bs.modal', function () {
        $('#support-form')[0].reset();
        $('.attach_ment').text('no file selected');
      });

      function formatDateTimeDisplay(raw) {
        if (!raw) {
          return '';
        }
        var value = String(raw).trim();
        var parts = value.split(' ');
        if (parts.length < 2) {
          return value;
        }
        var dateParts = parts[0].split('-');
        var timeParts = parts[1].split(':');
        if (dateParts.length < 3 || timeParts.length < 2) {
          return value;
        }
        var year = parseInt(dateParts[0], 10);
        var monthIndex = parseInt(dateParts[1], 10) - 1;
        var day = parseInt(dateParts[2], 10);
        var hour = parseInt(timeParts[0], 10);
        var minute = parseInt(timeParts[1], 10);
        if (isNaN(year) || isNaN(monthIndex) || isNaN(day) || isNaN(hour) || isNaN(minute)) {
          return value;
        }
        var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        var ampm = hour >= 12 ? 'pm' : 'am';
        var displayHour = hour % 12;
        if (displayHour === 0) {
          displayHour = 12;
        }
        var minuteStr = minute < 10 ? '0' + minute : String(minute);
        return months[monthIndex] + ' ' + day + ', ' + year + ' ' + displayHour + ':' + minuteStr + ' ' + ampm;
      }

      function renderMessages(messages) {
        var container = $('.ticket-thread-messages');
        container.empty();

        if (!messages || messages.length === 0) {
          container.append('<p class="text-muted mb-0">No messages yet.</p>');
          return;
        }

        messages.forEach(function (msg) {
          var isUser = (msg.senderType === 'user');
          var alignmentClass = isUser ? 'text-end' : 'text-start';
          var bubbleClass = isUser ? 'bg-primary text-white' : 'bg-light';
          var label = isUser ? 'You' : 'Support';

          var safeMessage = $('<div/>').text(msg.message || '').html();
          safeMessage = safeMessage.replace(/\n/g, '<br>');

          var html = '<div class="mb-3 ' + alignmentClass + '">';
          html += '<div class="small text-muted mb-1">' + label + ' &bull; ' + formatDateTimeDisplay(msg.createdAt) + '</div>';
          html += '<div class="d-inline-block p-2 rounded ' + bubbleClass + '">' + safeMessage;

          if (msg.attachments && msg.attachments.length > 0) {
            html += '<div class="mt-2">';
            msg.attachments.forEach(function (att) {
              if (!att.filePath || !att.fileName) {
                return;
              }
              var fileNameSafe = $('<div/>').text(att.fileName).html();
              var href = '../' + att.filePath.replace(/^\/+/, '');
              html += '<a href="' + href + '" target="_blank" class="d-block text-decoration-underline">' + fileNameSafe + '</a>';
            });
            html += '</div>';
          }

          html += '</div></div>';
          container.append(html);
        });
      }

      function loadTicketMessages(code) {
        var container = $('.ticket-thread-messages');
        container.html('<p class="text-muted mb-0">Loading conversation...</p>');

        $.ajax({
          type: 'GET',
          url: '../model/support_messages.php',
          data: { ticket_code: code },
          success: function (data) {
            if (data && data.status === 'success' && data.data) {
              renderMessages(data.data.messages || []);

              var ticket = data.data.ticket || {};
              var status = (ticket.status || '').toLowerCase();
              var replyContainer = $('#ticket-reply-container');
              var reopenContainer = $('#ticket-reopen-container');

              if (status === 'closed') {
                replyContainer.hide();
                reopenContainer.removeClass('d-none').show();
              } else {
                reopenContainer.hide();
                replyContainer.show();
              }
            } else {
              var message = (data && data.message) ? data.message : 'Could not load messages.';
              container.html('<p class="text-danger mb-0">' + message + '</p>');
            }
          },
          error: function () {
            container.html('<p class="text-danger mb-0">Could not load messages.</p>');
          }
        });
      }

      // Handle click event of View button
      $('.view-support').on('click', function () {
        var code = $(this).data('code');
        var subject = $(this).data('subject');
        var date = $(this).data('date');

        // Reset reply form
        $('#ticket-reply-form')[0].reset();
        $('.reply_attach_ment').text('no file selected');

        // Set header values in the support modal
        $('.subject').html(subject);
        $('.ticket_id').html(code);
        $('.complaint_time').html(date);
        $('#ticketReplyCode').val(code);

        $('#ticket-reply-container').show();
        $('#ticket-reopen-container').hide();

        loadTicketMessages(code);
      });

      // Track attachment selection on reply form
      $('#ticket_reply_attachment').on('change', function () {
        var numFiles = $(this)[0].files.length;
        $('.reply_attach_ment').text(
          numFiles === 0 ? 'no file selected' : (numFiles + (numFiles === 1 ? ' file selected' : ' files selected'))
        );
      });

      // Use AJAX to submit the new-ticket support form
      $('#support-form').submit(function (event) {
        event.preventDefault();

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
              $('#alertBanner').removeClass('alert-info alert-danger').addClass('alert-success');

              setTimeout(function () {
                location.reload();
              }, 2000);
            } else {
              $('#alertBanner').removeClass('alert-success alert-info').addClass('alert-danger');
            }

            $('#alertBanner').fadeIn();

            setTimeout(function () {
              $('#alertBanner').fadeOut();
            }, 5000);

            button.html(originalText);
            button.prop('disabled', false);
          },
          error: function () {
            $('#alertBanner').html('An error occurred while creating the ticket.');
            $('#alertBanner').removeClass('alert-success alert-info').addClass('alert-danger').fadeIn();
            setTimeout(function () {
              $('#alertBanner').fadeOut();
            }, 5000);

            button.html(originalText);
            button.prop('disabled', false);
          }
        });
      });

      // Submit reply within an existing ticket
      $('#ticket-reply-form').submit(function (event) {
        event.preventDefault();

        var button = $('#ticket_reply_submit');
        var originalText = button.html();

        button.html(originalText + '  <div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        var formData = new FormData($('#ticket-reply-form')[0]);

        $.ajax({
          type: 'POST',
          url: '../model/support_reply.php',
          data: formData,
          contentType: false,
          processData: false,
          success: function (data) {
            $('#alertBanner').html(data.message);

            if (data.status == 'success') {
              $('#alertBanner').removeClass('alert-info alert-danger').addClass('alert-success');
              // Reload conversation without closing modal
              var code = $('#ticketReplyCode').val();
              loadTicketMessages(code);
              $('#ticket-reply-form')[0].reset();
              $('.reply_attach_ment').text('no file selected');
            } else {
              $('#alertBanner').removeClass('alert-success alert-info').addClass('alert-danger');
            }

            $('#alertBanner').fadeIn();

            setTimeout(function () {
              $('#alertBanner').fadeOut();
            }, 5000);

            button.html(originalText);
            button.prop('disabled', false);
          },
          error: function () {
            $('#alertBanner').html('An error occurred while sending your reply.');
            $('#alertBanner').removeClass('alert-success alert-info').addClass('alert-danger').fadeIn();
            setTimeout(function () {
              $('#alertBanner').fadeOut();
            }, 5000);

            button.html(originalText);
            button.prop('disabled', false);
          }
        });
      });

      // Reopen a closed ticket
      $('#ticket_reopen_btn').on('click', function () {
        var code = $('#ticketReplyCode').val();
        if (!code) {
          return;
        }

        var button = $('#ticket_reopen_btn');
        var originalText = button.html();

        button.html(originalText + '  <div class="spinner-border text-primary" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        $.ajax({
          type: 'POST',
          url: '../model/support_reopen.php',
          data: { ticket_code: code },
          success: function (data) {
            $('#alertBanner').html(data.message);

            if (data.status == 'success') {
              $('#alertBanner').removeClass('alert-info alert-danger').addClass('alert-success');
              loadTicketMessages(code);
            } else {
              $('#alertBanner').removeClass('alert-success alert-info').addClass('alert-danger');
            }

            $('#alertBanner').fadeIn();

            setTimeout(function () {
              $('#alertBanner').fadeOut();
            }, 5000);

            button.html(originalText);
            button.prop('disabled', false);
          },
          error: function () {
            $('#alertBanner').html('An error occurred while reopening the ticket.');
            $('#alertBanner').removeClass('alert-success alert-info').addClass('alert-danger').fadeIn();
            setTimeout(function () {
              $('#alertBanner').fadeOut();
            }, 5000);

            button.html(originalText);
            button.prop('disabled', false);
          }
        });
      });
    });
  </script>
</body>

</html>
