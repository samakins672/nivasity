<?php
session_start();
include('model/config.php');
include('model/page_config.php');
require_once 'model/bulk_material_payment_service.php';
require_once 'model/material_copy_status.php';

if (!function_exists('bulk_material_payment_preview_normalize_header_row')) {
  function bulk_material_payment_preview_normalize_header_row(array $row): array
  {
    $normalizedHeaders = [];
    foreach ($row as $index => $header) {
      $header = (string) $header;
      if ($index === 0) {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
      }
      $normalizedHeaders[] = bulk_material_payment_normalize_text($header);
    }

    return $normalizedHeaders;
  }
}

if (!function_exists('bulk_material_payment_preview_build_rows_from_records')) {
  function bulk_material_payment_preview_build_rows_from_records(array $records): array
  {
    $rows = [];

    foreach ($records as $record) {
      if (!is_array($record)) {
        continue;
      }

      $data = is_array($record['data'] ?? null) ? $record['data'] : [];
      $lineNumber = (int) ($record['line_number'] ?? 0);
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

    return $rows;
  }
}

if (!function_exists('bulk_material_payment_preview_parse_upload')) {
  function bulk_material_payment_preview_parse_upload(array $file): array
  {
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
      $message = 'Upload a CSV file before previewing your batch.';

      if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
        $message = 'The uploaded CSV file is too large. Try a smaller CSV file.';
      } elseif ($uploadError === UPLOAD_ERR_PARTIAL) {
        $message = 'The CSV upload did not finish. Please try again.';
      } elseif ($uploadError !== UPLOAD_ERR_NO_FILE) {
        $message = 'We could not receive the uploaded CSV file. Please try again.';
      }

      return [
        'ok' => false,
        'message' => $message,
      ];
    }

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

    $normalizedHeaders = bulk_material_payment_preview_normalize_header_row($headers);
    $expectedHeaders = bulk_material_payment_format_csv_header();
    if ($normalizedHeaders !== $expectedHeaders) {
      fclose($handle);
      return [
        'ok' => false,
        'message' => 'Use the official CSV template with headers: ' . implode(', ', $expectedHeaders) . '.',
      ];
    }

    $records = [];
    $lineNumber = 1;
    while (($data = fgetcsv($handle)) !== false) {
      $lineNumber++;
      if (!is_array($data)) {
        continue;
      }

      $records[] = [
        'line_number' => $lineNumber,
        'data' => $data,
      ];
    }

    fclose($handle);

    return [
      'ok' => true,
      'rows' => bulk_material_payment_preview_build_rows_from_records($records),
    ];
  }
}

if (!function_exists('bulk_material_payment_preview_parse_text')) {
  function bulk_material_payment_preview_parse_text(string $rawText): array
  {
    if (trim($rawText) === '') {
      return [
        'ok' => false,
        'message' => 'Paste at least one student row before previewing your batch.',
      ];
    }

    $lines = preg_split('/\r\n|\r|\n/', $rawText);
    if (!is_array($lines)) {
      return [
        'ok' => false,
        'message' => 'We could not read the pasted student records.',
      ];
    }

    $records = [];
    foreach ($lines as $index => $line) {
      if (trim((string) $line) === '') {
        continue;
      }

      $data = str_getcsv((string) $line);
      if (!is_array($data)) {
        continue;
      }

      $records[] = [
        'line_number' => $index + 1,
        'data' => $data,
      ];
    }

    if (count($records) < 1) {
      return [
        'ok' => false,
        'message' => 'Paste at least one student row before previewing your batch.',
      ];
    }

    $expectedHeaders = bulk_material_payment_format_csv_header();
    $firstRecordHeaders = bulk_material_payment_preview_normalize_header_row((array) ($records[0]['data'] ?? []));
    if ($firstRecordHeaders === $expectedHeaders) {
      array_shift($records);
    }

    $rows = bulk_material_payment_preview_build_rows_from_records($records);
    if (count($rows) < 1) {
      return [
        'ok' => false,
        'message' => 'Paste at least one student row before previewing your batch.',
      ];
    }

    return [
      'ok' => true,
      'rows' => $rows,
    ];
  }
}

if (!function_exists('bulk_material_payment_preview_parse_request')) {
  function bulk_material_payment_preview_parse_request(array $file, string $rawText): array
  {
    if (trim($rawText) !== '') {
      return bulk_material_payment_preview_parse_text($rawText);
    }

    return bulk_material_payment_preview_parse_upload($file);
  }
}

