<?php
session_start();
include('model/config.php');
include('model/page_config.php');
require_once 'model/material_request_service.php';

$materialRequestTableReady = nivasityMaterialRequestsReady($conn);
$highlightToken = trim((string)($_GET['request'] ?? ''));
$userFacultyId = nivasityMaterialRequestGetUserFacultyId($conn, (int)$user_dept, (int)$school_id);
$materialRequests = $materialRequestTableReady ? nivasityMaterialRequestFetchVisibleRequests($conn, $user_) : [];

$departments = [];
$faculties = [];

if ((int)$school_id > 0) {
  $deptQuery = mysqli_query($conn, "SELECT id, name FROM depts WHERE school_id = " . (int)$school_id . " AND status = 'active' ORDER BY name ASC");
  if ($deptQuery) {
    while ($dept = mysqli_fetch_assoc($deptQuery)) {
      $departments[] = $dept;
    }
  }

  $facultyQuery = mysqli_query($conn, "SELECT id, name FROM faculties WHERE school_id = " . (int)$school_id . " AND status = 'active' ORDER BY name ASC");
  if ($facultyQuery) {
    while ($faculty = mysqli_fetch_assoc($facultyQuery)) {
      $faculties[] = $faculty;
    }
  }
}

$requestCounts = [
  'open' => 0,
  'under_review' => 0,
  'resolved' => 0,
];

