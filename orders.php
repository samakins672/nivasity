<?php
session_start();
include('model/config.php');
include('model/page_config.php');
require_once 'model/material_change_service.php';
require_once 'model/material_copy_status.php';
require_once 'model/bulk_material_payment_service.php';

$manual_query = mysqli_query($conn, "SELECT * FROM manuals_bought WHERE buyer = $user_id AND school_id = $school_id ORDER BY created_at DESC");
$bulk_payment_rows = [];
if (bulk_material_payment_has_table($conn, 'manual_bulk_payment_batches') && bulk_material_payment_has_table($conn, 'manual_bulk_payment_students')) {
  $bulk_payment_query = mysqli_query(
    $conn,
    "SELECT
        b.id,
        b.ref_id,
        b.manual_id,
        b.student_count,
        b.subtotal,
        b.fee_amount,
        b.total_amount,
        b.payment_status,
        COALESCE(b.paid_at, b.created_at) AS purchased_at,
        m.title,
        m.course_code,
        COUNT(s.id) AS listed_students
     FROM manual_bulk_payment_batches AS b
     INNER JOIN manuals AS m ON m.id = b.manual_id
     LEFT JOIN manual_bulk_payment_students AS s ON s.batch_id = b.id
     WHERE b.payer_user_id = {$user_id}
       AND b.school_id = {$school_id}
       AND b.payment_status = 'successful'
     GROUP BY
       b.id, b.ref_id, b.manual_id, b.student_count, b.subtotal, b.fee_amount, b.total_amount,
       b.payment_status, purchased_at, m.title, m.course_code
     ORDER BY purchased_at DESC, b.id DESC"
  );
  if ($bulk_payment_query) {
    while ($bulk_row = mysqli_fetch_assoc($bulk_payment_query)) {
      $bulk_payment_rows[] = $bulk_row;
    }
  }
}
$manuals_bought_has_id = material_change_has_column($conn, 'manuals_bought', 'id');
$manuals_bought_has_grant_status = material_change_has_column($conn, 'manuals_bought', 'grant_status');
$manuals_bought_has_export_id = material_change_has_column($conn, 'manuals_bought', 'export_id');
$manuals_bought_has_copy_status = material_copy_has_column($conn, 'manuals_bought', 'copy_status');

