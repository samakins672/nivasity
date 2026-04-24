<?php
session_start();
include('model/config.php');
include('model/page_config.php');
require_once 'model/bulk_material_payment_service.php';
require_once 'model/material_copy_status.php';

if (!function_exists('bulk_material_payment_preview_parse_upload')) {
  function bulk_material_payment_preview_parse_upload(array $file): array
  {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
      return [
        'ok' => false,
        'message' => 'Upload a CSV file before previewing your batch.',
      ];
    }

    $handle = fopen($file['tmp_name'], 'r');
    if ($handle === false) {
      return [
        'ok' => false,
        'message' => 'We could not read the uploaded CSV file.',
      ];
    }

    $headers = fgetcsv($handle);
    if (!is_array($headers)) {
      fclose($handle);
      return [
        'ok' => false,
        'message' => 'The CSV file is empty. Download the template and try again.',
      ];
    }

    $normalizedHeaders = [];
    foreach ($headers as $index => $header) {
      $header = (string) $header;
      if ($index === 0) {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
      }
      $normalizedHeaders[] = bulk_material_payment_normalize_text($header);
    }

    $expectedHeaders = bulk_material_payment_format_csv_header();
    if ($normalizedHeaders !== $expectedHeaders) {
      fclose($handle);
      return [
        'ok' => false,
        'message' => 'Use the official CSV template with headers: ' . implode(', ', $expectedHeaders) . '.',
      ];
    }

    $rows = [];
    $lineNumber = 1;
    while (($data = fgetcsv($handle)) !== false) {
      $lineNumber++;
      if (!is_array($data)) {
        continue;
      }

      $firstName = trim((string) ($data[0] ?? ''));
      $lastName = trim((string) ($data[1] ?? ''));
      $matricNo = trim((string) ($data[2] ?? ''));

      if ($firstName === '' && $lastName === '' && $matricNo === '') {
        continue;
      }

      $rows[] = [
        'line_number' => $lineNumber,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'matric_no' => $matricNo,
        'normalized_first_name' => bulk_material_payment_normalize_text($firstName),
        'normalized_last_name' => bulk_material_payment_normalize_text($lastName),
        'normalized_matric_no' => bulk_material_payment_normalize_text($matricNo),
      ];
    }

    fclose($handle);

    return [
      'ok' => true,
      'rows' => $rows,
    ];
  }
}

