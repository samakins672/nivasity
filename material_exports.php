<?php
session_start();
include('model/config.php');
include('model/page_config.php');

if (($_SESSION['nivas_userRole'] ?? '') !== 'hoc') {
  header('Location: /');
  exit();
}

$hocDeptInt = (int) $user_dept;
$hocSchoolInt = (int) $school_id;
$hocFacultyId = 0;
$hocLegacySharedVisibilityWhere = "1 = 0";
$hocSharedVisibilityWhere = "1 = 0";
$hocManualVisibilityWhere = "m.user_id = $user_id";

try {
  $deptsHasFacultyId = false;
  $manualsHasFaculty = false;
  $manualsHasDepts = false;
  $deptsFacultyColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM depts LIKE 'faculty_id'");
  if ($deptsFacultyColumnRes && mysqli_num_rows($deptsFacultyColumnRes) > 0) {
    $deptsHasFacultyId = true;
  }
  $manualsFacultyColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM manuals LIKE 'faculty'");
  if ($manualsFacultyColumnRes && mysqli_num_rows($manualsFacultyColumnRes) > 0) {
    $manualsHasFaculty = true;
  }
  $manualsDeptsColumnRes = mysqli_query($conn, "SHOW COLUMNS FROM manuals LIKE 'depts'");
  if ($manualsDeptsColumnRes && mysqli_num_rows($manualsDeptsColumnRes) > 0) {
    $manualsHasDepts = true;
  }

  $legacySharedVisibilityParts = [];
  if ($hocDeptInt > 0) {
    $legacySharedVisibilityParts[] = "m.dept = $hocDeptInt";
  }

  if ($deptsHasFacultyId && $manualsHasFaculty && $hocDeptInt > 0) {
    $userDeptMetaQ = mysqli_query($conn, "SELECT faculty_id FROM depts WHERE id = $hocDeptInt AND school_id = $hocSchoolInt LIMIT 1");
    if ($userDeptMetaQ && mysqli_num_rows($userDeptMetaQ) > 0) {
      $userDeptMeta = mysqli_fetch_assoc($userDeptMetaQ);
      $hocFacultyId = isset($userDeptMeta['faculty_id']) ? (int) $userDeptMeta['faculty_id'] : 0;
    }
    if ($hocFacultyId > 0) {
      $legacySharedVisibilityParts[] = "(m.dept = 0 AND m.faculty = $hocFacultyId)";
    }
  }

  if (!empty($legacySharedVisibilityParts)) {
    $hocLegacySharedVisibilityWhere = implode(' OR ', $legacySharedVisibilityParts);
  }

  if ($manualsHasDepts && $hocDeptInt > 0) {
    $normalized_depts_expr = "REPLACE(REPLACE(REPLACE(REPLACE(m.depts, '[', ''), ']', ''), '\"', ''), ' ', '')";
    $hocSharedVisibilityWhere = "(m.depts IS NOT NULL AND FIND_IN_SET($hocDeptInt, $normalized_depts_expr) > 0) OR (m.depts IS NULL AND ($hocLegacySharedVisibilityWhere))";
  } else {
    $hocSharedVisibilityWhere = $hocLegacySharedVisibilityWhere;
  }

  $hocManualVisibilityWhere = "m.user_id = $user_id OR (m.user_id = 0 AND m.school_id = $hocSchoolInt AND ($hocSharedVisibilityWhere))";
} catch (Throwable $e) {
  error_log('[material_exports] hoc visibility fallback: ' . $e->getMessage());
}

$manual_query_sql = "SELECT * FROM manuals AS m WHERE $hocManualVisibilityWhere ORDER BY m.id DESC";
$manual_query = mysqli_query($conn, $manual_query_sql);
$manualSalesMap = [];
$visible_manual_count = 0;

