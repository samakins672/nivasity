<?php
session_start();
require_once __DIR__ . '/model/config.php';

$rawCode = isset($_GET['code']) ? trim($_GET['code']) : '';
$code = strtoupper($rawCode);
$record = null;
$error = null;

if ($code !== '') {
  $safeCode = mysqli_real_escape_string($conn, $code);

  $sql = "
    SELECT 
      a.code,
      a.students_count,
      a.total_amount,
      a.downloaded_at,
      m.title AS manual_title,
      m.course_code,
      m.code AS manual_internal_code,
      u.first_name,
      u.last_name,
      u.email,
      d.name AS dept_name,
      s.name AS school_name
    FROM manual_export_audits AS a
    JOIN manuals AS m ON m.id = a.manual_id
    JOIN users AS u ON u.id = a.hoc_user_id
    LEFT JOIN depts AS d ON d.id = u.dept
    LEFT JOIN schools AS s ON s.id = u.school
    WHERE a.code = '$safeCode'
    LIMIT 1
  ";

  $res = mysqli_query($conn, $sql);
  if ($res && mysqli_num_rows($res) > 0) {
    $record = mysqli_fetch_assoc($res);
  } else {
    $error = 'No export record found for this code.';
  }
}

function h($v) {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function formatAmount($amount) {
  $amount = (int)$amount;
  return number_format($amount);
}

function formatDateTimeReadable($dt) {
  if (!$dt) {
    return '';
  }
  $ts = strtotime($dt);
  if ($ts === false) {
    return $dt;
  }
  return date('j M Y, g:ia', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manual Export Verification - Nivasity</title>
  <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">

  <!-- Open Graph Meta Tags -->
  <meta property="og:title" content="Manual Export Verification - Nivasity">
  <meta property="og:description" content="Verify manual export summaries using the code printed on the PDF.">
  <meta property="og:image" content="https://funaab.nivasity.com/assets/images/nivasity-main.png">
  <meta property="og:url" content="https://funaab.nivasity.com/manual-export-verify.php">
  <meta property="og:type" content="website">

  <!-- Twitter Meta Tags -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Manual Export Verification - Nivasity">
  <meta name="twitter:description" content="Verify manual export summaries using the code printed on the PDF.">
  <meta name="twitter:image" content="https://funaab.nivasity.com/assets/images/nivasity-main.png">

  <!-- Styles -->
  <link href="assets/css/mdb.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/vendors/mdi/css/materialdesignicons.min.css">
  <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&display=swap" rel="stylesheet" />
  <link href="assets/css/style.css" rel="stylesheet">

  <style>
    body {
      background: #f5f7fb;
    }

    .verify-card {
      max-width: 900px;
      margin: 40px auto;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
    }

    .verify-header-logo img {
      max-height: 48px;
    }

    .meta-label {
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      color: #6c757d;
      margin-bottom: 0.15rem;
    }

    .meta-value {
      font-weight: 600;
      font-size: 1rem;
      color: #212529;
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="card verify-card bg-white">
      <div class="card-body p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="verify-header-logo">
            <img src="assets/images/nivasity-main.png" alt="Nivasity">
          </div>
          <div class="text-end">
            <div class="meta-label">Page</div>
            <div class="meta-value">Manual Export Verification</div>
          </div>
        </div>

        <h4 class="fw-bold mb-3">Verify Manual Export</h4>
        <p class="text-muted mb-4">
          Enter the verification code printed at the top of the exported list from a Head of Class (HOC) dashboard.
          This page shows only summary information &mdash; not individual student details.
        </p>

        <form method="get" class="mb-4">
          <div class="row g-2 align-items-end">
            <div class="col-12 col-md-8">
              <div class="form-outline">
                <input type="text" id="code" name="code" class="form-control form-control-lg"
                  value="<?php echo h($rawCode); ?>" required>
                <label class="form-label" for="code">Verification Code</label>
              </div>
            </div>
            <div class="col-12 col-md-4 text-md-end mt-3 mt-md-0">
              <button type="submit" class="btn btn-lg btn-primary w-100">
                Verify
              </button>
            </div>
          </div>
        </form>

        <?php if ($code === ''): ?>
          <p class="text-muted mb-0">
            Example: the code usually looks like a short mix of letters and numbers (e.g. <span
              class="fw-bold">A7K9Q2L8M3</span>) and appears
            near the top of the exported PDF/printed sheet.
          </p>
        <?php elseif ($error): ?>
          <div class="alert alert-danger mb-0">
            <?php echo h($error); ?>
          </div>
        <?php elseif ($record): ?>
          <div class="border-top pt-4 mt-4">
            <h5 class="fw-bold mb-3">Result for Code: <?php echo h($record['code']); ?></h5>

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <div class="meta-label">Manual</div>
                <div class="meta-value">
                  <?php echo h($record['course_code']); ?> &mdash;
                  <?php echo h($record['manual_title']); ?>
                </div>
                <?php if (!empty($record['manual_internal_code'])): ?>
                  <div class="text-muted small">
                    Internal ID: <?php echo h($record['manual_internal_code']); ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="col-md-6">
                <div class="meta-label">HOC</div>
                <div class="meta-value">
                  <?php echo h(trim($record['first_name'] . ' ' . $record['last_name'])); ?>
                </div>
                <div class="text-muted small">
                  <?php if (!empty($record['dept_name'])): ?>
                    <?php echo h($record['dept_name']); ?>
                  <?php endif; ?>
                  <?php if (!empty($record['school_name'])): ?>
                    <?php if (!empty($record['dept_name'])): ?> &middot; <?php endif; ?>
                    <?php echo h($record['school_name']); ?>
                  <?php endif; ?>
                </div>
                <?php if (!empty($record['email'])): ?>
                  <div class="text-muted small">
                    <?php echo h($record['email']); ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <div class="meta-label">Total Students</div>
                <div class="meta-value">
                  <?php echo (int)$record['students_count']; ?>
                </div>
              </div>
              <div class="col-md-4">
                <div class="meta-label">Total Amount</div>
                <div class="meta-value">
                  &#8358; <?php echo formatAmount($record['total_amount']); ?>
                </div>
              </div>
              <div class="col-md-4">
                <div class="meta-label">Date Exported</div>
                <div class="meta-value">
                  <?php echo h(formatDateTimeReadable($record['downloaded_at'])); ?>
                </div>
              </div>
            </div>

            <p class="text-muted small mt-5 mb-0">
              If the details above match the header information on the printed/PDF list you received, the export is
              valid as issued from the Nivasity HOC dashboard.
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
  <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.1/mdb.min.js"></script>
</body>

</html>
