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

if (!function_exists('bulk_material_payment_json_response')) {
  function bulk_material_payment_json_response(string $status, string $message, array $data = [], int $httpStatus = 200): void
  {
    http_response_code($httpStatus);
    header('Content-Type: application/json');
    echo json_encode([
      'status' => $status,
      'message' => $message,
      'data' => $data,
    ]);
    exit;
  }
}

if (!function_exists('bulk_material_payment_build_preview_payload')) {
  function bulk_material_payment_build_preview_payload(array $previewResult, array $manual, array $pageWarnings, bool $walletReady, int $walletBalance): array
  {
    $previewTotalAmount = (int) ($previewResult['breakdown']['total_amount'] ?? 0);
    $canSubmitPayment = ((int) ($previewResult['invalid_count'] ?? 0)) === 0
      && ((int) ($previewResult['valid_count'] ?? 0)) > 0
      && $walletReady;

    return [
      'manual' => [
        'title' => (string) ($manual['title'] ?? ''),
        'course_code' => (string) ($manual['course_code'] ?? ''),
      ],
      'preview' => $previewResult,
      'wallet' => [
        'ready' => $walletReady,
        'balance' => $walletBalance,
        'has_enough_balance' => $walletBalance >= $previewTotalAmount,
      ],
      'preview_rows' => array_values($previewResult['source_rows'] ?? []),
      'page_warnings' => array_values($pageWarnings),
      'can_submit_payment' => $canSubmitPayment,
      'wallet_page_url' => nivasity_app_url('wallet.php'),
    ];
  }
}