if (!function_exists('bulk_material_payment_preview_analyze_rows')) {
  function bulk_material_payment_preview_analyze_rows(mysqli $conn, array $rows, array $manual, array $payer): array
  {
    $manualId = (int) ($manual['id'] ?? 0);
    $schoolId = (int) ($payer['school'] ?? 0);
    $payerDeptId = (int) ($payer['dept'] ?? 0);
    $manualPrice = (int) round((float) ($manual['price'] ?? 0));
    $nonLostCondition = material_copy_non_lost_condition($conn, 'mb');

    $results = [];
    $errors = [];
    $validCount = 0;
    $seenIdentities = [];
    $seenMatricNames = [];

    foreach ($rows as $row) {
      $lineNumber = (int) ($row['line_number'] ?? 0);
      $firstName = (string) ($row['first_name'] ?? '');
      $lastName = (string) ($row['last_name'] ?? '');
      $matricNo = (string) ($row['matric_no'] ?? '');
      $normalizedFirstName = (string) ($row['normalized_first_name'] ?? '');
      $normalizedLastName = (string) ($row['normalized_last_name'] ?? '');
      $normalizedMatricNo = (string) ($row['normalized_matric_no'] ?? '');
      $status = 'valid';
      $resolution = 'pending_placeholder';
      $message = 'Will create a pending placeholder until the student confirms the claim.';
      $matchedUserId = 0;

      if ($normalizedFirstName === '' || $normalizedLastName === '' || $normalizedMatricNo === '') {
        $status = 'error';
        $message = 'First name, last name, and matric number are required.';
      }

      $identityKey = $normalizedMatricNo . '|' . $normalizedFirstName . '|' . $normalizedLastName;
      if ($status === 'valid' && isset($seenIdentities[$identityKey])) {
        $status = 'error';
        $message = 'This student appears more than once in the uploaded CSV.';
      }

      if ($status === 'valid') {
        if (isset($seenMatricNames[$normalizedMatricNo]) && $seenMatricNames[$normalizedMatricNo] !== ($normalizedFirstName . '|' . $normalizedLastName)) {
          $status = 'error';
          $message = 'The same matric number is paired with different names in this CSV.';
        } else {
          $seenIdentities[$identityKey] = true;
          $seenMatricNames[$normalizedMatricNo] = $normalizedFirstName . '|' . $normalizedLastName;
        }
      }

      if ($status === 'valid' && bulk_material_payment_has_table($conn, 'manual_bulk_payment_students')) {
        $matricSafe = mysqli_real_escape_string($conn, $normalizedMatricNo);
        $firstSafe = mysqli_real_escape_string($conn, $normalizedFirstName);
        $lastSafe = mysqli_real_escape_string($conn, $normalizedLastName);
        $pendingQuery = mysqli_query(
          $conn,
          "SELECT id FROM manual_bulk_payment_students
           WHERE manual_id = {$manualId}
             AND school_id = {$schoolId}
             AND payer_dept_id = {$payerDeptId}
             AND normalized_matric_no = '{$matricSafe}'
             AND normalized_first_name = '{$firstSafe}'
             AND normalized_last_name = '{$lastSafe}'
             AND claim_status IN ('pending', 'awaiting_claim_confirmation', 'awaiting_student_confirmation')
           LIMIT 1"
        );
        if ($pendingQuery && mysqli_num_rows($pendingQuery) > 0) {
          $status = 'error';
          $message = 'This student already has a pending bulk-payment claim for the selected material.';
        }
      }

      if ($status === 'valid') {
        $matricSafe = mysqli_real_escape_string($conn, $normalizedMatricNo);
        $userQuery = mysqli_query(
          $conn,
          "SELECT id, first_name, last_name, dept, status
           FROM users
           WHERE school = {$schoolId}
             AND dept = {$payerDeptId}
             AND LOWER(TRIM(matric_no)) = '{$matricSafe}'
             AND status <> '" . mysqli_real_escape_string($conn, bulk_material_payment_placeholder_status()) . "'
           ORDER BY CASE WHEN status = 'verified' THEN 0 ELSE 1 END, id DESC
           LIMIT 1"
        );
        if ($userQuery && mysqli_num_rows($userQuery) > 0) {
          $user = mysqli_fetch_assoc($userQuery) ?: [];
          $matchedFirstName = bulk_material_payment_normalize_text((string) ($user['first_name'] ?? ''));
          $matchedLastName = bulk_material_payment_normalize_text((string) ($user['last_name'] ?? ''));
          if ($matchedFirstName !== $normalizedFirstName || $matchedLastName !== $normalizedLastName) {
            $status = 'error';
            $message = 'Matric number matched an existing student, but the first or last name did not match.';
          } else {
            $matchedUserId = (int) ($user['id'] ?? 0);
            $resolution = 'existing_student';
            $message = 'Matches an existing student profile in your department.';
          }
        }
      }

      if ($status === 'valid' && $matchedUserId > 0) {
        $ownershipQuery = mysqli_query(
          $conn,
          "SELECT 1 FROM manuals_bought AS mb
           WHERE mb.manual_id = {$manualId}
             AND mb.buyer = {$matchedUserId}
             AND {$nonLostCondition}
           LIMIT 1"
        );
        if ($ownershipQuery && mysqli_num_rows($ownershipQuery) > 0) {
          $status = 'error';
          $message = 'This student already owns an active copy of the selected material.';
        }
      }

      if ($status === 'valid') {
        $validCount++;
      } else {
        $errors[] = 'Line ' . $lineNumber . ': ' . $message;
      }

      $results[] = [
        'line_number' => $lineNumber,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'matric_no' => $matricNo,
        'status' => $status,
        'resolution' => $resolution,
        'message' => $message,
      ];
    }

    $breakdown = bulk_material_payment_fee_breakdown($validCount * $manualPrice, 5.0);

    return [
      'rows' => $results,
      'errors' => $errors,
      'valid_count' => $validCount,
      'invalid_count' => count($results) - $validCount,
      'breakdown' => $breakdown,
    ];
  }
}

$currentUserRole = (string) ($_SESSION['nivas_userRole'] ?? '');
$isStudentType = in_array($currentUserRole, ['student', 'hoc'], true);
$schemaReady = bulk_material_payment_ensure_schema($conn);
$wallet = nivasityGetUserWallet($conn, (int) $user_id);
$hasWalletPin = nivasityUserHasWalletPin($conn, (int) $user_id);
$manualId = isset($_GET['manual_id']) ? (int) $_GET['manual_id'] : 0;
$manual = null;
$pageError = '';
$pageWarnings = [];
$previewResult = null;

