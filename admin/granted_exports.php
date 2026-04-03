<?php
session_start();
include('../model/config.php');
include('../model/page_config.php');

if ($_SESSION['nivas_userRole'] == 'student' || $_SESSION['nivas_userRole'] == 'visitor' || $_SESSION['nivas_userRole'] == 'org_admin') {
  header('Location: /admin');
  exit();
}

function h($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatAmount($amount) {
  return number_format((int)$amount);
}

function formatDateTimeReadable($value) {
  if (empty($value)) {
    return '-';
  }

  $timestamp = strtotime((string)$value);
  if ($timestamp === false) {
    return (string)$value;
  }

  return date('j M Y, g:ia', $timestamp);
}

function tableHasColumn(mysqli $conn, $tableName, $columnName) {
  static $cache = [];
  $cacheKey = $tableName . '.' . $columnName;
  if (isset($cache[$cacheKey])) {
    return $cache[$cacheKey];
  }

  $safeTable = mysqli_real_escape_string($conn, $tableName);
  $safeColumn = mysqli_real_escape_string($conn, $columnName);
  $result = mysqli_query($conn, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
  $cache[$cacheKey] = ($result && mysqli_num_rows($result) > 0);
  return $cache[$cacheKey];
}

function resolveAuditStatusColumn(mysqli $conn) {
  if (tableHasColumn($conn, 'manual_export_audits', 'grant_status')) {
    return 'grant_status';
  }

  if (tableHasColumn($conn, 'manual_export_audits', 'status')) {
    return 'status';
  }

  return '';
}

function grantedStatusSql($qualifiedColumn) {
  return "LOWER(TRIM(CAST($qualifiedColumn AS CHAR))) IN ('granted', '1', 'true', 'yes')";
}

function runSelectAll(mysqli $conn, $sql) {
  $result = mysqli_query($conn, $sql);
  if ($result === false) {
    throw new RuntimeException(mysqli_error($conn));
  }

  $rows = [];
  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
  }

  return $rows;
}

function loadGrantedExportRows(mysqli $conn, array $auditRow, $fallbackDeptId) {
  $manualId = isset($auditRow['manual_id']) ? (int)$auditRow['manual_id'] : 0;
  $auditId = isset($auditRow['id']) ? (int)$auditRow['id'] : 0;
  $downloadedAt = isset($auditRow['downloaded_at']) ? mysqli_real_escape_string($conn, $auditRow['downloaded_at']) : '';
  $hasManualsBoughtId = tableHasColumn($conn, 'manuals_bought', 'id');
  $hasExportId = tableHasColumn($conn, 'manuals_bought', 'export_id');

  if ($manualId <= 0 || $auditId <= 0) {
    return [];
  }

  $baseSelect = "
    SELECT
      u.id AS user_id,
      CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS name,
      u.matric_no,
      u.adm_year,
      mb.price
    FROM manuals_bought AS mb
    JOIN users AS u ON u.id = mb.buyer
  ";

  $orderBy = " ORDER BY u.matric_no ASC, name ASC";

  if ($hasExportId) {
    $rows = runSelectAll(
      $conn,
      $baseSelect . "
        WHERE mb.export_id = $auditId
          AND mb.manual_id = $manualId
          AND mb.status = 'successful'" . $orderBy
    );
    if (!empty($rows)) {
      return $rows;
    }
  }

  if ($hasManualsBoughtId && !empty($auditRow['bought_ids_json'])) {
    $decodedIds = json_decode((string)$auditRow['bought_ids_json'], true);
    if (is_array($decodedIds)) {
      $decodedIds = array_values(array_unique(array_filter(array_map('intval', $decodedIds))));
      if (!empty($decodedIds)) {
        $idList = implode(',', $decodedIds);
        $rows = runSelectAll(
          $conn,
          $baseSelect . "
            WHERE mb.id IN ($idList)
              AND mb.manual_id = $manualId
              AND mb.status = 'successful'" . $orderBy
        );
        if (!empty($rows)) {
          return $rows;
        }
      }
    }
  }

  $fromBoughtId = isset($auditRow['from_bought_id']) ? (int)$auditRow['from_bought_id'] : 0;
  $toBoughtId = isset($auditRow['to_bought_id']) ? (int)$auditRow['to_bought_id'] : 0;
  if ($hasManualsBoughtId && $fromBoughtId > 0 && $toBoughtId > 0 && $toBoughtId >= $fromBoughtId) {
    $rows = runSelectAll(
      $conn,
      $baseSelect . "
        WHERE mb.id BETWEEN $fromBoughtId AND $toBoughtId
          AND mb.manual_id = $manualId
          AND mb.status = 'successful'" . $orderBy
    );
    if (!empty($rows)) {
      return $rows;
    }
  }

  $fallbackFilters = [
    "mb.manual_id = $manualId",
    "mb.status = 'successful'",
  ];
  if ($downloadedAt !== '') {
    $fallbackFilters[] = "mb.created_at <= '$downloadedAt'";
  }
  if ((int)$fallbackDeptId > 0) {
    $fallbackFilters[] = "u.dept = " . (int)$fallbackDeptId;
  }

  return runSelectAll(
    $conn,
    $baseSelect . "\n      WHERE " . implode("\n        AND ", $fallbackFilters) . $orderBy
  );
}

function loadGrantedAuditRecord(mysqli $conn, $auditId, $hocUserId, $statusColumn) {
  if ($auditId <= 0 || $hocUserId <= 0 || $statusColumn === '') {
    return null;
  }

  $hasGrantedBy = tableHasColumn($conn, 'manual_export_audits', 'granted_by');
  $hasGrantedAt = tableHasColumn($conn, 'manual_export_audits', 'granted_at');
  $hasBoughtIdsJson = tableHasColumn($conn, 'manual_export_audits', 'bought_ids_json');
  $hasFromBoughtId = tableHasColumn($conn, 'manual_export_audits', 'from_bought_id');
  $hasToBoughtId = tableHasColumn($conn, 'manual_export_audits', 'to_bought_id');

  $grantedBySelect = $hasGrantedBy ? 'a.granted_by' : 'NULL AS granted_by';
  $grantedAtSelect = $hasGrantedAt ? 'a.granted_at' : 'NULL AS granted_at';
  $boughtIdsJsonSelect = $hasBoughtIdsJson ? 'a.bought_ids_json' : 'NULL AS bought_ids_json';
  $fromBoughtIdSelect = $hasFromBoughtId ? 'a.from_bought_id' : 'NULL AS from_bought_id';
  $toBoughtIdSelect = $hasToBoughtId ? 'a.to_bought_id' : 'NULL AS to_bought_id';
  $grantedByNameSelect = $hasGrantedBy
    ? "TRIM(CONCAT(COALESCE(ad.first_name, ''), ' ', COALESCE(ad.last_name, ''))) AS granted_by_name"
    : "'' AS granted_by_name";
  $grantedByJoin = $hasGrantedBy ? 'LEFT JOIN admins AS ad ON ad.id = a.granted_by' : '';

  $sql = "
    SELECT
      a.id,
      a.code,
      a.manual_id,
      a.hoc_user_id,
      a.students_count,
      a.total_amount,
      a.downloaded_at,
      $grantedBySelect,
      $grantedAtSelect,
      $boughtIdsJsonSelect,
      $fromBoughtIdSelect,
      $toBoughtIdSelect,
      $grantedByNameSelect,
      m.title AS manual_title,
      m.course_code,
      m.code AS manual_code,
      CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) AS hoc_name,
      u.email AS hoc_email
    FROM manual_export_audits AS a
    JOIN manuals AS m ON m.id = a.manual_id
    JOIN users AS u ON u.id = a.hoc_user_id
    $grantedByJoin
    WHERE a.id = $auditId
      AND a.hoc_user_id = $hocUserId
      AND " . grantedStatusSql("a.`$statusColumn`") . "
    LIMIT 1
  ";

  $result = mysqli_query($conn, $sql);
  if ($result && mysqli_num_rows($result) > 0) {
    return mysqli_fetch_assoc($result);
  }

  return null;
}