if (!function_exists('bulk_material_payment_decode_preview_rows')) {
  function bulk_material_payment_decode_preview_rows(string $payload): array
  {
    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
      return [];
    }

    $rows = [];
    foreach ($decoded as $row) {
      if (!is_array($row)) {
        continue;
      }

      $firstName = trim((string) ($row['first_name'] ?? ''));
      $lastName = trim((string) ($row['last_name'] ?? ''));
      $matricNo = trim((string) ($row['matric_no'] ?? ''));

      if ($firstName === '' && $lastName === '' && $matricNo === '') {
        continue;
      }

      $rows[] = [
        'line_number' => (int) ($row['line_number'] ?? 0),
        'first_name' => $firstName,
        'last_name' => $lastName,
        'matric_no' => $matricNo,
        'normalized_first_name' => bulk_material_payment_normalize_text((string) ($row['normalized_first_name'] ?? $firstName)),
        'normalized_last_name' => bulk_material_payment_normalize_text((string) ($row['normalized_last_name'] ?? $lastName)),
        'normalized_matric_no' => bulk_material_payment_normalize_text((string) ($row['normalized_matric_no'] ?? $matricNo)),
      ];
    }

    return $rows;
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
$previewRows = [];
$paymentSuccess = null;
$initialPreviewResponse = null;

if (isset($_SESSION['bulk_material_payment_flash']) && is_array($_SESSION['bulk_material_payment_flash'])) {
  $flash = $_SESSION['bulk_material_payment_flash'];
  if ((int) ($flash['manual_id'] ?? 0) === $manualId && (int) ($flash['user_id'] ?? 0) === (int) $user_id) {
    $paymentSuccess = $flash;
    unset($_SESSION['bulk_material_payment_flash']);
  }
}

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

$walletBalance = (int) ($wallet['balance'] ?? 0);
$walletReady = $wallet !== null && $hasWalletPin && (int) $user_dept > 0 && (string) $user_status === 'verified';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_preview_bulk_payment'])) {
  if ($pageError !== '') {
    bulk_material_payment_json_response('error', $pageError, [], 422);
  }

  $parsedUpload = bulk_material_payment_preview_parse_upload($_FILES['bulk_csv'] ?? []);
  if (!$parsedUpload['ok']) {
    bulk_material_payment_json_response('error', (string) ($parsedUpload['message'] ?? 'Unable to preview the uploaded CSV right now.'), [], 422);
  }

  $previewRows = $parsedUpload['rows'] ?? [];
  $previewResult = bulk_material_payment_preview_analyze_rows($conn, $previewRows, $manual ?: [], [
    'school' => $school_id,
    'dept' => $user_dept,
  ]);
  $previewResult['source_rows'] = $previewRows;

  bulk_material_payment_json_response(
    'success',
    ((int) ($previewResult['invalid_count'] ?? 0)) > 0
      ? 'Preview loaded. Fix the highlighted rows before payment.'
      : 'Preview loaded successfully.',
    bulk_material_payment_build_preview_payload($previewResult, $manual ?: [], $pageWarnings, $walletReady, $walletBalance)
  );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_bulk_payment']) && $pageError === '') {
  $previewRows = bulk_material_payment_decode_preview_rows((string) ($_POST['preview_payload'] ?? ''));
  if (empty($previewRows)) {
    $pageError = 'Preview the CSV again before paying from your wallet.';
  } else {
    $previewResult = bulk_material_payment_preview_analyze_rows($conn, $previewRows, $manual ?: [], [
      'school' => $school_id,
      'dept' => $user_dept,
    ]);
    $previewResult['source_rows'] = $previewRows;
    $initialPreviewResponse = [
      'status' => 'success',
      'message' => 'Preview restored for checkout.',
      'data' => bulk_material_payment_build_preview_payload($previewResult, $manual ?: [], $pageWarnings, $walletReady, $walletBalance),
    ];

    if (((int) ($previewResult['invalid_count'] ?? 0)) > 0 || ((int) ($previewResult['valid_count'] ?? 0)) < 1) {
      $pageError = 'Fix the CSV preview errors before paying from your wallet.';
    } elseif (!$walletReady) {
      $pageError = 'Complete the wallet prerequisites before paying for this batch.';
    } else {
      try {
        $paymentResult = bulk_material_payment_process_wallet_batch(
          $conn,
          [
            'id' => $user_id,
            'school' => $school_id,
            'dept' => $user_dept,
          ],
          $manual ?: [],
          $previewRows,
          trim((string) ($_POST['wallet_pin'] ?? '')),
          'web'
        );
        $_SESSION['bulk_material_payment_flash'] = array_merge($paymentResult, [
          'manual_id' => $manualId,
          'user_id' => (int) $user_id,
        ]);
        header('Location: bulk_material_payment.php?manual_id=' . $manualId . '&paid=1');
        exit;
      } catch (Throwable $e) {
        $pageError = $e->getMessage();
      }
    }
  }
}