if (!$isStudentType) {
  $pageError = 'Only student and HOC accounts can start a bulk material payment.';
}

if ($schemaReady === false && $pageError === '') {
  $pageError = 'Bulk payment tables are not ready yet. Run the latest SQL migration first.';
}

if ($manualId <= 0 && $pageError === '') {
  $pageError = 'Select a valid material before starting a bulk payment.';
}

if ($pageError === '') {
  $manualQuery = mysqli_query($conn, "SELECT * FROM manuals WHERE id = {$manualId} AND school_id = {$school_id} LIMIT 1");
  if (!$manualQuery || mysqli_num_rows($manualQuery) < 1) {
    $pageError = 'The selected material could not be found for your school.';
  } else {
    $manual = mysqli_fetch_assoc($manualQuery) ?: null;
  }
}

if ($pageError === '' && $manual) {
  $dueDate = strtotime((string) ($manual['due_date'] ?? ''));
  if ((string) ($manual['status'] ?? '') !== 'open' || ($dueDate !== false && time() > $dueDate)) {
    $pageError = 'This material is closed and cannot be used for bulk payment.';
  }
}

if ($pageError === '' && (int) $user_dept <= 0) {
  $pageWarnings[] = 'Set your department on your profile before paying for students in bulk.';
}
if ($wallet === null) {
  $pageWarnings[] = 'Request your wallet before moving to wallet payment.';
}
if (!$hasWalletPin) {
  $pageWarnings[] = 'Create your Wallet PIN before wallet payment is enabled.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_bulk_payment']) && $pageError === '') {
  $parsedUpload = bulk_material_payment_preview_parse_upload($_FILES['bulk_csv'] ?? []);
  if (!$parsedUpload['ok']) {
    $pageError = (string) ($parsedUpload['message'] ?? 'Unable to preview the uploaded CSV right now.');
  } else {
    $previewResult = bulk_material_payment_preview_analyze_rows($conn, $parsedUpload['rows'] ?? [], $manual ?: [], [
      'school' => $school_id,
      'dept' => $user_dept,
    ]);
  }
}

