<?php
session_start();
include('model/config.php');
include('model/page_config.php');
require_once 'model/material_change_service.php';

$manual_query = mysqli_query($conn, "SELECT * FROM manuals_bought WHERE buyer = $user_id AND school_id = $school_id ORDER BY created_at DESC");
$manuals_bought_has_id = material_change_has_column($conn, 'manuals_bought', 'id');
$manuals_bought_has_grant_status = material_change_has_column($conn, 'manuals_bought', 'grant_status');
$manuals_bought_has_export_id = material_change_has_column($conn, 'manuals_bought', 'export_id');

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
                                $is_granted = ($manuals_bought_has_grant_status && material_change_boolish_is_true($manual['grant_status'] ?? '0'))
                                  || ($manuals_bought_has_export_id && (int) ($manual['export_id'] ?? 0) > 0);
                                $was_changed = $manuals_bought_has_id && isset($changed_bought_ids[(int) ($manual['id'] ?? 0)]);
                                $can_change_material = $is_within_change_window && strtolower((string) $status) === 'successful' && !$is_granted && !$was_changed;
                                $change_material_reason = '';
                                if (!$is_within_change_window) {
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
                                  <div class="badge <?php echo ($status == 'successful') ? 'bg-success' : 'bg-danger'; ?>"><?php echo $status; ?></div>
                                </td>
                                <td>
                                  <div class="d-flex flex-wrap gap-2">
                                    <a href="model/receipt.php?action=download&format=pdf&ref=<?php echo urlencode($manual['ref_id']); ?>&kind=manual&item_id=<?php echo (int)$manual['manual_id']; ?>" class="btn btn-sm btn-outline-primary" title="Download receipt as PDF">
                                      Download
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-email-receipt" data-ref="<?php echo htmlspecialchars($manual['ref_id']); ?>" data-kind="manual" data-item-id="<?php echo (int)$manual['manual_id']; ?>" title="Email receipt">
                                      Email
                                    </button>
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
    });
  </script>
</body>

</html>