$changed_bought_ids = [];
if ($manuals_bought_has_id) {
  material_change_ensure_schema($conn);
  $manual_change_logs_query = mysqli_query($conn, "SELECT manuals_bought_id FROM manual_change_logs WHERE buyer_id = " . (int) $user_id . " AND manuals_bought_id IS NOT NULL");
  if ($manual_change_logs_query) {
    while ($change_log = mysqli_fetch_assoc($manual_change_logs_query)) {
      $changed_bought_ids[(int) ($change_log['manuals_bought_id'] ?? 0)] = true;
    }
  }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Orders - Nivasity</title>

  <?php include('partials/_head.php') ?>
  <style>
    @media (max-width: 767.98px) {
      .order-mobile-hidden {
        display: none;
      }
    }

    .change-material-meta {
      background: #f7f8fb;
      border: 1px solid #e7ebf3;
      border-radius: 0.75rem;
      padding: 0.85rem 1rem;
    }

    .change-material-hint {
      font-size: 0.85rem;
      color: #6c7383;
    }
  </style>
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
                                  <th class="order-mobile-hidden">Trans. ID</th>
                                  <th>Name</th>
                                  <th>Price</th>
                                  <th>Date Bought</th>
                                  <th class="order-mobile-hidden">Status</th>
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
                                $is_within_change_window = material_change_is_within_window((string) ($manual['created_at'] ?? ''), 72);
                                $is_after_lost_wait_period = material_copy_is_after_lost_wait_period((string) ($manual['created_at'] ?? ''), 48);
                                $is_lost = material_copy_is_lost_row($manual);
                                $is_granted = ($manuals_bought_has_grant_status && material_change_boolish_is_true($manual['grant_status'] ?? '0'))
                                  || ($manuals_bought_has_export_id && (int) ($manual['export_id'] ?? 0) > 0);
                                $was_changed = $manuals_bought_has_id && isset($changed_bought_ids[(int) ($manual['id'] ?? 0)]);
                                $can_change_material = $is_within_change_window && strtolower((string) $status) === 'successful' && !$is_lost && !$is_granted && !$was_changed;
                                $can_mark_lost = $manuals_bought_has_id && $manuals_bought_has_copy_status && strtolower((string) $status) === 'successful' && !$is_lost && $is_after_lost_wait_period;
                                $change_material_reason = '';
                                if ($is_lost) {
                                  $change_material_reason = 'This material copy has already been marked as lost. Buy another copy from the store when needed.';
                                } elseif (!$is_within_change_window) {
                                  $change_material_reason = 'Material change is only available within 72 hours of purchase.';
                                } elseif (strtolower((string) $status) !== 'successful') {
                                  $change_material_reason = 'Only successful purchases can be changed.';
                                } elseif ($is_granted) {
                                  $change_material_reason = 'Granted materials cannot be changed.';
                                } elseif ($was_changed) {
                                  $change_material_reason = 'This purchase has already been changed once.';
                                }
                                ?>
                              <tr>
                                <td class="order-mobile-hidden">
                                  #<?php echo $manual['ref_id'] ?>
                                </td>
                                <td>
                                  <div class="d-flex ">
                                    <div>
                                      <h6 class="order-mobile-hidden"><span class="d-sm-none-2"><?php echo $manuals['title'] ?> -</span> <?php echo $manuals['course_code'] ?></h6>
                                      <h6 class="d-md-none mb-1"><?php echo $manuals['code'] ?></h6>
                                      <p class="order-mobile-hidden d-sm-none-2">ID: <span class="fw-bold"><?php echo $manuals['code'] ?></span></p>
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
                                <td class="order-mobile-hidden">
                                  <div class="d-flex flex-wrap gap-2">
                                    <div class="badge <?php echo ($status == 'successful') ? 'bg-success' : 'bg-danger'; ?>"><?php echo $status; ?></div>
                                    <?php if ($is_lost): ?>
                                      <div class="badge bg-warning text-dark">lost</div>
                                    <?php endif; ?>
                                  </div>
                                </td>
                                <td>
                                  <div class="d-flex flex-wrap gap-2">
                                    <a href="model/receipt.php?action=download&format=pdf&ref=<?php echo urlencode($manual['ref_id']); ?>&kind=manual&item_id=<?php echo (int)$manual['manual_id']; ?>" class="btn btn-sm btn-outline-primary" title="Download receipt as PDF">
                                      Download
                                    </a>
                                    <?php if ($is_within_change_window): ?>
                                      <button
                                        type="button"
                                        class="btn btn-sm btn-outline-dark js-open-material-change"
                                        data-old-manual-id="<?php echo (int) $manual['manual_id']; ?>"
                                        data-ref="<?php echo htmlspecialchars($manual['ref_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-item-title="<?php echo htmlspecialchars((string) ($manuals['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-course-code="<?php echo htmlspecialchars((string) ($manuals['course_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-price="<?php echo htmlspecialchars((string) ($manuals['price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
                                        <?php echo $can_change_material ? '' : 'disabled'; ?>
                                        title="<?php echo htmlspecialchars($can_change_material ? 'Choose another material with the same price.' : $change_material_reason, ENT_QUOTES, 'UTF-8'); ?>">
                                        Change Material
                                      </button>
                                    <?php endif; ?>
                                    <?php if ($can_mark_lost): ?>
                                      <button
                                        type="button"
                                        class="btn btn-sm btn-outline-warning js-mark-material-lost"
                                        data-bought-id="<?php echo (int) ($manual['id'] ?? 0); ?>"
                                        data-item-title="<?php echo htmlspecialchars((string) ($manuals['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        data-course-code="<?php echo htmlspecialchars((string) ($manuals['course_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        Mark Lost
                                      </button>
                                    <?php endif; ?>
                                  </div>
                                  <?php if (!$can_change_material && $change_material_reason !== ''): ?>
                                    <small class="text-muted d-block mt-2"><?php echo htmlspecialchars($change_material_reason, ENT_QUOTES, 'UTF-8'); ?></small>
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
                    <div class="row flex-grow mt-4">
                      <div class="col-12 card card-rounded shadow-sm px-2">
                        <div class="card-header">
                          <h4 class="fw-bold my-3">Bulk Payments Made</h4>
                        </div>
                        <div class="card-body">
                          <div class="table-responsive mt-1">
                            <table id="bulk_order_table" class="table table-striped table-hover select-table datatable-opt">
                              <thead>
                                <tr>
                                  <th class="order-mobile-hidden">Trans. ID</th>
                                  <th>Material</th>
                                  <th>Students</th>
                                  <th>Amount</th>
                                  <th>Date Paid</th>
                                  <th class="order-mobile-hidden">Status</th>
                                  <th>Actions</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($bulk_payment_rows as $bulk_payment): ?>
                                  <?php
                                    $bulk_ref = (string) ($bulk_payment['ref_id'] ?? '');
                                    $bulk_date_raw = (string) ($bulk_payment['purchased_at'] ?? '');
                                    $bulk_date = $bulk_date_raw !== '' ? date('j M, Y', strtotime($bulk_date_raw)) : '-';
                                    $bulk_time = $bulk_date_raw !== '' ? date('h:i a', strtotime($bulk_date_raw)) : '-';
                                    $bulk_status = (string) ($bulk_payment['payment_status'] ?? 'successful');
                                    $bulk_total = (int) ($bulk_payment['total_amount'] ?? 0);
                                    $bulk_total_label = $bulk_total > 0 ? '₦ ' . number_format($bulk_total) : 'FREE';
                                    $bulk_student_count = max((int) ($bulk_payment['student_count'] ?? 0), (int) ($bulk_payment['listed_students'] ?? 0));
                                  ?>
                                  <tr>
                                    <td class="order-mobile-hidden">#<?php echo htmlspecialchars($bulk_ref, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                      <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars((string) ($bulk_payment['title'] ?? 'Bulk material payment'), ENT_QUOTES, 'UTF-8'); ?></h6>
                                        <p class="mb-0 text-muted"><?php echo htmlspecialchars((string) ($bulk_payment['course_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                                      </div>
                                    </td>
                                    <td>
                                      <h6 class="mb-0"><?php echo number_format($bulk_student_count); ?></h6>
                                      <p class="mb-0 text-muted small">student<?php echo $bulk_student_count === 1 ? '' : 's'; ?></p>
                                    </td>
                                    <td><h6 class="text-success fw-bold mb-0"><?php echo $bulk_total_label; ?></h6></td>
                                    <td>
                                      <h6><?php echo htmlspecialchars($bulk_date, ENT_QUOTES, 'UTF-8'); ?></h6>
                                      <p class="fw-bold"><?php echo htmlspecialchars($bulk_time, ENT_QUOTES, 'UTF-8'); ?></p>
                                    </td>
                                    <td class="order-mobile-hidden">
                                      <div class="badge <?php echo strtolower($bulk_status) === 'successful' ? 'bg-success' : 'bg-danger'; ?>"><?php echo htmlspecialchars($bulk_status, ENT_QUOTES, 'UTF-8'); ?></div>
                                    </td>
                                    <td>
                                      <a href="model/receipt.php?action=download&format=pdf&ref=<?php echo urlencode($bulk_ref); ?>" class="btn btn-sm btn-outline-primary" title="Download bulk payment receipt as PDF">
                                        Download
                                      </a>
                                    </td>
                                  </tr>
                                <?php endforeach; ?>
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
          <?php include('partials/_bulk_payment_modal.php') ?>
        <!-- partial -->
      </div>
      <!-- Bootstrap alert container -->
      <div id="alertBanner"
        class="alert alert-info text-center fw-bold alert-dismissible end-2 top-2 fade show position-fixed w-auto p-2 px-4"
        role="alert" style="z-index: 5000; display: none;">
        An error occurred during the AJAX request.
      </div>
      <div class="modal fade" id="changeMaterialModal" tabindex="-1" aria-labelledby="changeMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow">
            <form id="changeMaterialForm">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title fw-bold" id="changeMaterialModalLabel">Change Material</h5>
                  <p class="mb-0 text-muted small">Replacement price must match the original payment, and granted materials cannot be changed.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="old_manual_id" id="changeMaterialOldManualId">
                <input type="hidden" name="ref_id" id="changeMaterialRefId">
                <div id="changeMaterialFeedback" class="alert d-none" role="alert"></div>
                <div class="change-material-meta mb-3">
                  <div class="fw-semibold" id="changeMaterialCurrentTitle">Loading purchase details...</div>
                  <div class="text-muted small" id="changeMaterialCurrentMeta">Please wait while replacement materials are loaded.</div>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold" for="changeMaterialSelect">Choose replacement material</label>
                  <select class="form-select" id="changeMaterialSelect" name="new_manual_id" disabled>
                    <option value="">Loading eligible materials...</option>
                  </select>
                </div>
                <div class="change-material-hint">
                  This action can only be used once per purchased material.
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark" id="changeMaterialSubmit" disabled>Change Material</button>
              </div>
            </form>
          </div>
        </div>
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
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.1/mdb.min.js"></script>
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
  <script src="assets/js/script.js"></script>

  <script>
    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');
      var changeMaterialModalElement = document.getElementById('changeMaterialModal');
      var changeMaterialModal = changeMaterialModalElement && window.bootstrap ? new bootstrap.Modal(changeMaterialModalElement) : null;

      function showBanner(text, type) {
        var $banner = $('#alertBanner');
        $banner.removeClass('alert-info alert-danger alert-success').addClass('alert-' + type);
        $banner.text(text).fadeIn(150);
        setTimeout(function(){ $banner.fadeOut(300); }, 3000);
      }

      var orderUrlParams = new URLSearchParams(window.location.search);
      if (orderUrlParams.get('lost_material_guide') === '1') {
        orderUrlParams.delete('lost_material_guide');
        if (window.history && typeof window.history.replaceState === 'function') {
          var nextOrderQuery = orderUrlParams.toString();
          var nextOrderUrl = window.location.pathname + (nextOrderQuery ? '?' + nextOrderQuery : '') + window.location.hash;
          window.history.replaceState({}, document.title, nextOrderUrl);
        }

        if ($('.js-mark-material-lost').length) {
          showBanner('Find the affected material in Order History, click Mark Lost, then return to Store to buy another copy.', 'info');
        } else {
          showBanner('Lost-material replacement starts here. If you do not yet see a Mark Lost button, the purchase may still be within the 48-hour wait window.', 'info');
        }

        var orderTable = document.getElementById('order_table');
        if (orderTable && typeof orderTable.scrollIntoView === 'function') {
          orderTable.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      }

      function setChangeMaterialFeedback(type, text) {
        var $feedback = $('#changeMaterialFeedback');
        if (!text) {
          $feedback.addClass('d-none').removeClass('alert-info alert-danger alert-success').text('');
          return;
        }

        $feedback.removeClass('d-none alert-info alert-danger alert-success').addClass('alert-' + type).text(text);
      }

      function resetChangeMaterialModal() {
        $('#changeMaterialOldManualId').val('');
        $('#changeMaterialRefId').val('');
        $('#changeMaterialCurrentTitle').text('Loading purchase details...');
        $('#changeMaterialCurrentMeta').text('Please wait while replacement materials are loaded.');
        $('#changeMaterialSelect').html('<option value="">Loading eligible materials...</option>').prop('disabled', true);
        $('#changeMaterialSubmit').prop('disabled', true).text('Change Material');
        setChangeMaterialFeedback('', '');
      }

      function populateChangeMaterialCandidates(order, candidates) {
        var currentTitle = order && order.title ? order.title : 'Selected material';
        var currentCode = order && order.course_code ? ' - ' + order.course_code : '';
        var currentRef = order && order.ref_id ? '#' + order.ref_id : '';
        var currentPrice = order && order.price ? Number(order.price).toLocaleString() : '0';
        $('#changeMaterialCurrentTitle').text(currentTitle + currentCode);
        $('#changeMaterialCurrentMeta').text('Reference ' + currentRef + ' • Paid ₦ ' + currentPrice);

        var $select = $('#changeMaterialSelect');
        if (!candidates || !candidates.length) {
          $select.html('<option value="">No eligible materials available right now</option>').prop('disabled', true);
          $('#changeMaterialSubmit').prop('disabled', true);
          setChangeMaterialFeedback('info', 'No replacement material is currently available with the same price for your account.');
          return;
        }

        var options = ['<option value="">Select a replacement material</option>'];
        candidates.forEach(function(candidate) {
          var dept = candidate.dept_name ? ' • ' + candidate.dept_name : '';
          options.push(
            '<option value="' + candidate.id + '">' +
            candidate.title + (candidate.course_code ? ' - ' + candidate.course_code : '') +
            ' • ₦ ' + Number(candidate.price).toLocaleString() +
            dept +
            '</option>'
          );
        });

        $select.html(options.join('')).prop('disabled', false);
      }

      $(document).on('click', '.js-open-material-change', function() {
        var oldManualId = $(this).data('old-manual-id');
        var refId = $(this).data('ref');

        resetChangeMaterialModal();
        $('#changeMaterialOldManualId').val(oldManualId);
        $('#changeMaterialRefId').val(refId);

        if (changeMaterialModal) {
          changeMaterialModal.show();
        }

        $.ajax({
          url: 'model/change-material.php',
          method: 'GET',
          data: { old_manual_id: oldManualId, ref_id: refId },
          dataType: 'json'
        }).done(function(resp) {
          if (!resp || resp.status !== 'success' || !resp.data) {
            var message = resp && resp.message ? resp.message : 'Unable to load replacement materials.';
            setChangeMaterialFeedback('danger', message);
            $('#changeMaterialCurrentTitle').text('Material change unavailable');
            $('#changeMaterialCurrentMeta').text(message);
            $('#changeMaterialSelect').html('<option value="">No eligible materials available</option>').prop('disabled', true);
            return;
          }

          populateChangeMaterialCandidates(resp.data.order || null, resp.data.candidates || []);
        }).fail(function(xhr) {
          var message = xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : 'Unable to load replacement materials right now.';
          setChangeMaterialFeedback('danger', message);
          $('#changeMaterialCurrentTitle').text('Material change unavailable');
          $('#changeMaterialCurrentMeta').text(message);
          $('#changeMaterialSelect').html('<option value="">No eligible materials available</option>').prop('disabled', true);
        });
      });

      $('#changeMaterialSelect').on('change', function() {
        $('#changeMaterialSubmit').prop('disabled', !$(this).val());
        if ($(this).val()) {
          setChangeMaterialFeedback('', '');
        }
      });

      $('#changeMaterialForm').on('submit', function(e) {
        e.preventDefault();
        var $submit = $('#changeMaterialSubmit');
        $submit.prop('disabled', true).text('Changing...');
        setChangeMaterialFeedback('', '');

        $.ajax({
          url: 'model/change-material.php',
          method: 'POST',
          data: $(this).serialize(),
          dataType: 'json'
        }).done(function(resp) {
          if (!resp || resp.status !== 'success') {
            var message = resp && resp.message ? resp.message : 'Unable to change material.';
            setChangeMaterialFeedback('danger', message);
            $submit.prop('disabled', false).text('Change Material');
            return;
          }

          setChangeMaterialFeedback('success', resp.message || 'Material changed successfully.');
          showBanner(resp.message || 'Material changed successfully.', 'success');
          setTimeout(function() {
            window.location.reload();
          }, 1200);
        }).fail(function(xhr) {
          var message = xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : 'Unable to change material right now.';
          setChangeMaterialFeedback('danger', message);
          showBanner(message, 'danger');
          $submit.prop('disabled', false).text('Change Material');
        });
      });

      $(document).on('click', '.js-mark-material-lost', function() {
        var boughtId = Number($(this).data('bought-id') || 0);
        var itemTitle = $(this).data('item-title') || 'this material';
        var courseCode = $(this).data('course-code') ? ' - ' + $(this).data('course-code') : '';
        var $btn = $(this);

        if (!boughtId) {
          showBanner('Unable to identify the selected purchased material.', 'danger');
          return;
        }

        if (!window.confirm('Mark ' + itemTitle + courseCode + ' as lost? It will stay in your history, but you can buy another copy afterward.')) {
          return;
        }

        $btn.prop('disabled', true).text('Marking...');
        $.ajax({
          url: 'model/mark-material-lost.php',
          method: 'POST',
          data: { bought_id: boughtId },
          dataType: 'json'
        }).done(function(resp) {
          var success = resp && resp.status === 'success';
          var message = resp && resp.message ? resp.message : 'Unable to update the selected material.';
          showBanner(message, success ? 'success' : 'danger');
          if (success) {
            setTimeout(function() {
              window.location.reload();
            }, 900);
            return;
          }
          $btn.prop('disabled', false).text('Mark Lost');
        }).fail(function(xhr) {
          var message = xhr.responseJSON && xhr.responseJSON.message
            ? xhr.responseJSON.message
            : 'Unable to mark this material copy as lost right now.';
          showBanner(message, 'danger');
          $btn.prop('disabled', false).text('Mark Lost');
        });
      });
    });
  </script>
</body>

</html>