$auditStatusColumn = resolveAuditStatusColumn($conn);

if (isset($_GET['download'])) {
  $auditId = (int)$_GET['download'];
  $auditRow = loadGrantedAuditRecord($conn, $auditId, (int)$user_id, $auditStatusColumn);

  if (!$auditRow) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>Granted Export Not Found</title>
      <style>
        body { font-family: Arial, sans-serif; padding: 40px; color: #212529; }
      </style>
    </head>
    <body>
      <h2>Granted export not found</h2>
      <p>The selected granted export could not be loaded for this account.</p>
    </body>
    </html>
    <?php
    exit();
  }

  $rows = loadGrantedExportRows($conn, $auditRow, (int)$user_dept);
  $verificationUrl = nivasity_app_url('manual-export-verify.php?code=' . urlencode($auditRow['code']));
  ?>
  <!DOCTYPE html>
  <html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Granted Export <?php echo h($auditRow['code']); ?></title>
    <style>
      body {
        padding: 50px;
        margin: 0;
        width: 100%;
        font-family: Arial, sans-serif;
        box-sizing: border-box;
        position: relative;
        color: #212529;
      }

      .watermark {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        opacity: 0.08;
        width: 80%;
        z-index: -1;
      }

      .granted-stamp {
        position: fixed;
        top: 24px;
        left: 24px;
        border: 3px solid #1f7a3d;
        color: #1f7a3d;
        padding: 10px 16px;
        font-size: 18px;
        font-weight: 700;
        letter-spacing: 0.2em;
        transform: rotate(-8deg);
        background: rgba(255, 255, 255, 0.88);
      }

      .meta-block {
        margin: 14px 0 22px;
        font-size: 13px;
        line-height: 1.6;
      }

      table {
        width: 100%;
        border-collapse: collapse;
      }

      th,
      td {
        text-align: left;
        padding: 8px 6px;
        border-bottom: 1px solid #dee2e6;
        font-size: 13px;
      }

      th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }

      .muted {
        color: #6c757d;
        font-size: 11px;
      }
    </style>
  </head>
  <body>
    <img src="https://nivasity.com/nivasity.png" alt="Nivasity watermark" class="watermark">
    <div class="granted-stamp">GRANTED</div>

    <center><h2 style="text-transform: uppercase; margin-top: 10px;">PAYMENTS FOR <?php echo h($auditRow['course_code']); ?> MANUAL</h2></center>

    <div class="meta-block">
      <p><strong>Verification Code:</strong> <?php echo h($auditRow['code']); ?></p>
      <p><strong>Total Students:</strong> <?php echo (int)$auditRow['students_count']; ?> &nbsp;&nbsp; <strong>Total Amount:</strong> &#8358; <?php echo formatAmount($auditRow['total_amount']); ?></p>
      <p><strong>Date Exported:</strong> <?php echo h(formatDateTimeReadable($auditRow['downloaded_at'])); ?></p>
      <p><strong>Date Granted:</strong> <?php echo h(formatDateTimeReadable($auditRow['granted_at'])); ?></p>
      <p><strong>HOC:</strong> <?php echo h(trim((string)$auditRow['hoc_name'])); ?><?php if (!empty($auditRow['hoc_email'])): ?> (<?php echo h($auditRow['hoc_email']); ?>)<?php endif; ?></p>
      <p><strong>Granted By:</strong> <?php echo h(trim((string)$auditRow['granted_by_name']) !== '' ? $auditRow['granted_by_name'] : '-'); ?></p>
      <p class="muted">You can verify this export at <?php echo h($verificationUrl); ?></p>
    </div>

    <table>
      <tr>
        <th>S/N</th>
        <th>Names</th>
        <th>Matric No</th>
        <th>Admission Year</th>
        <th>Price Paid</th>
      </tr>
      <?php if (!empty($rows)): ?>
        <?php foreach ($rows as $index => $row): ?>
        <tr>
          <td><?php echo $index + 1; ?></td>
          <td><?php echo h($row['name']); ?></td>
          <td><?php echo h($row['matric_no']); ?></td>
          <td><?php echo h($row['adm_year']); ?></td>
          <td><?php echo h($row['price']); ?></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="5">No students could be reconstructed for this granted export.</td>
        </tr>
      <?php endif; ?>
    </table>

    <script>
      setTimeout(function () {
        window.print();
      }, 1200);
    </script>
  </body>
  </html>
  <?php
  exit();
}