$templateUrl = nivasity_asset_url('assets/templates/manual-bulk-payment-template.csv');
$walletPageUrl = nivasity_app_url('wallet.php');
$manualTitle = (string) ($manual['title'] ?? 'Selected Material');
$manualCode = (string) ($manual['course_code'] ?? '');
$manualPrice = (int) round((float) ($manual['price'] ?? 0));
$storeUrl = nivasity_app_url();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Bulk Material Payment</title>
  <?php include('partials/_head.php') ?>
  <style>
    .bulk-page {
      --bulk-accent: var(--bs-secondary, #7A3B73);
      --bulk-accent-rgb: 122, 59, 115;
    }

    .bulk-page .text-primary {
      color: var(--bulk-accent) !important;
    }

    .bulk-summary-card {
      border: 0;
      background: linear-gradient(135deg, var(--bulk-accent) 0%, #0f766e 100%);
      color: #fff;
    }

    .bulk-summary-copy .text-primary {
      color: rgba(255, 255, 255, 0.82) !important;
    }

    .bulk-summary-copy .text-muted {
      color: rgba(255, 255, 255, 0.82) !important;
    }

    .bulk-summary-metrics {
      display: grid;
      gap: 0.75rem;
      width: 100%;
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .bulk-summary-metric {
      border-radius: 1rem;
      padding: 1rem;
      background: rgba(255, 255, 255, 0.8);
      border: 1px solid rgba(var(--bulk-accent-rgb), 0.14);
      min-width: 0;
      color: #0f172a;
    }

    .bulk-summary-metric .text-muted {
      color: rgba(15, 23, 42, 0.72) !important;
    }

    .bulk-summary-metric .fw-bold {
      color: #0f172a;
    }

    .bulk-summary-metric-wide {
      grid-column: 1 / -1;
    }

    .bulk-section-card {
      border: 0;
      border-radius: 1.25rem;
    }

    .bulk-inline-label {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--bulk-accent);
    }

    .bulk-step-card {
      display: flex;
      gap: 0.9rem;
      align-items: flex-start;
      padding: 1rem;
      border: 1px solid rgba(var(--bulk-accent-rgb), 0.14);
      border-radius: 1rem;
      background: #fff;
    }

    .bulk-step-index {
      width: 2.15rem;
      height: 2.15rem;
      border-radius: 999px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      background: rgba(var(--bulk-accent-rgb), 0.14);
      color: var(--bulk-accent);
      font-weight: 700;
    }

    .bulk-dropzone {
      border: 1px dashed rgba(var(--bulk-accent-rgb), 0.45);
      border-radius: 1rem;
      padding: 1.25rem;
      background: rgba(var(--bulk-accent-rgb), 0.03);
    }

    .bulk-upload-note {
      border-radius: 1rem;
      background: rgba(var(--bulk-accent-rgb), 0.06);
      padding: 0.9rem 1rem;
    }

    .bulk-kpi-card {
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 1rem;
      padding: 1rem;
      background: #fff;
      height: 100%;
    }

    .bulk-result-card {
      border: 1px solid rgba(15, 23, 42, 0.08);
      border-radius: 1rem;
      padding: 1rem;
      background: #fff;
    }

    .bulk-status-pill {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.35rem 0.7rem;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 700;
      white-space: nowrap;
    }

    .bulk-status-pill-ready {
      color: #198754;
      background: rgba(25, 135, 84, 0.12);
    }

    .bulk-status-pill-fix {
      color: #dc3545;
      background: rgba(220, 53, 69, 0.12);
    }

    .bulk-result-message {
      color: #475467;
      font-size: 0.95rem;
    }

    .bulk-pay-card {
      border: 1px solid rgba(25, 135, 84, 0.12);
      border-radius: 1.15rem;
      padding: 1.25rem;
      background: linear-gradient(135deg, rgba(25, 135, 84, 0.06) 0%, rgba(var(--bulk-accent-rgb), 0.05) 100%);
    }

    .bulk-empty-state {
      border: 1px dashed rgba(15, 23, 42, 0.14);
      border-radius: 1rem;
      padding: 1rem;
      background: rgba(248, 250, 252, 0.9);
      color: #475467;
    }

    .bulk-table-status-ok {
      color: #198754;
      font-weight: 700;
    }

    .bulk-table-status-error {
      color: #dc3545;
      font-weight: 700;
    }

    @media (min-width: 992px) {
      .bulk-summary-metrics {
        width: 320px;
      }
    }

    @media (max-width: 991.98px) {
      .bulk-summary-card .card-body,
      .bulk-section-card .card-body {
        padding: 1.1rem !important;
      }

      .bulk-dropzone,
      .bulk-pay-card {
        padding: 1rem;
      }
    }
  </style>
</head>

<body class="bulk-page">
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
                      <div class="col-12">
                        <div class="card card-rounded shadow-sm bulk-summary-card">
                          <div class="card-body p-4 p-lg-5">
                            <div class="d-flex flex-column flex-lg-row justify-content-between gap-4 align-items-start">
                              <div class="pe-lg-4 bulk-summary-copy">
                                <a href="<?php echo htmlspecialchars($storeUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-light fw-bold btn-sm mb-3">
                                  <i class="mdi mdi-arrow-left"></i> Back to Store
                                </a>
                                <p class="text-uppercase text-primary fw-bold small mb-2">Bulk Material Payment</p>
                                <h2 class="fw-bold mb-2"><?php echo htmlspecialchars($manualTitle, ENT_QUOTES, 'UTF-8'); ?></h2>
                                <?php if ($manualCode !== ''): ?>
                                  <p class="text-muted mb-3"><?php echo htmlspecialchars($manualCode, ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <p class="text-muted mb-0">Upload one CSV, review which students are ready, then pay once from your wallet. Each student will confirm the claim on their own account before access is granted.</p>
                              </div>
                              <div class="bulk-summary-metrics">
                                <div class="bulk-summary-metric">
                                  <div class="small text-muted mb-1">Material price</div>
                                  <div class="fw-bold h5 mb-0">₦ <?php echo number_format($manualPrice); ?></div>
                                </div>
                                <div class="bulk-summary-metric">
                                  <div class="small text-muted mb-1">Bulk fee</div>
                                  <div class="fw-bold h5 mb-0">5%</div>
                                </div>
                                <div class="bulk-summary-metric bulk-summary-metric-wide">
                                  <div class="small text-muted mb-1">Wallet balance</div>
                                  <div class="fw-bold h5 mb-0">₦ <?php echo number_format($walletBalance); ?></div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="col-12 col-lg-5">
                        <div class="card card-rounded shadow-sm bulk-section-card h-100">
                          <div class="card-body p-4">
                            <div class="bulk-inline-label mb-3">Simple Flow</div>
                            <div class="d-grid gap-3">
                              <div class="bulk-step-card">
                                <div class="bulk-step-index">1</div>
                                <div>
                                  <h6 class="fw-bold mb-1">Download the CSV template</h6>
                                  <p class="text-muted mb-3">Use the official template so the upload works cleanly on your phone or laptop.</p>
                                  <a href="<?php echo htmlspecialchars($templateUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary fw-bold w-100" download>Download CSV Template</a>
                                </div>
                              </div>
                              <div class="bulk-step-card">
                                <div class="bulk-step-index">2</div>
                                <div>
                                  <h6 class="fw-bold mb-1">Fill one row per student</h6>
                                  <p class="text-muted mb-0">Each row must contain <strong>first name</strong>, <strong>last name</strong>, and <strong>matric number</strong>. Students are matched only inside your department.</p>
                                </div>
                              </div>
                              <div class="bulk-step-card">
                                <div class="bulk-step-index">3</div>
                                <div>
                                  <h6 class="fw-bold mb-1">Preview first, then pay once</h6>
                                  <p class="text-muted mb-0">Your preview opens in a modal so you can review the batch quickly on mobile before entering your Wallet PIN.</p>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="col-12 col-lg-7">
                        <div class="card card-rounded shadow-sm bulk-section-card h-100" id="bulkPreviewSection">
                          <div class="card-body p-4">
                            <div class="d-flex flex-column justify-content-between align-items-start gap-3 mb-4">
                              <div>
                                <div class="bulk-inline-label mb-2">Upload And Review</div>
                                <h4 class="fw-bold mb-1">Preview your batch before paying</h4>
                                <p class="text-muted mb-0">The page will only unlock wallet payment after every row is ready.</p>
                              </div>
                              <div class="bulk-upload-note w-100">
                                <div class="small text-muted mb-1">Expected columns</div>
                                <div class="fw-semibold">first_name, last_name, matric_no</div>
                              </div>
                            </div>

                            <?php if ($pageError !== ''): ?>
                              <div class="alert alert-danger"><?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>

                            <?php if (is_array($paymentSuccess)): ?>
                              <div class="alert alert-success">
                                <div class="fw-bold mb-2">Bulk payment completed successfully.</div>
                                <div>Reference: <strong><?php echo htmlspecialchars((string) ($paymentSuccess['ref_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                <div>Students queued: <strong><?php echo number_format((int) ($paymentSuccess['student_count'] ?? 0)); ?></strong></div>
                                <div>Total debited: <strong>₦ <?php echo number_format((int) ($paymentSuccess['total_amount'] ?? 0)); ?></strong></div>
                                <div>Wallet balance after payment: <strong>₦ <?php echo number_format((int) ($paymentSuccess['wallet_balance_after'] ?? 0)); ?></strong></div>
                              </div>
                            <?php endif; ?>

                            <?php foreach ($pageWarnings as $warning): ?>
                              <div class="alert alert-warning mb-2"><?php echo htmlspecialchars($warning, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endforeach; ?>

                            <div class="alert d-none" id="bulkPreviewAjaxAlert"></div>

                            <form method="post" enctype="multipart/form-data" class="bulk-dropzone" id="bulkPreviewForm" action="bulk_material_payment.php?manual_id=<?php echo $manualId; ?>">
                              <div class="row g-3 align-items-end">
                                <div class="col-12 col-lg-8">
                                  <label for="bulk_csv" class="form-label fw-bold">Upload CSV</label>
                                  <input type="file" class="form-control" id="bulk_csv" name="bulk_csv" accept=".csv,text/csv" required>
                                  <div class="small text-muted mt-2" id="bulkCsvHelper">Only `.csv` files are accepted.</div>
                                  <div class="small text-primary fw-semibold mt-1 d-none" id="bulkCsvFileName"></div>
                                </div>
                                <div class="col-12 col-lg-4 d-grid">
                                  <button type="submit" class="btn btn-primary fw-bold" id="bulkPreviewSubmitBtn">Proceed to Preview & Pay</button>
                                </div>
                              </div>
                            </form>

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
  <div class="modal fade" id="bulkPreviewModal" tabindex="-1" aria-labelledby="bulkPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="bulkPreviewModalLabel">Batch Preview</h5>
            <p class="text-muted mb-0" id="bulkPreviewModalSubtitle">Review your uploaded rows before payment.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger d-none" id="bulkPreviewModalError"></div>
          <div id="bulkPreviewModalWarnings" class="d-grid gap-2 mb-3"></div>
          <div id="bulkPreviewModalSummary"></div>
          <div id="bulkPreviewModalRows" class="mt-3"></div>
          <div id="bulkPreviewModalPaymentWrap" class="mt-4"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="bulkWalletPinModal" tabindex="-1" aria-labelledby="bulkWalletPinModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="bulkWalletPinModalLabel">Confirm Wallet Payment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted text-center mb-3" id="bulkWalletPinModalMessage">Enter your 4-digit Wallet PIN to authorize this bulk payment.</p>
          <div class="alert alert-danger d-none" id="bulkWalletPinError"></div>
          <div class="wallet-pin-field">
            <label for="bulkWalletPinInput" class="form-label fw-bold">Wallet PIN</label>
            <input type="password" class="form-control wallet-pin-input" id="bulkWalletPinInput" maxlength="4" inputmode="numeric" placeholder="4-DIGIT PIN">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary fw-bold" id="bulkWalletPinConfirmBtn">Confirm & Pay</button>
        </div>
      </div>
    </div>
  </div>

  <form method="post" id="bulkWalletPaymentForm" class="d-none">
    <input type="hidden" name="submit_bulk_payment" value="1">
    <input type="hidden" name="wallet_pin" id="bulkWalletPaymentHiddenPin" value="">
    <input type="hidden" name="preview_payload" id="bulkWalletPaymentPreviewPayload" value="">
  </form>

  <script src="assets/vendors/js/vendor.bundle.base.js"></script>
  <script src="assets/js/js/off-canvas.js"></script>
  <script src="assets/js/js/hoverable-collapse.js"></script>
  <script src="assets/js/js/template.js"></script>
  <script src="assets/js/js/settings.js"></script>
  <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function () {
      var previewForm = $('#bulkPreviewForm');
      var fileInput = $('#bulk_csv');
      var fileNameNode = $('#bulkCsvFileName');
      var previewSubmitBtn = $('#bulkPreviewSubmitBtn');
      var ajaxAlert = $('#bulkPreviewAjaxAlert');
      var previewModalElement = document.getElementById('bulkPreviewModal');
      var previewModal = previewModalElement && window.bootstrap ? new bootstrap.Modal(previewModalElement) : null;
      var walletPinModalElement = document.getElementById('bulkWalletPinModal');
      var walletPinModal = walletPinModalElement && window.bootstrap ? new bootstrap.Modal(walletPinModalElement) : null;
      var walletPinInput = $('#bulkWalletPinInput');
      var walletPinError = $('#bulkWalletPinError');
      var walletPinConfirmBtn = $('#bulkWalletPinConfirmBtn');
      var walletPinMessage = $('#bulkWalletPinModalMessage');
      var walletPaymentForm = $('#bulkWalletPaymentForm');
      var walletPaymentHiddenPin = $('#bulkWalletPaymentHiddenPin');
      var walletPaymentPreviewPayload = $('#bulkWalletPaymentPreviewPayload');
      var pendingPaymentTotal = 0;
      var reopenPreviewAfterPin = false;
      var walletPaymentSubmitting = false;
      var initialPreviewResponse = <?php echo json_encode($initialPreviewResponse, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

      function escapeHtml(value) {
        return String(value == null ? '' : value)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      }

      function formatNaira(value) {
        return '₦ ' + Number(value || 0).toLocaleString();
      }

      function showAjaxAlert(message, kind) {
        ajaxAlert.removeClass('d-none alert-danger alert-success alert-warning alert-info').addClass('alert-' + kind).html(message);
      }

      function renderWarnings(warnings) {
        if (!Array.isArray(warnings) || warnings.length < 1) {
          return '';
        }

        return warnings.map(function (warning) {
          return '<div class="alert alert-warning mb-0">' + escapeHtml(warning) + '</div>';
        }).join('');
      }

      function renderPreviewRows(rows) {
        if (!Array.isArray(rows) || rows.length < 1) {
          return '<div class="bulk-empty-state">No student rows were found in this CSV.</div>';
        }

        return '' +
          '<div class="table-responsive">' +
            '<table class="table table-striped align-middle">' +
              '<thead>' +
                '<tr>' +
                  '<th>Line</th>' +
                  '<th>Student</th>' +
                  '<th>Matric No.</th>' +
                  '<th>Resolution</th>' +
                  '<th>Status</th>' +
                '</tr>' +
              '</thead>' +
              '<tbody>' + rows.map(function (row) {
                var isReady = row && row.status === 'valid';
                return '' +
                  '<tr>' +
                    '<td>' + Number(row.line_number || 0) + '</td>' +
                    '<td>' + escapeHtml(((row.first_name || '') + ' ' + (row.last_name || '')).trim()) + '</td>' +
                    '<td>' + escapeHtml(row.matric_no || '') + '</td>' +
                    '<td>' + escapeHtml(row.message || '') + '</td>' +
                    '<td><span class="bulk-status-pill ' + (isReady ? 'bulk-status-pill-ready' : 'bulk-status-pill-fix') + '">' + (isReady ? 'Ready' : 'Fix row') + '</span></td>' +
                  '</tr>';
              }).join('') + '</tbody>' +
            '</table>' +
          '</div>';
      }

      function renderPaymentBlock(payload) {
        var preview = payload.preview || {};
        var wallet = payload.wallet || {};
        var totalAmount = Number(preview.breakdown && preview.breakdown.total_amount ? preview.breakdown.total_amount : 0);
        var walletPageUrl = payload.wallet_page_url || <?php echo json_encode($walletPageUrl); ?>;
        var canSubmitPayment = !!payload.can_submit_payment;
        var hasEnoughBalance = !!wallet.has_enough_balance;

        if (!canSubmitPayment) {
          return '<div class="bulk-empty-state">Fix the rows marked <strong>Fix row</strong> and complete the checklist on the page before payment will unlock.</div>';
        }

        if (!hasEnoughBalance) {
          return '' +
            '<div class="bulk-empty-state">' +
              'Your wallet balance is <strong>' + formatNaira(wallet.balance || 0) + '</strong>, which is lower than the required <strong>' + formatNaira(totalAmount) + '</strong>.' +
              '<div class="d-grid d-sm-flex gap-2 mt-3">' +
                '<a href="' + escapeHtml(walletPageUrl) + '" class="btn btn-outline-success fw-bold">Fund Wallet</a>' +
              '</div>' +
            '</div>';
        }

        return '' +
          '<div class="bulk-pay-card">' +
            '<div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start mb-3">' +
              '<div>' +
                '<div class="bulk-inline-label mb-2">Ready To Pay</div>' +
                '<h5 class="fw-bold mb-1">Pay this batch from your wallet</h5>' +
              '</div>' +
              '<div class="text-md-end">' +
                '<div class="small text-muted mb-1">Total debit</div>' +
                '<div class="fw-bold h4 mb-0">' + formatNaira(totalAmount) + '</div>' +
              '</div>' +
            '</div>' +
            '<div class="d-grid d-sm-flex gap-2 mt-4">' +
              '<button type="button" class="btn btn-success btn-lg fw-bold bulk-open-wallet-pin-modal" data-payment-total="' + totalAmount + '">Pay ' + formatNaira(totalAmount) + ' From Wallet</button>' +
            '</div>' +
            '<div class="small text-muted mt-2">Your wallet will be debited immediately after you confirm the PIN.</div>' +
          '</div>';
      }

      function openWalletPinModal(totalAmount, shouldReturnToPreview) {
        pendingPaymentTotal = Number(totalAmount || 0);
        reopenPreviewAfterPin = !!shouldReturnToPreview;
        walletPinInput.val('');
        walletPinError.addClass('d-none').text('');
        walletPinMessage.text('Enter your 4-digit Wallet PIN to authorize this bulk payment of ' + formatNaira(pendingPaymentTotal) + '.');
        if (previewModalElement && previewModalElement.classList.contains('show') && previewModal) {
          $('#bulkPreviewModal').one('hidden.bs.modal', function () {
            if (walletPinModal) {
              walletPinModal.show();
            }
          });
          previewModal.hide();
          return;
        }
        if (walletPinModal) {
          walletPinModal.show();
        }
      }

      function renderPreviewModal(response) {
        var payload = response && response.data ? response.data : {};
        var preview = payload.preview || {};
        var manual = payload.manual || {};
        walletPaymentPreviewPayload.val(JSON.stringify(Array.isArray(payload.preview_rows) ? payload.preview_rows : []));
        var summaryHtml = '' +
          '<div class="row g-3">' +
            '<div class="col-6 col-md-3"><div class="bulk-kpi-card"><p class="text-muted mb-1">Rows uploaded</p><h4 class="fw-bold mb-0">' + Number((preview.rows || []).length) + '</h4></div></div>' +
            '<div class="col-6 col-md-3"><div class="bulk-kpi-card"><p class="text-muted mb-1">Ready rows</p><h4 class="fw-bold mb-0">' + Number(preview.valid_count || 0) + '</h4></div></div>' +
            '<div class="col-6 col-md-3"><div class="bulk-kpi-card"><p class="text-muted mb-1">Need fixes</p><h4 class="fw-bold mb-0">' + Number(preview.invalid_count || 0) + '</h4></div></div>' +
            '<div class="col-6 col-md-3"><div class="bulk-kpi-card"><p class="text-muted mb-1">Total to debit</p><h4 class="fw-bold mb-0">' + formatNaira(preview.breakdown && preview.breakdown.total_amount ? preview.breakdown.total_amount : 0) + '</h4></div></div>' +
          '</div>';

        $('#bulkPreviewModalLabel').text('Batch Preview');
        $('#bulkPreviewModalSubtitle').text((manual.title || 'Selected material') + ((manual.course_code || '') ? ' - ' + manual.course_code : ''));
        $('#bulkPreviewModalError').addClass('d-none').text('');
        $('#bulkPreviewModalWarnings').html(renderWarnings(payload.page_warnings || []));
        $('#bulkPreviewModalSummary').html(summaryHtml);

        var rowsHtml = '';
        if (Array.isArray(preview.errors) && preview.errors.length > 0) {
          rowsHtml += '<div class="alert alert-warning"><div class="fw-bold mb-2">Rows that need attention</div><ul class="mb-0 ps-3">' + preview.errors.map(function (error) {
            return '<li>' + escapeHtml(error) + '</li>';
          }).join('') + '</ul></div>';
        }
        rowsHtml += renderPreviewRows(preview.rows || []);
        $('#bulkPreviewModalRows').html(rowsHtml);
        $('#bulkPreviewModalPaymentWrap').html(renderPaymentBlock(payload));

        if (previewModal) {
          previewModal.show();
        }
      }

      fileInput.on('change', function () {
        var selectedFile = this.files && this.files.length > 0 ? this.files[0].name : '';
        if (selectedFile) {
          fileNameNode.text('Selected file: ' + selectedFile).removeClass('d-none');
        } else {
          fileNameNode.text('').addClass('d-none');
        }
      });

      previewForm.on('submit', function (event) {
        event.preventDefault();

        var formData = new FormData(this);
        formData.append('ajax_preview_bulk_payment', '1');
        var originalText = previewSubmitBtn.html();
        previewSubmitBtn.prop('disabled', true).html('Previewing...');
        ajaxAlert.addClass('d-none').removeClass('alert-danger alert-success alert-warning alert-info').html('');

        $.ajax({
          type: 'POST',
          url: previewForm.attr('action'),
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json'
        }).done(function (response) {
          if (response && response.status === 'success') {
            renderPreviewModal(response);
            return;
          }

          showAjaxAlert(response && response.message ? response.message : 'Unable to preview this CSV right now.', 'danger');
        }).fail(function (xhr) {
          var response = xhr.responseJSON || {};
          showAjaxAlert(response.message || 'Unable to preview this CSV right now.', 'danger');
        }).always(function () {
          previewSubmitBtn.prop('disabled', false).html(originalText);
        });
      });

      $(document).on('click', '.bulk-open-wallet-pin-modal', function () {
        openWalletPinModal($(this).data('paymentTotal'), $(this).closest('#bulkPreviewModal').length > 0);
      });

      walletPinConfirmBtn.on('click', function () {
        var pin = String(walletPinInput.val() || '').trim();
        if (!/^\d{4}$/.test(pin)) {
          walletPinError.removeClass('d-none').text('Enter a valid 4-digit Wallet PIN.');
          return;
        }
        if (!walletPaymentPreviewPayload.val()) {
          walletPinError.removeClass('d-none').text('Preview the CSV again before paying from your wallet.');
          return;
        }

        walletPinError.addClass('d-none').text('');
        walletPaymentHiddenPin.val(pin);
        walletPaymentSubmitting = true;
        walletPinConfirmBtn.prop('disabled', true).text('Confirming...');
        walletPaymentForm.get(0).submit();
      });

      if (initialPreviewResponse) {
        renderPreviewModal(initialPreviewResponse);
      }

      $('#bulkWalletPinModal').on('hidden.bs.modal', function () {
        walletPinInput.val('');
        walletPinError.addClass('d-none').text('');
        walletPinConfirmBtn.prop('disabled', false).text('Confirm & Pay');
        if (!walletPaymentSubmitting && reopenPreviewAfterPin && previewModal) {
          previewModal.show();
        }
        reopenPreviewAfterPin = false;
        walletPaymentSubmitting = false;
      });
    });
  </script>
</body>

</html>