if (!function_exists('bulk_material_payment_preview_validation_unavailable_message')) {
  function bulk_material_payment_preview_validation_unavailable_message(): string
  {
    return 'Validation unavailable.';
  }
}

if (!function_exists('bulk_material_payment_preview_validation_warning')) {
  function bulk_material_payment_preview_validation_warning(): string
  {
    return 'Validation unavailable. Payment locked.';
  }
}

if (!function_exists('bulk_material_payment_preview_analyze_rows')) {
  function bulk_material_payment_preview_analyze_rows(mysqli $conn, array $rows, array $manual, array $payer): array
  {
    $manualId = (int) ($manual['id'] ?? 0);
    $schoolId = (int) ($payer['school'] ?? 0);
    $payerDeptId = (int) ($payer['dept'] ?? 0);
    $manualPrice = (int) round((float) ($manual['price'] ?? 0));
    $nonLostCondition = '1 = 1';
    $validationWarning = '';

    try {
      $nonLostCondition = material_copy_non_lost_condition($conn, 'mb');
    } catch (Throwable $e) {
      $validationWarning = bulk_material_payment_preview_validation_warning();
    }

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
      $message = 'Pending placeholder.';
      $matchedUserId = 0;

      if ($normalizedFirstName === '' || $normalizedLastName === '' || $normalizedMatricNo === '') {
        $status = 'error';
        $message = 'Missing name or matric no.';
      }

      $namePairSignature = bulk_material_payment_name_pair_signature($normalizedFirstName, $normalizedLastName);
      $identityKey = $normalizedMatricNo . '|' . $namePairSignature;
      if ($status === 'valid' && isset($seenIdentities[$identityKey])) {
        $status = 'error';
        $message = 'Duplicate row.';
      }

      if ($status === 'valid') {
        if (isset($seenMatricNames[$normalizedMatricNo]) && $seenMatricNames[$normalizedMatricNo] !== $namePairSignature) {
          $status = 'error';
          $message = 'Same matric, different names.';
        } else {
          $seenIdentities[$identityKey] = true;
          $seenMatricNames[$normalizedMatricNo] = $namePairSignature;
        }
      }

      if ($status === 'valid' && $validationWarning === '') {
        try {
          if (bulk_material_payment_has_table($conn, 'manual_bulk_payment_students')) {
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
              $message = 'Pending claim exists.';
            }
          }

          if ($status === 'valid') {
            $matricSafe = mysqli_real_escape_string($conn, $normalizedMatricNo);
            $activeCopyQuery = mysqli_query(
              $conn,
              "SELECT mb.id
               FROM manuals_bought AS mb
               INNER JOIN users AS u ON u.id = mb.buyer
               WHERE mb.manual_id = {$manualId}
                 AND mb.school_id = {$schoolId}
                 AND {$nonLostCondition}
                 AND u.school = {$schoolId}
                 AND u.dept = {$payerDeptId}
                 AND LOWER(TRIM(u.matric_no)) = '{$matricSafe}'
               LIMIT 1"
            );
            if ($activeCopyQuery && mysqli_num_rows($activeCopyQuery) > 0) {
              $status = 'error';
              $message = 'Active copy exists.';
            }
          }

          if ($status === 'valid') {
            $matricSafe = mysqli_real_escape_string($conn, $normalizedMatricNo);
            $matchedUser = bulk_material_payment_find_matching_user($conn, $schoolId, $payerDeptId, $normalizedMatricNo, $normalizedFirstName, $normalizedLastName);
            $matchStatus = (string) ($matchedUser['match_status'] ?? 'not_found');

            if ($matchStatus === 'name_mismatch') {
              $status = 'error';
              $message = bulk_material_payment_name_mismatch_message($matchedUser);
            } elseif ($matchStatus === 'department_mismatch') {
              $resolution = 'department_mismatch_note';
              $message = bulk_material_payment_department_mismatch_message($matchedUser);
            } elseif ($matchStatus === 'matched') {
              $matchedUserId = (int) ($matchedUser['id'] ?? 0);
              $resolution = 'existing_student';
              $message = 'Matched in your dept.';
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
              $message = 'Already owns this material.';
            }
          }
        } catch (Throwable $e) {
          $validationWarning = bulk_material_payment_preview_validation_warning();
          $status = 'error';
          $resolution = 'validation_unavailable';
          $message = bulk_material_payment_preview_validation_unavailable_message();
        }
      } elseif ($status === 'valid') {
        $status = 'error';
        $resolution = 'validation_unavailable';
        $message = bulk_material_payment_preview_validation_unavailable_message();
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
      'validation_warning' => $validationWarning,
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
    $previewWarnings = array_values($pageWarnings);
    $validationWarning = trim((string) ($previewResult['validation_warning'] ?? ''));
    if ($validationWarning !== '') {
      $previewWarnings[] = $validationWarning;
    }
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
      'page_warnings' => $previewWarnings,
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

  $parsedUpload = bulk_material_payment_preview_parse_request(
    $_FILES['bulk_csv'] ?? [],
    (string) ($_POST['bulk_text_records'] ?? '')
  );
  if (!$parsedUpload['ok']) {
    bulk_material_payment_json_response('error', (string) ($parsedUpload['message'] ?? 'Unable to preview the uploaded CSV right now.'), [], 422);
  }

  $previewRows = $parsedUpload['rows'] ?? [];
  $previewResult = bulk_material_payment_preview_analyze_rows($conn, $previewRows, $manual ?: [], [
    'school' => $school_id,
    'dept' => $user_dept,
  ]);
  $previewResult['source_rows'] = $previewRows;
  $validationWarning = trim((string) ($previewResult['validation_warning'] ?? ''));
  $previewMessage = $validationWarning !== ''
    ? 'Preview loaded, but student-record validation is temporarily unavailable.'
    : (((int) ($previewResult['invalid_count'] ?? 0)) > 0
      ? 'Preview loaded. Fix the highlighted rows before payment.'
      : 'Preview loaded successfully.');

  bulk_material_payment_json_response(
    'success',
    $previewMessage,
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

    $validationWarning = trim((string) ($previewResult['validation_warning'] ?? ''));
    if ($validationWarning !== '') {
      $pageError = $validationWarning;
    } elseif (((int) ($previewResult['invalid_count'] ?? 0)) > 0 || ((int) ($previewResult['valid_count'] ?? 0)) < 1) {
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
      background: linear-gradient(135deg, var(--bulk-accent) 60%, #ff9100 100%);
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

    .bulk-alt-entry-card {
      display: grid;
      gap: 0.75rem;
      align-items: start;
    }

    .bulk-alt-entry-card .btn {
      justify-self: start;
    }

    .bulk-option-divider {
      display: flex;
      align-items: center;
      gap: 0.85rem;
      margin: 0.95rem 0;
      color: rgba(15, 23, 42, 0.62);
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .bulk-option-divider::before,
    .bulk-option-divider::after {
      content: '';
      flex: 1;
      border-top: 1px dashed rgba(var(--bulk-accent-rgb), 0.3);
    }

    .bulk-text-records-input {
      display: block;
      width: 100% !important;
      min-height: 20rem !important;
      height: 20rem !important;
      line-height: 1.5;
      resize: vertical;
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

    .wallet-pin-input {
      height: 4.5rem;
      border-radius: 0.85rem;
      font-size: 1.55rem;
      font-weight: 700;
      letter-spacing: 0.24em;
      text-align: center;
      padding: 0.75rem 1rem;
    }

    .wallet-pin-input::placeholder {
      letter-spacing: 0.08em;
      font-size: 0.9rem;
      font-weight: 600;
    }

    .wallet-pin-field {
      max-width: 21rem;
      margin: 0 auto;
      text-align: center;
    }

    .wallet-pin-field .form-label {
      display: block;
      text-align: center;
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
                                  <p class="text-muted mb-0">Each row must contain <strong>first name</strong>, <strong>last name</strong>, and <strong>matric number</strong>. You can upload the rows as CSV or paste them directly. Students are matched only inside your department.</p>
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
                                <div class="col-12">
                                  <label for="bulk_csv" class="form-label fw-bold">Upload CSV</label>
                                  <input type="file" class="form-control" id="bulk_csv" name="bulk_csv" accept=".csv,text/csv" required>
                                  <div class="small text-muted mt-2" id="bulkCsvHelper"></div>
                                  <div class="small text-primary fw-semibold mt-1 d-none" id="bulkCsvFileName"></div>
                                </div>
                                <div class="col-12 d-grid">
                                  <button type="submit" class="btn btn-primary fw-bold" id="bulkPreviewSubmitBtn">Proceed to Preview & Pay</button>
                                </div>
                              </div>
                            </form>

                            <div class="bulk-option-divider" aria-hidden="true">OR</div>

                            <div class="bulk-dropzone bulk-alt-entry-card mt-3">
                              <div>
                                <label class="form-label fw-bold mb-1">Paste Records</label>
                                <div class="small text-muted">Open a modal and paste one comma-separated row per student.</div>
                              </div>
                              <button type="button" class="btn btn-secondary fw-bold" id="bulkOpenTextEntryModalBtn">Paste Records</button>
                              <div class="small text-muted" id="bulkTextDraftStatus">Paste rows in a modal if you do not want to upload a CSV file.</div>
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
        <?php include('partials/_footer.php') ?>
        <?php include('partials/_bulk_payment_modal.php') ?>
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

  <div class="modal fade" id="bulkTextEntryModal" tabindex="-1" aria-labelledby="bulkTextEntryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title fw-bold" id="bulkTextEntryModalLabel">Paste Student Records</h5>
            <p class="text-muted mb-0">Use one comma-separated row per student in the format <strong>first_name, last_name, matric_no</strong>.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger d-none" id="bulkTextEntryAlert"></div>
          <label for="bulkTextRecordsInput" class="form-label fw-bold">Student rows</label>
          <textarea class="form-control bulk-text-records-input" id="bulkTextRecordsInput" rows="14" placeholder="first_name,last_name,matric_no&#10;Ada,Okafor,CSC/2022/001&#10;John,Balogun,CSC/2022/002"></textarea>
          <div class="small text-muted mt-2">The first row can be the column header or the first student record. Drafts stay on this device until payment succeeds.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary fw-bold" id="bulkTextPreviewSubmitBtn">Proceed to Preview & Pay</button>
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
    <input type="hidden" name="preview_source" id="bulkWalletPaymentPreviewSource" value="">
  </form>

  <script src="assets/vendors/js/vendor.bundle.base.js"></script>
  <script src="assets/js/js/off-canvas.js"></script>
  <script src="assets/js/js/hoverable-collapse.js"></script>
  <script src="assets/js/js/template.js"></script>
  <script src="assets/js/js/settings.js"></script>
  <script src="assets/js/script.js"></script>
  <script>
    $(document).ready(function () {
      function createModalInstance(element) {
        if (!element || !window.bootstrap || !window.bootstrap.Modal) {
          return null;
        }

        return typeof window.bootstrap.Modal.getOrCreateInstance === 'function'
          ? window.bootstrap.Modal.getOrCreateInstance(element)
          : new window.bootstrap.Modal(element);
      }

      var previewForm = $('#bulkPreviewForm');
      var fileInput = $('#bulk_csv');
      var fileNameNode = $('#bulkCsvFileName');
      var previewSubmitBtn = $('#bulkPreviewSubmitBtn');
      var openTextEntryModalBtn = $('#bulkOpenTextEntryModalBtn');
      var textEntryModalElement = document.getElementById('bulkTextEntryModal');
      var textEntryModal = createModalInstance(textEntryModalElement);
      var textEntryAlert = $('#bulkTextEntryAlert');
      var textRecordsInput = $('#bulkTextRecordsInput');
      var textPreviewSubmitBtn = $('#bulkTextPreviewSubmitBtn');
      var textDraftStatus = $('#bulkTextDraftStatus');
      var ajaxAlert = $('#bulkPreviewAjaxAlert');
      var previewModalElement = document.getElementById('bulkPreviewModal');
      var previewModal = createModalInstance(previewModalElement);
      var walletPinModalElement = document.getElementById('bulkWalletPinModal');
      var walletPinModal = createModalInstance(walletPinModalElement);
      var walletPinInput = $('#bulkWalletPinInput');
      var walletPinError = $('#bulkWalletPinError');
      var walletPinConfirmBtn = $('#bulkWalletPinConfirmBtn');
      var walletPinMessage = $('#bulkWalletPinModalMessage');
      var walletPaymentForm = $('#bulkWalletPaymentForm');
      var walletPaymentHiddenPin = $('#bulkWalletPaymentHiddenPin');
      var walletPaymentPreviewPayload = $('#bulkWalletPaymentPreviewPayload');
      var walletPaymentPreviewSource = $('#bulkWalletPaymentPreviewSource');
      var pendingPaymentTotal = 0;
      var reopenPreviewAfterPin = false;
      var walletPaymentSubmitting = false;
      var lastPreviewSource = '';
      var returnTextEntryAfterPreview = false;
      var previewHideReason = '';
      var textDraftStorageKey = 'nivasity.bulk_payment_text_records.' + <?php echo json_encode((string) $user_id); ?> + '.' + <?php echo json_encode((string) $manualId); ?>;
      var paymentCompleted = <?php echo is_array($paymentSuccess) ? 'true' : 'false'; ?>;
      var initialPreviewSource = <?php echo json_encode((string) ($_POST['preview_source'] ?? '')); ?>;
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

      function showTextEntryAlert(message, kind) {
        if (!textEntryAlert.length) {
          return;
        }

        if (!message) {
          textEntryAlert.addClass('d-none').removeClass('alert-danger alert-success alert-warning alert-info').html('');
          return;
        }

        textEntryAlert.removeClass('d-none alert-danger alert-success alert-warning alert-info').addClass('alert-' + kind).html(message);
      }

      function canUseLocalStorage() {
        try {
          return typeof window.localStorage !== 'undefined';
        } catch (error) {
          return false;
        }
      }

      function updateTextDraftStatus() {
        if (!textDraftStatus.length) {
          return;
        }

        if (paymentCompleted) {
          textDraftStatus.text('Saved pasted records were cleared after payment completion.');
          return;
        }

        var hasDraft = String(textRecordsInput.val() || '').trim() !== '';
        textDraftStatus.text(
          hasDraft
            ? ''
            : 'Paste rows in a modal if you do not want to upload a CSV file.'
        );
      }

      function persistTextDraft() {
        if (paymentCompleted) {
          updateTextDraftStatus();
          return;
        }

        if (canUseLocalStorage()) {
          var value = String(textRecordsInput.val() || '');
          if (value.trim() === '') {
            window.localStorage.removeItem(textDraftStorageKey);
          } else {
            window.localStorage.setItem(textDraftStorageKey, value);
          }
        }

        updateTextDraftStatus();
      }

      function clearTextDraft() {
        textRecordsInput.val('');
        if (canUseLocalStorage()) {
          window.localStorage.removeItem(textDraftStorageKey);
        }
        updateTextDraftStatus();
      }

      function restoreTextDraft() {
        if (paymentCompleted) {
          clearTextDraft();
          return;
        }

        if (canUseLocalStorage()) {
          var savedDraft = window.localStorage.getItem(textDraftStorageKey);
          if (savedDraft) {
            textRecordsInput.val(savedDraft);
          }
        }

        updateTextDraftStatus();
      }

      function resolvePreviewRequestError(xhr) {
        var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;

        if (!response && xhr && xhr.responseText) {
          try {
            response = JSON.parse(xhr.responseText);
          } catch (error) {
            var responseText = String(xhr.responseText || '');
            var jsonStart = responseText.indexOf('{');
            if (jsonStart !== -1) {
              try {
                response = JSON.parse(responseText.slice(jsonStart));
              } catch (nestedError) {
                response = null;
              }
            } else {
              response = null;
            }
          }
        }

        if (response && response.message) {
          return response.message;
        }

        var responseText = xhr && xhr.responseText ? String(xhr.responseText) : '';
        if (/mysqli_sql_exception|MySQL server has gone away|No connection could be made because the target machine actively refused it/i.test(responseText)) {
          return 'Student-record validation is temporarily unavailable. The CSV could not be verified against the database right now.';
        }

        var responseUrl = xhr && xhr.responseURL ? String(xhr.responseURL) : '';
        if (responseUrl.indexOf('signin.html') !== -1) {
          return 'Your session expired. Sign in again and retry the CSV preview.';
        }

        if (xhr && xhr.status === 413) {
          return 'The uploaded CSV file is too large. Try a smaller CSV file.';
        }

        if (xhr && xhr.status === 422) {
          return 'Check the uploaded CSV file and try again.';
        }

        return 'Unable to preview this CSV right now.';
      }

      function handlePreviewError(message, target) {
        if (target === 'text') {
          showTextEntryAlert(message, 'danger');
          return;
        }

        showAjaxAlert(message, 'danger');
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
          previewHideReason = 'wallet';
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

      function renderPreviewModal(response, source) {
        if (source) {
          lastPreviewSource = source;
          returnTextEntryAfterPreview = source === 'text';
        }

        var payload = response && response.data ? response.data : {};
        var preview = payload.preview || {};
        var manual = payload.manual || {};
        walletPaymentPreviewPayload.val(JSON.stringify(Array.isArray(payload.preview_rows) ? payload.preview_rows : []));
        walletPaymentPreviewSource.val(lastPreviewSource || 'file');
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

        if (lastPreviewSource === 'text' && textEntryModalElement && textEntryModalElement.classList.contains('show') && textEntryModal) {
          $('#bulkTextEntryModal').one('hidden.bs.modal', function () {
            if (previewModal) {
              previewModal.show();
            }
          });
          textEntryModal.hide();
          return;
        }

        if (previewModal) {
          previewModal.show();
        }
      }

      function runPreviewRequest(formData, options) {
        options = options || {};

        var triggerButton = options.triggerButton || previewSubmitBtn;
        var previewSource = options.previewSource || 'file';
        var errorTarget = options.errorTarget || 'page';
        var originalText = triggerButton.html();

        if (errorTarget === 'text') {
          showTextEntryAlert('', 'danger');
        }
        ajaxAlert.addClass('d-none').removeClass('alert-danger alert-success alert-warning alert-info').html('');

        triggerButton.prop('disabled', true).html('Previewing...');

        $.ajax({
          type: 'POST',
          url: previewForm.attr('action'),
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json'
        }).done(function (response) {
          if (response && response.status === 'success') {
            renderPreviewModal(response, previewSource);
            return;
          }

          handlePreviewError(response && response.message ? response.message : 'Unable to preview this CSV right now.', errorTarget);
        }).fail(function (xhr) {
          handlePreviewError(resolvePreviewRequestError(xhr), errorTarget);
        }).always(function () {
          triggerButton.prop('disabled', false).html(originalText);
        });
      }

      fileInput.on('change', function () {
        var selectedFile = this.files && this.files.length > 0 ? this.files[0].name : '';
        if (selectedFile) {
          fileNameNode.text('Selected file: ' + selectedFile).removeClass('d-none');
        } else {
          fileNameNode.text('').addClass('d-none');
        }
      });

      textRecordsInput.on('input', function () {
        persistTextDraft();
      });

      openTextEntryModalBtn.on('click', function () {
        showTextEntryAlert('', 'danger');
        if (textEntryModal) {
          textEntryModal.show();
        }
      });

      previewForm.on('submit', function (event) {
        event.preventDefault();

        var formData = new FormData(this);
        formData.append('ajax_preview_bulk_payment', '1');
        runPreviewRequest(formData, {
          triggerButton: previewSubmitBtn,
          previewSource: 'file',
          errorTarget: 'page'
        });
      });

      textPreviewSubmitBtn.on('click', function () {
        var rawText = String(textRecordsInput.val() || '');
        if (rawText.trim() === '') {
          showTextEntryAlert('Paste at least one student row before previewing your batch.', 'danger');
          return;
        }

        persistTextDraft();

        var formData = new FormData();
        formData.append('ajax_preview_bulk_payment', '1');
        formData.append('bulk_text_records', rawText);
        runPreviewRequest(formData, {
          triggerButton: textPreviewSubmitBtn,
          previewSource: 'text',
          errorTarget: 'text'
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
        walletPaymentPreviewSource.val(lastPreviewSource || 'file');
        walletPaymentSubmitting = true;
        walletPinConfirmBtn.prop('disabled', true).text('Confirming...');
        walletPaymentForm.get(0).submit();
      });

      $('#bulkPreviewModal').on('hidden.bs.modal', function () {
        if (previewHideReason === 'wallet') {
          previewHideReason = '';
          return;
        }

        if (!walletPaymentSubmitting && returnTextEntryAfterPreview && lastPreviewSource === 'text' && textEntryModal) {
          textEntryModal.show();
        }
      });

      restoreTextDraft();

      if (initialPreviewResponse) {
        renderPreviewModal(initialPreviewResponse, initialPreviewSource || 'file');
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