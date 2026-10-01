<?php
// Bulk material payment preview: parse CSV/text rows and validate them against the payer's
// department before paying. Shared by bulk_material_payment.php and API/materials/bulk/*.
require_once __DIR__ . '/bulk_material_payment_service.php';
require_once __DIR__ . '/material_copy_status.php';

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

if (!function_exists('bulk_material_payment_load_payable_manual')) {
  // A material the payer can bulk-pay for right now: same school, open, not overdue, current semester.
  function bulk_material_payment_load_payable_manual(mysqli $conn, int $manualId, int $schoolId): array
  {
    require_once __DIR__ . '/material_semester.php';

    if ($manualId <= 0) {
      throw new Exception('Select a valid material before starting a bulk payment.');
    }

    $manualQuery = mysqli_query($conn, "SELECT * FROM manuals WHERE id = {$manualId} AND school_id = {$schoolId} LIMIT 1");
    $manual = $manualQuery ? mysqli_fetch_assoc($manualQuery) : null;
    if (!$manual) {
      throw new Exception('The selected material could not be found for your school.');
    }

    $dueDate = strtotime((string) ($manual['due_date'] ?? ''));
    if ((string) ($manual['status'] ?? '') !== 'open' || ($dueDate !== false && time() > $dueDate)) {
      throw new Exception('This material is closed and cannot be used for bulk payment.');
    }
    if (!material_semester_is_visible($conn, $manual, $schoolId)) {
      throw new Exception('This material is not on sale this semester and cannot be used for bulk payment.');
    }

    return $manual;
  }
}

if (!function_exists('bulk_material_payment_list_payable_manuals')) {
  // Materials open to the payer's department (including ones they already own), current semester only.
  function bulk_material_payment_list_payable_manuals(mysqli $conn, int $schoolId, int $deptId): array
  {
    require_once __DIR__ . '/material_semester.php';
    require_once __DIR__ . '/material_request_service.php';

    if ($schoolId <= 0 || $deptId <= 0) {
      return [];
    }

    $visibilityWhere = nivasityMaterialRequestBuildManualVisibilityWhere($conn, $deptId, $schoolId, 'm');
    $semesterWhere = material_semester_where_sql($conn, $schoolId, 'm');
    $sql = "SELECT m.id, m.title, m.course_code, m.price, m.level, m.semester, m.due_date
            FROM manuals AS m
            WHERE ($visibilityWhere)
              AND m.status = 'open'
              AND m.school_id = $schoolId
              AND $semesterWhere
              AND m.due_date >= NOW()
            ORDER BY m.id DESC";
    $rs = mysqli_query($conn, $sql);
    if (!$rs) {
      return [];
    }

    $manuals = [];
    while ($row = mysqli_fetch_assoc($rs)) {
      $manuals[] = [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'course_code' => (string) $row['course_code'],
        'price' => (int) round((float) $row['price']),
        'level' => $row['level'] !== null ? (string) $row['level'] : null,
        'semester' => material_semester_normalize($row['semester'] ?? null),
      ];
    }

    return $manuals;
  }
}

if (!function_exists('bulk_material_payment_wallet_readiness')) {
  // Whether the payer can pay a bulk batch from their wallet, with the reasons when not.
  function bulk_material_payment_wallet_readiness(mysqli $conn, array $user): array
  {
    $userId = (int) ($user['id'] ?? 0);
    $wallet = nivasityGetUserWallet($conn, $userId);
    $hasPin = nivasityUserHasWalletPin($conn, $userId);
    $warnings = [];

    if ((int) ($user['dept'] ?? 0) <= 0) {
      $warnings[] = 'Set your department on your profile before paying for students in bulk.';
    }
    if ($wallet === null) {
      $warnings[] = 'Request your wallet before moving to wallet payment.';
    }
    if (!$hasPin) {
      $warnings[] = 'Create your Wallet PIN before wallet payment is enabled.';
    }

    return [
      'ready' => $wallet !== null && $hasPin && (int) ($user['dept'] ?? 0) > 0 && (string) ($user['status'] ?? '') === 'verified',
      'balance' => (int) ($wallet['balance'] ?? 0),
      'warnings' => $warnings,
    ];
  }
}