foreach ($materialRequests as $requestRow) {
  $statusKey = (string)($requestRow['status'] ?? 'open');
  if (isset($requestCounts[$statusKey])) {
    $requestCounts[$statusKey]++;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Material Requests - Nivasity</title>
  <?php include('partials/_head.php') ?>
  <link rel="stylesheet" href="assets/vendors/select2/select2.min.css">
  <link rel="stylesheet" href="assets/vendors/select2-bootstrap-theme/select2-bootstrap.min.css">
  <style>
    .material-request-card {
      border: 1px solid rgba(255, 145, 0, 0.12);
      border-radius: 1rem;
      overflow: hidden;
    }

    .material-request-card.is-highlighted {
      border-color: rgba(255, 145, 0, 0.45);
      box-shadow: 0 22px 45px rgba(255, 145, 0, 0.15);
    }

    .material-request-form-card {
      background: linear-gradient(180deg, #fff9f0 0%, #ffffff 100%);
      border: 1px solid rgba(255, 145, 0, 0.14);
    }

    .material-request-launch-btn {
      border-radius: 0.95rem;
      font-weight: 800;
      min-height: 3.35rem;
      box-shadow: 0 16px 32px rgba(255, 145, 0, 0.18);
    }

    .material-request-modal .modal-content {
      border: none;
      border-radius: 1.15rem;
      overflow: hidden;
      box-shadow: 0 24px 60px rgba(32, 24, 17, 0.16);
    }

    .material-request-modal .modal-header {
      border-bottom: 1px solid rgba(31, 31, 31, 0.06);
      padding: 1.2rem 1.4rem;
    }

    .material-request-modal .modal-body {
      padding: 1.4rem;
    }

    .material-request-modal .modal-footer {
      border-top: 1px solid rgba(31, 31, 31, 0.06);
      padding: 1rem 1.4rem 1.25rem;
    }

    .material-request-modal .form-outline .form-control {
      border: 1px solid #c9cdd4;
      border-radius: 0.6rem;
      background: #fff;
      box-shadow: none;
    }

    .material-request-modal .form-outline .form-control:focus {
      border-color: #ff9100;
      box-shadow: 0 0 0 0.2rem rgba(255, 145, 0, 0.2);
    }

    .material-request-note {
      background: rgba(255, 145, 0, 0.1);
      border: 1px solid rgba(255, 145, 0, 0.16);
      color: #805100;
      border-radius: 0.9rem;
      padding: 0.95rem 1rem;
      line-height: 1.55;
    }

    .material-request-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
      gap: 1rem;
    }

    .material-request-stat {
      border-radius: 1rem;
      background: #fff;
      border: 1px solid rgba(31, 31, 31, 0.06);
      padding: 1rem 1.1rem;
      box-shadow: 0 12px 25px rgba(34, 28, 21, 0.04);
    }

    .material-request-stat .label {
      color: #7f746b;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      font-weight: 700;
    }

    .material-request-stat .value {
      color: #1f1f1f;
      font-size: 1.6rem;
      font-weight: 800;
      margin-top: 0.25rem;
      display: block;
    }

    .request-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      border-radius: 999px;
      padding: 0.35rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .request-pill.open {
      background: rgba(255, 145, 0, 0.14);
      color: #d97800;
    }

    .request-pill.review {
      background: rgba(27, 120, 242, 0.12);
      color: #1b78f2;
    }

    .request-pill.resolved {
      background: rgba(18, 166, 102, 0.12);
      color: #128a5a;
    }

    .request-meta {
      color: #746961;
      font-size: 0.92rem;
    }

    .request-progress-wrap {
      margin-top: 1rem;
    }

    .request-progress-wrap .progress {
      height: 0.75rem;
      border-radius: 999px;
      background: #f2ece5;
    }

    .request-progress-wrap .progress-bar {
      background: linear-gradient(135deg, #ff9a1f 0%, #ff8400 100%);
    }

    .request-action-btn {
      border-radius: 0.85rem;
      font-weight: 800;
    }

    .request-upvote-btn .mdi {
      font-size: 1.1rem;
      margin-right: 0.35rem;
    }

    .request-share-link {
      border-radius: 0.85rem;
    }

    .request-resolution-note {
      background: #f3fff8;
      border: 1px solid rgba(18, 166, 102, 0.14);
      color: #166a49;
      border-radius: 0.85rem;
      padding: 0.85rem 0.95rem;
      line-height: 1.5;
    }

    .select2-container--bootstrap {
      width: 100% !important;
    }

    .select2-container--bootstrap .select2-selection--single,
    .select2-container--bootstrap .select2-selection--multiple {
      min-height: calc(3rem + 2px);
      padding: 0.65rem 0.9rem;
      border: 1px solid #c9cdd4;
      border-radius: 0.6rem;
    }

    .select2-container--bootstrap.select2-container--focus .select2-selection,
    .select2-container--bootstrap.select2-container--open .select2-selection {
      border-color: #ff9100;
      box-shadow: 0 0 0 0.2rem rgba(255, 145, 0, 0.2);
    }

    .select2-container--bootstrap .select2-results__option--highlighted[aria-selected],
    .select2-container--bootstrap .select2-results__option[aria-selected="true"] {
      background-color: #ff9100;
      color: #fff;
    }

    .select2-container--bootstrap .select2-dropdown {
      border-color: #ff9100;
    }
  </style>
</head>

<body>
  <div class="container-scroller sidebar-fixed">
    <?php include('partials/_navbar.php') ?>
    <div class="container-fluid page-body-wrapper">
      <?php include('partials/_sidebar_user.php') ?>
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row mb-4">
            <div class="col-12">
              <div class="card card-rounded shadow-sm material-request-form-card">
                <div class="card-body p-4">
                  <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-4">
                    <div>
                      <h3 class="fw-bold mb-2">Can’t find a material?</h3>
                      <p class="text-muted mb-0">Request it here, choose who needs it, and share the request so course mates can upvote it.</p>
                    </div>
                    
                    <button type="button" class="btn btn-primary btn-lg material-request-launch-btn" data-bs-toggle="modal" data-bs-target="#materialRequestModal">
                      Create Material Request
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="row mb-4">
            <div class="col-12">
              <div class="material-request-stats">
                <div class="material-request-stat">
                  <span class="label">Open Requests</span>
                  <span class="value"><?php echo (int)$requestCounts['open']; ?></span>
                </div>
                <div class="material-request-stat">
                  <span class="label">Under Review</span>
                  <span class="value"><?php echo (int)$requestCounts['under_review']; ?></span>
                </div>
                <div class="material-request-stat">
                  <span class="label">Resolved</span>
                  <span class="value"><?php echo (int)$requestCounts['resolved']; ?></span>
                </div>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <?php if (empty($materialRequests)): ?>
                <div class="card card-rounded shadow-sm">
                  <div class="card-body py-5 text-center">
                    <h4 class="fw-bold mb-2">No visible material requests yet</h4>
                    <p class="text-muted mb-0">Once students in your audience create requests, they will appear here for upvotes and sharing.</p>
                  </div>
                </div>
              <?php else: ?>
                <?php foreach ($materialRequests as $requestRow): ?>
                  <?php
                    $statusClass = $requestRow['status'] === 'resolved' ? 'resolved' : ($requestRow['status'] === 'under_review' ? 'review' : 'open');
                    $shareUrl = nivasity_app_url('material_requests.php?request=' . urlencode((string)$requestRow['share_token']));
                    $isHighlighted = $highlightToken !== '' && $highlightToken === (string)$requestRow['share_token'];
                  ?>
                  <div class="card card-rounded shadow-sm material-request-card mb-4<?php echo $isHighlighted ? ' is-highlighted' : ''; ?>" id="request-<?php echo (int)$requestRow['id']; ?>">
                    <div class="card-body p-4">
                      <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                        <div>
                          <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                            <h4 class="fw-bold mb-0"><?php echo htmlspecialchars($requestRow['material_title']); ?></h4>
                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($requestRow['material_code']); ?></span>
                            <span class="request-pill <?php echo $statusClass; ?>">
                              <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($requestRow['status']))); ?>
                            </span>
                          </div>
                          <div class="request-meta mb-2">
                            Requested by <?php echo htmlspecialchars($requestRow['requester_name'] !== '' ? $requestRow['requester_name'] : 'A student'); ?>
                            on <?php echo htmlspecialchars(date('d M Y', strtotime((string)$requestRow['created_at']))); ?>
                          </div>
                          <div class="request-meta">
                            <strong>Audience:</strong> <?php echo htmlspecialchars($requestRow['audience_label']); ?>
                          </div>
                        </div>
                        <div class="text-lg-end">
                          <div class="fw-bold text-dark"><?php echo (int)$requestRow['upvote_count']; ?> / <?php echo (int)$requestRow['expected_buyers_count']; ?> upvotes</div>
                          <div class="request-meta"><?php echo htmlspecialchars(number_format((float)$requestRow['progress_percent'], 1)); ?>% of expected buyers</div>
                        </div>
                      </div>

                      <div class="request-progress-wrap">
                        <div class="progress">
                          <div class="progress-bar" role="progressbar" style="width: <?php echo min(100, max(0, (float)$requestRow['progress_percent'])); ?>%"></div>
                        </div>
                        <div class="request-meta mt-2">
                          <?php if ($requestRow['status'] === 'resolved'): ?>
                            Resolved at <?php echo htmlspecialchars(date('d M Y', strtotime((string)($requestRow['updated_at'] ?? $requestRow['created_at'])))); ?>.
                          <?php elseif ($requestRow['threshold_met']): ?>
                            This request has crossed the 40% threshold and is now on the admin radar.
                          <?php else: ?>
                            It needs <?php echo htmlspecialchars(number_format(max(0, (float)$requestRow['threshold_percent'] - (float)$requestRow['progress_percent']), 1)); ?>% more support to hit the 40% review threshold.
                          <?php endif; ?>
                        </div>
                      </div>

                      <?php if ($requestRow['status'] === 'resolved' && trim((string)($requestRow['resolution_note'] ?? '')) !== ''): ?>
                        <div class="request-resolution-note mt-3">
                          <strong>Resolution note:</strong> <?php echo htmlspecialchars((string)$requestRow['resolution_note']); ?>
                        </div>
                      <?php endif; ?>

                      <?php if ($requestRow['status'] !== 'resolved'): ?>
                        <div class="d-flex flex-column flex-md-row gap-2 mt-4">
                          <button
                            type="button"
                            class="btn btn-primary request-action-btn request-upvote-btn js-upvote-request"
                            data-request-id="<?php echo (int)$requestRow['id']; ?>"
                            <?php echo $requestRow['viewer_has_upvoted'] ? 'disabled' : ''; ?>>
                            <i class="mdi mdi-thumb-up"></i>
                            <?php echo $requestRow['viewer_has_upvoted'] ? 'Upvoted' : 'Upvote Request'; ?>
                          </button>
                          <button type="button" class="btn btn-outline-primary request-action-btn request-share-link js-copy-request-link" data-share-url="<?php echo htmlspecialchars($shareUrl, ENT_QUOTES, 'UTF-8'); ?>">
                            Copy Share Link
                          </button>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php include('partials/_footer.php') ?>
        <?php include('partials/_bulk_payment_modal.php') ?>
      </div>

      <?php if ($materialRequestTableReady && $user_status === 'verified' && (int)$user_dept > 0): ?>
        <div class="modal fade material-request-modal" id="materialRequestModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h4 class="fw-bold mb-1">New Material Request</h4>
                  <p class="text-muted mb-0">Request a missing material and choose the students who should see it.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form id="material-request-form">
                <div class="modal-body">
                  <input type="hidden" name="action" value="create">
                    <div class="row">
                    <div class="col-md-4">
                      <div class="form-outline mb-4">
                        <input type="text" name="material_code" id="material_code" class="form-control form-control-lg w-100" maxlength="100" required>
                        <label class="form-label" for="material_code">Material Code</label>
                      </div>
                    </div>
                    <div class="col-md-8">
                      <div class="form-outline mb-4">
                        <input type="text" name="material_title" id="material_title" class="form-control form-control-lg w-100" maxlength="255" required>
                        <label class="form-label" for="material_title">Material Title</label>
                      </div>
                    </div>
                    <div class="col-12">
                      <label class="form-label" for="request_scope">Who needs this material?</label>
                      <select name="scope" id="request_scope" class="form-control form-control-lg academic-select" required>
                        <option value="school">All Students in My School</option>
                        <option value="faculty">All students in my Faculty</option>
                        <option value="selected_faculties">Selected Faculties</option>
                        <option value="selected_departments">Selected Departments</option>
                        <option value="my_department">Only My Department</option>
                      </select>
                    </div>
                    <div class="col-12 d-none mt-3" id="targetFacultiesWrap">
                      <label class="form-label" for="target_faculty_ids">Faculties</label>
                      <select name="target_faculty_ids[]" id="target_faculty_ids" class="form-control form-control-lg academic-select" multiple>
                        <?php foreach ($faculties as $faculty): ?>
                          <option value="<?php echo (int)$faculty['id']; ?>"><?php echo htmlspecialchars($faculty['name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-12 d-none mt-3" id="targetDepartmentsWrap">
                      <label class="form-label" for="target_dept_ids">Departments</label>
                      <select name="target_dept_ids[]" id="target_dept_ids" class="form-control form-control-lg academic-select" multiple>
                        <?php foreach ($departments as $department): ?>
                          <option value="<?php echo (int)$department['id']; ?>"><?php echo htmlspecialchars($department['name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-light request-action-btn" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" id="material_request_submit" class="btn btn-primary fw-bold btn-lg request-action-btn">Submit Material Request</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div id="alertBanner" class="alert alert-info text-center fw-bold alert-dismissible end-2 top-2 fade show position-fixed w-auto p-2 px-4"
        role="alert" style="z-index: 5000; display: none;">
        An error occurred.
      </div>
    </div>
  </div>

  <script src="assets/vendors/js/vendor.bundle.base.js"></script>
  <script src="assets/vendors/select2/select2.min.js"></script>
  <script src="assets/js/js/off-canvas.js"></script>
  <script src="assets/js/js/hoverable-collapse.js"></script>
  <script src="assets/js/js/template.js"></script>
  <script src="assets/js/js/settings.js"></script>
  <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function () {
      $('.academic-select').select2({
        width: '100%',
        theme: 'bootstrap',
        dropdownParent: $('#materialRequestModal').length ? $('#materialRequestModal') : $(document.body)
      });

      function showBanner(message, type) {
        $('#alertBanner').html(message);
        $('#alertBanner').removeClass('alert-info alert-danger alert-success alert-warning').addClass('alert-' + type);
        $('#alertBanner').fadeIn();
        setTimeout(function () {
          $('#alertBanner').fadeOut();
        }, 5000);
      }

      function toggleAudienceFields() {
        var scope = $('#request_scope').val();
        $('#targetFacultiesWrap').toggleClass('d-none', scope !== 'selected_faculties');
        $('#target_faculty_ids').prop('required', scope === 'selected_faculties');
        $('#targetDepartmentsWrap').toggleClass('d-none', scope !== 'selected_departments');
        $('#target_dept_ids').prop('required', scope === 'selected_departments');
      }

      $('#request_scope').on('change', toggleAudienceFields);
      toggleAudienceFields();

      $('#materialRequestModal').on('hidden.bs.modal', function () {
        var form = document.getElementById('material-request-form');
        if (form) {
          form.reset();
          $('#target_faculty_ids').val(null).trigger('change');
          $('#target_dept_ids').val(null).trigger('change');
          $('#request_scope').val('school').trigger('change');
        }
      });

      $('#material-request-form').on('submit', function (event) {
        event.preventDefault();

        var button = $('#material_request_submit');
        var originalText = button.html();
        button.html(originalText + '  <div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        var formData = new FormData(this);

        $.ajax({
          type: 'POST',
          url: 'model/material_requests.php',
          data: formData,
          contentType: false,
          processData: false,
          success: function (data) {
            var bannerType = data.status === 'success' ? 'success' : (data.status === 'warning' ? 'warning' : 'danger');
            showBanner(data.message || 'Request processed.', bannerType);

            if (data.status === 'success') {
              var modalElement = document.getElementById('materialRequestModal');
              if (modalElement && window.bootstrap) {
                var modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                  modalInstance.hide();
                }
              }
            }

            if (data.redirect) {
              setTimeout(function () {
                window.location.href = data.redirect;
              }, 1200);
            }

            button.html(originalText);
            button.prop('disabled', false);
          },
          error: function (xhr) {
            var response = xhr.responseJSON || {};
            showBanner(response.message || 'Unable to submit your request right now.', 'danger');
            button.html(originalText);
            button.prop('disabled', false);
          }
        });
      });

      $(document).on('click', '.js-upvote-request', function () {
        var button = $(this);
        var originalText = button.html();
        button.prop('disabled', true).html('<div class="spinner-border text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span></div>');

        $.ajax({
          type: 'POST',
          url: 'model/material_requests.php',
          data: { action: 'upvote', request_id: button.data('request-id') },
          success: function (data) {
            showBanner(data.message || 'Upvote saved.', data.status === 'success' ? 'success' : 'info');
            setTimeout(function () {
              window.location.reload();
            }, 900);
          },
          error: function (xhr) {
            var response = xhr.responseJSON || {};
            showBanner(response.message || 'Unable to save your upvote right now.', 'danger');
            button.prop('disabled', false).html(originalText);
          }
        });
      });

      $(document).on('click', '.js-copy-request-link', function () {
        var button = $(this);
        var shareUrl = button.data('share-url');

        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(shareUrl).then(function () {
            showBanner('Share link copied successfully.', 'success');
          }).catch(function () {
            showBanner('We could not copy the share link automatically. Please copy it manually.', 'warning');
          });
          return;
        }

        var tempInput = document.createElement('input');
        tempInput.value = shareUrl;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        showBanner('Share link copied successfully.', 'success');
      });
    });
  </script>
</body>

</html>