$grantedExports = [];
$tableError = '';

if ($auditStatusColumn !== '') {
  try {
    $hasGrantedBy = tableHasColumn($conn, 'manual_export_audits', 'granted_by');
    $hasGrantedAt = tableHasColumn($conn, 'manual_export_audits', 'granted_at');
    $grantedBySelect = $hasGrantedBy ? 'a.granted_by' : 'NULL AS granted_by';
    $grantedAtSelect = $hasGrantedAt ? 'a.granted_at' : 'NULL AS granted_at';
    $grantedByNameSelect = $hasGrantedBy
      ? "TRIM(CONCAT(COALESCE(ad.first_name, ''), ' ', COALESCE(ad.last_name, ''))) AS granted_by_name"
      : "'' AS granted_by_name";
    $grantedByJoin = $hasGrantedBy ? 'LEFT JOIN admins AS ad ON ad.id = a.granted_by' : '';
    $sortColumn = $hasGrantedAt ? 'COALESCE(a.granted_at, a.downloaded_at)' : 'a.downloaded_at';

    $grantedExports = runSelectAll(
      $conn,
      "
      SELECT
        a.id,
        a.code,
        a.students_count,
        a.total_amount,
        a.downloaded_at,
        $grantedAtSelect,
        $grantedBySelect,
        $grantedByNameSelect,
        m.title AS manual_title,
        m.course_code,
        m.code AS manual_code
      FROM manual_export_audits AS a
      JOIN manuals AS m ON m.id = a.manual_id
      $grantedByJoin
      WHERE a.hoc_user_id = " . (int)$user_id . "
        AND " . grantedStatusSql("a.`$auditStatusColumn`") . "
      ORDER BY $sortColumn DESC, a.id DESC
      "
    );
  } catch (Throwable $e) {
    $tableError = 'Unable to load granted exports right now.';
    error_log('[admin/granted_exports] ' . $e->getMessage());
  }
} else {
  $tableError = 'Grant tracking is not available in this environment yet.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Granted Exports - Nivasity</title>

  <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="../assets/vendors/simple-line-icons/css/simple-line-icons.css">
  <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../assets/css/dashboard/style.min.css">

  <script src="../assets/js/main.js"></script>
  <script src="https://accounts.google.com/gsi/client" async defer></script>

  <link rel="shortcut icon" href="../favicon.ico" />

  <script async src="https://www.googletagmanager.com/gtag/js?id=G-30QJ6DSHBN"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-30QJ6DSHBN');
  </script>
</head>

<body>
  <div class="container-scroller sidebar-fixed">
    <?php include('../partials/_navbar.php') ?>
    <div class="container-fluid page-body-wrapper">
      <?php include('../partials/_sidebar_admin.php') ?>
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row flex-grow">
            <div class="col-12 grid-margin stretch-card">
              <div class="card card-rounded shadow-sm">
                <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                  <div>
                    <h4 class="fw-bold my-2">Granted Exports</h4>
                    <p class="mb-0 text-muted">Re-open previously granted material export lists with the original export code and grant date.</p>
                  </div>
                  <div class="badge bg-dark px-3 py-2">Total: <?php echo count($grantedExports); ?></div>
                </div>
                <div class="card-body">
                  <?php if ($tableError !== ''): ?>
                    <div class="alert alert-warning"><?php echo h($tableError); ?></div>
                  <?php endif; ?>

                  <div class="table-responsive mt-1">
                    <table id="granted_exports_table" class="table table-hover table-striped select-table datatable-opt">
                      <thead>
                        <tr>
                          <th>Export Code</th>
                          <th>Manual</th>
                          <th>Students</th>
                          <th>Total Amount</th>
                          <th>Date Exported</th>
                          <th>Date Granted</th>
                          <th>Granted By</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($grantedExports as $export): ?>
                        <tr>
                          <td>
                            <h6 class="mb-1 text-uppercase"><?php echo h($export['code']); ?></h6>
                            <p class="mb-0 text-muted small">Manual Ref: <?php echo h($export['manual_code']); ?></p>
                          </td>
                          <td>
                            <h6 class="mb-1 text-uppercase text-secondary"><?php echo h($export['course_code']); ?></h6>
                            <p class="mb-0"><?php echo h($export['manual_title']); ?></p>
                          </td>
                          <td>
                            <span class="fw-bold"><?php echo (int)$export['students_count']; ?></span>
                          </td>
                          <td>
                            &#8358; <?php echo formatAmount($export['total_amount']); ?>
                          </td>
                          <td>
                            <h6 class="mb-1"><?php echo h(formatDateTimeReadable($export['downloaded_at'])); ?></h6>
                          </td>
                          <td>
                            <h6 class="mb-1 text-success"><?php echo h(formatDateTimeReadable($export['granted_at'])); ?></h6>
                          </td>
                          <td>
                            <?php echo h(trim((string)$export['granted_by_name']) !== '' ? $export['granted_by_name'] : '-'); ?>
                          </td>
                          <td>
                            <a class="btn btn-dark btn-sm" href="granted_exports.php?download=<?php echo (int)$export['id']; ?>" target="_blank" rel="noopener">
                              <i class="mdi mdi-download me-1"></i> Download list
                            </a>
                          </td>
                        </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>

                  <?php if (empty($grantedExports) && $tableError === ''): ?>
                    <div class="alert alert-info mt-3 mb-0">No granted exports have been recorded for this account yet.</div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <?php include('../partials/_footer.php') ?>
      </div>
    </div>
  </div>

  <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.1/mdb.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap4.min.js"></script>
  <script src="../assets/js/js/off-canvas.js"></script>
  <script src="../assets/js/js/hoverable-collapse.js"></script>
  <script src="../assets/js/js/template.js"></script>
  <script src="../assets/js/js/settings.js"></script>
  <script src="../assets/js/js/data-table.js"></script>
  <script src="../assets/js/script.js"></script>
  <script>
    $(document).ready(function () {
      $('.btn').attr('data-mdb-ripple-duration', '0');
    });
  </script>
</body>

</html>