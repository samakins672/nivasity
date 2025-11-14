<?php
session_start();
include('model/config.php');
include('model/page_config.php');

$manual_query = mysqli_query($conn, "SELECT * FROM manuals_bought WHERE buyer = $user_id AND school_id = $school_id ORDER BY created_at DESC");

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
                          <h4 class="fw-bold my-3">Course Materials Bought</h4> 
                        </div>
                        <div class="card-body">
                          <!-- order Ticket Table -->
                          <div class="table-responsive  mt-1">
                            <table id="order_table" class="table table-striped table-hover select-table datatable-opt">
                              <thead>
                                <tr>
                                  <th>Trans. ID</th>
                                  <th>Name</th>
                                  <th>Price</th>
                                  <th>Date Bought</th>
                                  <th>Status</th>
                                  <th>Actions</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php
                              while ($manual = mysqli_fetch_array($manual_query)) {
                                $manual_id = $manual['manual_id'];

                                $manuals = mysqli_fetch_array(mysqli_query($conn, "SELECT * FROM manuals WHERE id = $manual_id AND school_id = $school_id"));

                                // Retrieve and format the due date
                                $created_date = date('j M, Y', strtotime($manual['created_at']));
                                $created_time = date('h:i a', strtotime($manual['created_at']));
                                // Retrieve the status
                                $status = $manual['status'];
                                $event_price = number_format($manuals['price']);
                                $event_price = $event_price > 0 ? "₦ $event_price" : 'FREE';
                                ?>
                              <tr>
                                <td>
                                  #<?php echo $manual['ref_id'] ?>
                                </td>
                                <td>
                                  <div class="d-flex ">
                                    <div>
                                      <h6><span class="d-sm-none-2"><?php echo $manuals['title'] ?> -</span> <?php echo $manuals['course_code'] ?></h6>
                                      <p class="d-sm-none-2">ID: <span class="fw-bold"><?php echo $manuals['code'] ?></span></p>
                                    </div>
                                  </div>
                                </td>
                                <td>
                                  <h6 class="text-success fw-bold"><?php echo $event_price ?></h6>
                                </td>
                                <td>
                                  <h6><?php echo $created_date ?></h6>
                                  <p class="fw-bold"><?php echo $created_time ?></p>
                                </td>
                                <td>
                                  <div class="badge <?php echo ($status == 'successful') ? 'bg-success' : 'bg-danger'; ?>"><?php echo $status; ?></div>
                                </td>
                                <td>
                                  <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary js-download-receipt" data-ref="<?php echo htmlspecialchars($manual['ref_id']); ?>" data-kind="manual" data-item-id="<?php echo (int)$manual['manual_id']; ?>" title="Download receipt as PDF">
                                      Download
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-email-receipt" data-ref="<?php echo htmlspecialchars($manual['ref_id']); ?>" data-kind="manual" data-item-id="<?php echo (int)$manual['manual_id']; ?>" title="Email receipt">
                                      Email
                                    </button>
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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

  <script>
    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');
      $(document).on('click', '.js-download-receipt', async function () {
        const $btn = $(this);
        const ref = $btn.data('ref');
        const kind = $btn.data('kind');
        const itemId = $btn.data('item-id');
        const url = `model/receipt.php?action=download&pdf=1&ref=${encodeURIComponent(ref)}&kind=${encodeURIComponent(kind)}&item_id=${encodeURIComponent(itemId)}`;
        const filename = `receipt-${ref}-${kind}-${itemId}.pdf`;
        try {
          $btn.prop('disabled', true).text('Preparing...');
          const resp = await fetch(url, { method: 'GET', credentials: 'same-origin', cache: 'no-store' });
          if (!resp.ok) throw new Error('Failed to load receipt HTML');
          const html = await resp.text();
          if (!html || html.replace(/\s+/g, '').length < 20) throw new Error('Empty receipt content');
          // Render in an offscreen iframe to avoid CSS conflicts
          const iframe = document.createElement('iframe');
          iframe.style.position = 'fixed';
          iframe.style.left = '-9999px';
          iframe.style.top = '0';
          iframe.style.width = '794px';
          iframe.style.height = '1123px';
          document.body.appendChild(iframe);
          const doc = iframe.contentDocument || iframe.contentWindow.document;
          doc.open();
          doc.write(`<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">`
            + `<style>*{box-sizing:border-box;} body{font-family: Arial,Helvetica,sans-serif; color:#333; background:#fff; margin:0;} .pdf-container{width:178mm; margin:0 auto; padding:6mm;} .header{display:flex; align-items:center; margin-bottom:8px;} .header img{height:42px; display:block; max-width:100%;} table{width:100%; border-collapse: collapse; table-layout: fixed;} th,td{font-size:13px; word-wrap:break-word;} th:nth-child(3),td:nth-child(3){width:32mm; text-align:right; white-space:nowrap;} h2,h3{color:#7a3b73; margin:0 0 8px;}</style>`
            + `</head><body><div class="pdf-container"><div class="header"><img crossorigin="anonymous" src="https://funaab.nivasity.com/assets/images/nivasity-main.png" alt="Nivasity"></div><div class="content">${html}</div></div></body></html>`);
          doc.close();
          await new Promise(r => setTimeout(r, 150));
          const opt = {
            margin: [10, 10, 10, 10],
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true, logging: false, allowTaint: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
          };
          await html2pdf().set(opt).from(doc.querySelector('.pdf-container')).save();
          document.body.removeChild(iframe);
        } catch (e) {
          showBanner('Could not generate PDF receipt.', 'danger');
        } finally {
          $btn.prop('disabled', false).text('Download');
        }
      });
      $(document).on('click', '.js-email-receipt', function() {
        var ref = $(this).data('ref');
        var kind = $(this).data('kind');
        var itemId = $(this).data('item-id');
        var $btn = $(this);
        $btn.prop('disabled', true).text('Sending...');
        $.ajax({
          url: 'model/receipt.php',
          method: 'GET',
          data: { action: 'email', ref: ref, kind: kind, item_id: itemId },
          dataType: 'json'
        }).done(function(resp) {
          var msg = (resp && resp.status === 'success') ? 'Receipt sent to your email.' : (resp && resp.message ? resp.message : 'Failed to send receipt.');
          showBanner(msg, (resp && resp.status === 'success') ? 'info' : 'danger');
        }).fail(function() {
          showBanner('An error occurred while sending receipt.', 'danger');
        }).always(function() {
          $btn.prop('disabled', false).text('Email');
        });
      });

      function showBanner(text, type) {
        var $banner = $('#alertBanner');
        $banner.removeClass('alert-info alert-danger alert-success').addClass('alert-' + type);
        $banner.text(text).fadeIn(150);
        setTimeout(function(){ $banner.fadeOut(300); }, 3000);
      }
    });
  </script>
</body>

</html>