if ($manual_query) {
  $manualIds = [];
  while ($manualRowForStats = mysqli_fetch_assoc($manual_query)) {
    $manualIds[] = (int) $manualRowForStats['id'];
    $visible_manual_count++;
  }

  if (!empty($manualIds)) {
    $manualIdsCsv = implode(',', array_values(array_unique($manualIds)));
    $manualSalesQuery = mysqli_query(
      $conn,
      "SELECT mb.manual_id, COUNT(mb.manual_id) AS cnt, COALESCE(SUM(mb.price), 0) AS total
       FROM manuals_bought AS mb
       JOIN users AS bu ON bu.id = mb.buyer
       WHERE mb.manual_id IN ($manualIdsCsv)
         AND mb.status = 'successful'
         AND bu.dept = $hocDeptInt
       GROUP BY mb.manual_id"
    );
    if ($manualSalesQuery) {
      while ($manualSalesRow = mysqli_fetch_assoc($manualSalesQuery)) {
        $manualSalesMap[(int) $manualSalesRow['manual_id']] = [
          'cnt' => (int) $manualSalesRow['cnt'],
          'total' => (int) $manualSalesRow['total'],
        ];
      }
    }
  }

  mysqli_data_seek($manual_query, 0);
}

$faculties = [];
$faculties_query = mysqli_query($conn, "SELECT id, name FROM faculties WHERE school_id = $school_id AND status = 'active' ORDER BY name ASC");
if ($faculties_query) {
  while ($faculty = mysqli_fetch_assoc($faculties_query)) {
    $faculties[(int) $faculty['id']] = $faculty['name'];
  }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Material Exports - Nivasity</title>
  <?php include('partials/_head.php') ?>
  <style>
    .material-export-note {
      background: #f8f9fc;
      border: 1px solid #e7ebf3;
      border-radius: 1rem;
      color: #586277;
      padding: 0.95rem 1rem;
    }

    .material-export-empty {
      background: #f8f9fc;
      border: 1px dashed #d6dbe7;
      border-radius: 1rem;
      padding: 2rem 1.5rem;
      text-align: center;
    }
  </style>
</head>

<body>
  <div class="container-scroller">
    <?php include('partials/_navbar.php') ?>
    <div class="container-fluid page-body-wrapper">
      <?php include('partials/_sidebar_user.php') ?>
      <div class="main-panel">
        <div class="content-wrapper py-0">
          <div class="row">
            <div class="col-sm-12 px-2">
              <div class="home-tab">
                <div class="tab-content tab-content-basic py-0">
                  <div class="tab-pane fade show active" id="material-exports" role="tabpanel" aria-labelledby="material-exports">
                    <div class="row flex-grow">
                      <div class="col-12 grid-margin stretch-card">
                        <div class="card card-rounded shadow-sm">
                          <div class="card-body">
                            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
                              <div>
                                <h4 class="fw-bold mb-1">Material Exports</h4>
                                <p class="text-muted mb-0">Export eligible student lists for the materials visible to your department and faculty scope.</p>
                              </div>
                              <div class="material-export-note small">
                                Export includes only students who have not yet been marked as collected for that material.
                              </div>
                            </div>

                            <?php if ($visible_manual_count === 0): ?>
                              <div class="material-export-empty">
                                <h5 class="fw-bold mb-2">No materials available yet</h5>
                                <p class="text-muted mb-0">When materials are available to your HOC scope, they will appear here for export.</p>
                              </div>
                            <?php else: ?>
                              <div class="table-responsive mt-1">
                                <table class="table table-hover select-table datatable-opt">
                                  <thead>
                                    <tr>
                                      <th>Name</th>
                                      <th class="d-sm-none-2">Faculty</th>
                                      <th class="d-sm-none-2">Unit Price</th>
                                      <th>Revenue</th>
                                      <th class="d-sm-none-2">Availability</th>
                                      <th>Status</th>
                                      <th>Export</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <?php while ($manual = mysqli_fetch_array($manual_query)): ?>
                                      <?php
                                      $manual_id = (int) $manual['id'];
                                      $manual_title_esc = htmlspecialchars((string) $manual['title'], ENT_QUOTES, 'UTF-8');
                                      $manual_course_code_esc = htmlspecialchars((string) $manual['course_code'], ENT_QUOTES, 'UTF-8');
                                      $manual_code_esc = htmlspecialchars((string) $manual['code'], ENT_QUOTES, 'UTF-8');
                                      $manualSales = isset($manualSalesMap[$manual_id]) ? $manualSalesMap[$manual_id] : ['cnt' => 0, 'total' => 0];
                                      $manuals_bought_cnt = (int) $manualSales['cnt'];
                                      $manuals_bought_price = (int) $manualSales['total'];
                                      $manualQuantity = max((int) $manual['quantity'], 1);
                                      $percentage_sold = ($manuals_bought_cnt / $manualQuantity) * 100;
                                      $sold_quantity_text = $manuals_bought_cnt . '/' . $manual['quantity'];
                                      $due_date2 = date('Y-m-d', strtotime($manual['due_date']));
                                      $status = $manual['status'];
                                      if ($date > $due_date2) {
                                        $status = 'overdue';
                                      }
                                      ?>
                                      <tr>
                                        <td>
                                          <div>
                                            <h6><span class="d-sm-none-2"><?php echo $manual_title_esc; ?> -</span> <?php echo $manual_course_code_esc; ?></h6>
                                            <p class="d-sm-none-2">ID: <span class="fw-bold"><?php echo $manual_code_esc; ?></span></p>
                                          </div>
                                        </td>
                                        <td class="d-sm-none-2">
                                          <h6><?php echo isset($manual['faculty'], $faculties[(int) $manual['faculty']]) ? htmlspecialchars($faculties[(int) $manual['faculty']], ENT_QUOTES, 'UTF-8') : '&mdash;'; ?></h6>
                                        </td>
                                        <td class="d-sm-none-2">
                                          <h6>&#8358; <?php echo number_format((int) $manual['price']); ?></h6>
                                        </td>
                                        <td>
                                          <h6 class="text-secondary">&#8358; <?php echo number_format($manuals_bought_price); ?></h6>
                                          <p>Sold: <span class="fw-bold"><?php echo $manuals_bought_cnt; ?></span></p>
                                        </td>
                                        <td class="d-sm-none-2">
                                          <div>
                                            <div class="d-flex justify-content-between align-items-center mb-1 max-width-progress-wrap">
                                              <p class="text-success"><?php echo round($percentage_sold); ?>%</p>
                                              <p><?php echo htmlspecialchars($sold_quantity_text, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                            <div class="progress progress-md">
                                              <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage_sold; ?>%" aria-valuenow="<?php echo $percentage_sold; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                          </div>
                                        </td>
                                        <td>
                                          <div class="badge <?php echo ($status === 'open') ? 'bg-success' : 'bg-danger'; ?>"><?php echo ($status === 'open') ? 'Active' : 'Closed'; ?></div>
                                        </td>
                                        <td>
                                          <?php if ($manuals_bought_cnt >= 1): ?>
                                            <button type="button" class="btn btn-outline-dark btn-sm export-manual" data-bs-toggle="modal" data-bs-target="#exportManual" data-manual_id="<?php echo $manual_id; ?>" data-code="<?php echo $manual_course_code_esc; ?>">
                                              <i class="mdi mdi-export-variant"></i> Export List
                                            </button>
                                          <?php else: ?>
                                            <span class="badge bg-light text-dark">No exports yet</span>
                                          <?php endif; ?>
                                        </td>
                                      </tr>
                                    <?php endwhile; ?>
                                  </tbody>
                                </table>
                              </div>
                            <?php endif; ?>
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
        <?php include('partials/_footer.php') ?>
        <?php include('partials/_bulk_payment_modal.php') ?>
      </div>
      <div id="alertBanner" class="alert alert-info text-center fw-bold alert-dismissible end-2 top-2 fade show position-fixed w-auto p-2 px-4" role="alert" style="z-index: 5000; display: none;">
        An error occurred during the export request.
      </div>
      <div class="modal fade" id="exportManual" tabindex="-1" role="dialog" aria-labelledby="exportManualLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h4 class="modal-title fw-bold" id="exportManualLabel">Got a RRR Number?</h4>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="export-manual-form">
              <input type="hidden" name="code" value="0">
              <input type="hidden" name="manual_id" value="0">
              <div class="modal-body">
                <div class="alert alert-secondary py-2 px-3 mb-3 small">
                  Export includes only students yet to be marked as collected on the platform. Students already granted will not appear in subsequent exports.
                </div>
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
    </div>
  </div>

  <script src="assets/vendors/js/vendor.bundle.base.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.1/mdb.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <script src="assets/js/js/off-canvas.js"></script>
  <script src="assets/js/js/hoverable-collapse.js"></script>
  <script src="assets/js/js/template.js"></script>
  <script src="assets/js/js/settings.js"></script>
  <script src="assets/js/js/data-table.js"></script>
  <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');

      function showExportBanner(message, status) {
        $('#alertBanner').html(message);
        if (status === 'success') {
          $('#alertBanner').removeClass('alert-info alert-danger').addClass('alert-success');
        } else {
          $('#alertBanner').removeClass('alert-success alert-info').addClass('alert-danger');
        }
        $('#alertBanner').fadeIn();
        setTimeout(function () {
          $('#alertBanner').fadeOut();
        }, 5000);
      }

      $('#exportManual').on('hidden.bs.modal', function () {
        if ($('#export-manual-form')[0]) {
          $('#export-manual-form')[0].reset();
        }
      });

      $(document).on('click', '.export-manual', function () {
        var manualId = $(this).data('manual_id');
        var code = $(this).data('code');

        $('#export-manual-form input[name="manual_id"]').val(manualId);
        $('#export-manual-form input[name="code"]').val(code);
      });

      $('#export-manual-form').submit(function (event) {
        event.preventDefault();

        var manualId = $('#export-manual-form input[name="manual_id"]').val();
        var code = $('#export-manual-form input[name="code"]').val();
        var rrr = $('#export-manual-form input[name="rrr"]').val();
        var secondCheck = 'Once this list is granted, those students will not be in the next/subsequent export for this material. Proceed now?';

        if (!window.confirm(secondCheck)) {
          return;
        }

        var button = $('#export_manual_submit');
        var originalText = button.html();

        button.html('<div class="spinner-border spinner-border-sm text-white" style="width: 1rem; height: 1rem;" role="status"><span class="sr-only"></span>');
        button.prop('disabled', true);

        fetch('model/export.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
          },
          body: new URLSearchParams({
            manual_id: manualId,
            code: code,
            rrr: rrr,
            output: 'pdf'
          })
        })
          .then(async function (response) {
            var contentType = response.headers.get('content-type') || '';
            if (!response.ok || contentType.indexOf('application/pdf') === -1) {
              var errorMessage = 'Unable to export material right now. Please try again.';
              if (contentType.indexOf('application/json') !== -1) {
                var payload = await response.json();
                if (payload && payload.message) {
                  errorMessage = payload.message;
                }
              }
              throw new Error(errorMessage);
            }

            var blob = await response.blob();
            var downloadUrl = window.URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = downloadUrl;
            link.download = 'manual-export-' + code + '.pdf';
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(downloadUrl);
            showExportBanner('Export downloaded successfully.', 'success');
          })
          .catch(function (error) {
            showExportBanner(error && error.message ? error.message : 'Unable to export material right now. Please try again.', 'error');
          })
          .finally(function () {
            button.html(originalText);
            button.prop('disabled', false);
          });
      });
    });
  </script>
</body>

</html>