$templateUrl = nivasity_asset_url('assets/templates/manual-bulk-payment-template.csv');
$walletPageUrl = nivasity_app_url('wallet.php');
$manualTitle = (string) ($manual['title'] ?? 'Selected Material');
$manualCode = (string) ($manual['course_code'] ?? '');
$manualPrice = (int) round((float) ($manual['price'] ?? 0));
$walletBalance = (int) ($wallet['balance'] ?? 0);
$walletReady = $wallet !== null && $hasWalletPin && (int) $user_dept > 0 && (string) $user_status === 'verified';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Bulk Material Payment</title>
  <?php include('partials/_head.php') ?>
  <style>
    .bulk-summary-card {
      border: 0;
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.08) 0%, rgba(15, 118, 110, 0.08) 100%);
    }

    .bulk-dropzone {
      border: 1px dashed rgba(13, 110, 253, 0.45);
      border-radius: 1rem;
      padding: 1.25rem;
      background: rgba(13, 110, 253, 0.03);
    }

    .bulk-table-status-ok {
      color: #198754;
      font-weight: 700;
    }

    .bulk-table-status-error {
      color: #dc3545;
      font-weight: 700;
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
                  <div class="tab-pane fade show active" role="tabpanel">
                    <div class="row g-3">
                      <div class="col-12 col-xl-5">
                        <div class="card card-rounded shadow-sm bulk-summary-card h-100">
                          <div class="card-body p-4">
                            <p class="text-uppercase text-primary fw-bold small mb-2">Bulk Material Payment</p>
                            <h3 class="fw-bold mb-2"><?php echo htmlspecialchars($manualTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($manualCode, ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                              <span class="text-muted">Single material price</span>
                              <strong>₦ <?php echo number_format($manualPrice); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                              <span class="text-muted">Bulk fee</span>
                              <strong>5%</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                              <span class="text-muted">Wallet balance</span>
                              <strong>₦ <?php echo number_format($walletBalance); ?></strong>
                            </div>
                            <a href="<?php echo htmlspecialchars($templateUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary fw-bold w-100" download>Download CSV Template</a>
                            <div class="mt-3 small text-muted">
                              Upload the filled template with <strong>first name</strong>, <strong>last name</strong>, and <strong>matric number</strong> for each student.
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="col-12 col-xl-7">
                        <div class="card card-rounded shadow-sm h-100">
                          <div class="card-body p-4">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                              <div>
                                <h4 class="fw-bold mb-1">Preview Your Batch</h4>
                                <p class="text-muted mb-0">Validate the CSV before wallet payment is enabled.</p>
                              </div>
                              <a href="<?php echo htmlspecialchars($walletPageUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-light fw-bold">Go to Wallet</a>
                            </div>

                            <?php if ($pageError !== ''): ?>
                              <div class="alert alert-danger"><?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>

                            <?php foreach ($pageWarnings as $warning): ?>
                              <div class="alert alert-warning mb-2"><?php echo htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endforeach; ?>

                            <form method="post" enctype="multipart/form-data" class="bulk-dropzone">
                              <input type="hidden" name="preview_bulk_payment" value="1">
                              <div class="mb-3">
                                <label for="bulk_csv" class="form-label fw-bold">Upload CSV</label>
                                <input type="file" class="form-control" id="bulk_csv" name="bulk_csv" accept=".csv,text/csv" required>
                              </div>
                              <button type="submit" class="btn btn-primary fw-bold">Preview Batch</button>
                              <span class="ms-2 text-muted small">Wallet debit and batch saving will be wired after preview validation.</span>
                            </form>

                            <?php if (is_array($previewResult)): ?>
                              <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                  <div class="border rounded-3 p-3 h-100">
                                    <p class="text-muted mb-1">Valid students</p>
                                    <h4 class="fw-bold mb-0"><?php echo number_format((int) ($previewResult['valid_count'] ?? 0)); ?></h4>
                                  </div>
                                </div>
                                <div class="col-md-4">
                                  <div class="border rounded-3 p-3 h-100">
                                    <p class="text-muted mb-1">Subtotal</p>
                                    <h4 class="fw-bold mb-0">₦ <?php echo number_format((int) ($previewResult['breakdown']['subtotal'] ?? 0)); ?></h4>
                                  </div>
                                </div>
                                <div class="col-md-4">
                                  <div class="border rounded-3 p-3 h-100">
                                    <p class="text-muted mb-1">Total with fee</p>
                                    <h4 class="fw-bold mb-0">₦ <?php echo number_format((int) ($previewResult['breakdown']['total_amount'] ?? 0)); ?></h4>
                                  </div>
                                </div>
                              </div>

                              <?php if (!empty($previewResult['errors'])): ?>
                                <div class="alert alert-warning mt-3 mb-0">
                                  <div class="fw-bold mb-2">Rows that need attention</div>
                                  <ul class="mb-0 ps-3">
                                    <?php foreach (($previewResult['errors'] ?? []) as $error): ?>
                                      <li><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></li>
                                    <?php endforeach; ?>
                                  </ul>
                                </div>
                              <?php endif; ?>

                              <div class="table-responsive mt-3">
                                <table class="table table-striped align-middle">
                                  <thead>
                                    <tr>
                                      <th>Line</th>
                                      <th>Student</th>
                                      <th>Matric No.</th>
                                      <th>Resolution</th>
                                      <th>Status</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <?php foreach (($previewResult['rows'] ?? []) as $row): ?>
                                      <tr>
                                        <td><?php echo (int) ($row['line_number'] ?? 0); ?></td>
                                        <td><?php echo htmlspecialchars(trim(((string) ($row['first_name'] ?? '')) . ' ' . ((string) ($row['last_name'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['matric_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($row['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="<?php echo (($row['status'] ?? '') === 'valid') ? 'bulk-table-status-ok' : 'bulk-table-status-error'; ?>">
                                          <?php echo (($row['status'] ?? '') === 'valid') ? 'Ready' : 'Fix row'; ?>
                                        </td>
                                      </tr>
                                    <?php endforeach; ?>
                                  </tbody>
                                </table>
                              </div>

                              <div class="alert alert-info mt-3 mb-0">
                                <?php if ($walletReady): ?>
                                  Wallet payment checks passed for this account. The next implementation slice will turn this validated preview into a saved batch and wallet debit.
                                <?php else: ?>
                                  Complete the wallet prerequisites above before wallet payment can be enabled for this batch.
                                <?php endif; ?>
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
      </div>
    </div>
  </div>
</body>

</html>