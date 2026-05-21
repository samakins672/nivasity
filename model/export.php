<?php
session_start();
include('config.php');
include('functions.php');
require_once __DIR__ . '/material_copy_status.php';
require_once __DIR__ . '/export_pdf.php';
$statusRes = $schools = 'failed';

/**
 * Generate a short, human-friendly verification code for manual exports.
 * Ensures (with high probability) uniqueness within manual_export_audits.
 */
function generateManualExportCode(mysqli $conn, $length = 10) {
  $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
  $maxIndex = strlen($alphabet) - 1;
  $attempts = 0;
  do {
    $code = '';
    for ($i = 0; $i < $length; $i++) {
      $code .= $alphabet[random_int(0, $maxIndex)];
    }
    $safeCode = mysqli_real_escape_string($conn, $code);
    $exists = mysqli_query($conn, "SELECT 1 FROM manual_export_audits WHERE code = '$safeCode' LIMIT 1");
    $attempts++;
  } while ($exists && mysqli_num_rows($exists) > 0 && $attempts < 10);

  return $code;
}

function exportRequestId() {
  try {
    return strtoupper(bin2hex(random_bytes(6)));
  } catch (Throwable $e) {
    return strtoupper(uniqid('EXP', false));
  }
}

function exportLog($requestId, $message, array $context = []) {
  $prefix = '[export][' . $requestId . '] ';
  if (!empty($context)) {
    $json = json_encode($context, JSON_UNESCAPED_SLASHES);
    if ($json !== false) {
      error_log($prefix . $message . ' | ' . $json);
      return;
    }
  }
  error_log($prefix . $message);
}

function exportSqlSnippet($sql, $maxLen = 300) {
  $oneLine = preg_replace('/\s+/', ' ', trim((string)$sql));
  if (strlen($oneLine) > $maxLen) {
    return substr($oneLine, 0, $maxLen) . '...';
  }
  return $oneLine;
}

function exportJsonResponse(array $payload, $statusCode = 200) {
  if (!headers_sent()) {
    http_response_code((int)$statusCode);
    header('Content-Type: application/json');
  }
  echo json_encode($payload);
}

function exportPdfResponse(string $payload, string $filename, $statusCode = 200) {
  if (!headers_sent()) {
    http_response_code((int)$statusCode);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($payload));
  }
  echo $payload;
}

function exportRunQuery(mysqli $conn, $sql, $requestId, $step, array $context = []) {
  $res = mysqli_query($conn, $sql);
  if ($res === false) {
    $sqlError = mysqli_error($conn);
    exportLog($requestId, 'DB query failed', array_merge($context, [
      'step' => $step,
      'sql_error' => $sqlError,
      'sql' => exportSqlSnippet($sql),
    ]));
    throw new RuntimeException($step . ' failed: ' . $sqlError);
  }
  return $res;
}

function exportResolveAuditStatusColumn(mysqli $conn, $requestId) {
  static $resolved = null;
  if ($resolved !== null) {
    return $resolved;
  }

  $hasGrantStatus = mysqli_query($conn, "SHOW COLUMNS FROM manual_export_audits LIKE 'grant_status'");
  if ($hasGrantStatus && mysqli_num_rows($hasGrantStatus) > 0) {
    $resolved = 'grant_status';
    return $resolved;
  }

  $hasStatus = mysqli_query($conn, "SHOW COLUMNS FROM manual_export_audits LIKE 'status'");
  if ($hasStatus && mysqli_num_rows($hasStatus) > 0) {
    $resolved = 'status';
    return $resolved;
  }

  exportLog($requestId, 'Audit status column not found; export will continue without granted lookups');
  $resolved = '';
  return $resolved;
}

function exportAuditHasColumn(mysqli $conn, $columnName) {
  static $columnCache = [];
  if (isset($columnCache[$columnName])) {
    return $columnCache[$columnName];
  }

  $safeColumn = mysqli_real_escape_string($conn, $columnName);
  $res = mysqli_query($conn, "SHOW COLUMNS FROM manual_export_audits LIKE '$safeColumn'");
  $hasColumn = ($res && mysqli_num_rows($res) > 0);
  $columnCache[$columnName] = $hasColumn;
  return $hasColumn;
}

function exportManualsBoughtHasColumn(mysqli $conn, $columnName) {
  static $columnCache = [];
  if (isset($columnCache[$columnName])) {
    return $columnCache[$columnName];
  }

  $safeColumn = mysqli_real_escape_string($conn, $columnName);
  $res = mysqli_query($conn, "SHOW COLUMNS FROM manuals_bought LIKE '$safeColumn'");
  $hasColumn = ($res && mysqli_num_rows($res) > 0);
  $columnCache[$columnName] = $hasColumn;
  return $hasColumn;
}

function exportIsExternalManualPaymentRef($refId) {
  return stripos(trim((string)$refId), 'manual_ext_') === 0;
}

function exportDisplayMatricNo($matricNo) {
  $matricNo = trim((string)$matricNo);
  if ($matricNo === '') {
    return '';
  }

  return preg_replace('/_PEND_$/i', '', $matricNo) ?? $matricNo;
}

// Check if the manual ID is provided in the POST request
if (isset($_POST['manual_id'])) {
  $requestId = exportRequestId();
  try {
    $manualId = isset($_POST['manual_id']) ? (int)$_POST['manual_id'] : 0;
    $outputMode = isset($_POST['output']) ? strtolower(trim((string)$_POST['output'])) : 'json';
    $rrr = isset($_POST['rrr']) ? trim((string)$_POST['rrr']) : '';
    if ($manualId <= 0) {
      exportJsonResponse(['status' => 'error', 'message' => 'Invalid manual ID', 'request_id' => $requestId], 400);
      exit;
    }

    $hocUserId = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
    $hocDeptId = 0;
    $hocSchoolId = isset($_SESSION['nivas_userSch']) ? (int)$_SESSION['nivas_userSch'] : 0;
    $applyHocDeptFilter = false;
    $isHocUser = isset($_SESSION['nivas_userRole']) && $_SESSION['nivas_userRole'] === 'hoc';
    if (isset($_SESSION['nivas_userRole']) && $_SESSION['nivas_userRole'] === 'hoc' && $hocUserId > 0) {
      $hocDeptRes = exportRunQuery(
        $conn,
        "SELECT dept, school FROM users WHERE id = $hocUserId LIMIT 1",
        $requestId,
        'load_hoc_dept',
        ['hoc_user_id' => $hocUserId]
      );
      if ($hocDeptRes && mysqli_num_rows($hocDeptRes) > 0) {
        $hocDeptRow = mysqli_fetch_assoc($hocDeptRes);
        $hocDeptId = isset($hocDeptRow['dept']) ? (int)$hocDeptRow['dept'] : 0;
        $hocSchoolId = isset($hocDeptRow['school']) ? (int)$hocDeptRow['school'] : $hocSchoolId;
        if ($hocDeptId > 0) {
          $applyHocDeptFilter = true;
        }
      }
      if ($hocDeptId <= 0) {
        exportJsonResponse([
          'status' => 'error',
          'message' => 'Your department is required before exporting material lists.',
          'request_id' => $requestId,
        ], 403);
        exit;
      }
    }
    $auditStatusColumn = exportResolveAuditStatusColumn($conn, $requestId);
    exportLog($requestId, 'Manual export started', [
      'manual_id' => $manualId,
      'hoc_user_id' => $hocUserId,
      'hoc_dept_id' => $hocDeptId,
      'dept_filter_applied' => $applyHocDeptFilter
    ]);

    $manualRes = exportRunQuery(
      $conn,
      "SELECT id, title, course_code, code FROM manuals WHERE id = $manualId LIMIT 1",
      $requestId,
      'load_manual',
      ['manual_id' => $manualId]
    );
    if (mysqli_num_rows($manualRes) < 1) {
      exportJsonResponse(['status' => 'error', 'message' => 'Manual not found', 'request_id' => $requestId], 404);
      exit;
    }
    $manualRow = mysqli_fetch_assoc($manualRes);

    if ($isHocUser && $hocUserId > 0) {
      $deptsHasFacultyId = false;
      $manualsHasFaculty = false;
      $manualsHasDepts = false;
      $hocFacultyId = 0;
      $hocLegacySharedVisibilityWhere = "1 = 0";
      $hocSharedVisibilityWhere = "1 = 0";

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
      if ($hocDeptId > 0) {
        $legacySharedVisibilityParts[] = "m.dept = $hocDeptId";
      }

      if ($deptsHasFacultyId && $manualsHasFaculty && $hocDeptId > 0) {
        $userDeptMetaQ = mysqli_query($conn, "SELECT faculty_id FROM depts WHERE id = $hocDeptId AND school_id = $hocSchoolId LIMIT 1");
        if ($userDeptMetaQ && mysqli_num_rows($userDeptMetaQ) > 0) {
          $userDeptMeta = mysqli_fetch_assoc($userDeptMetaQ);
          $hocFacultyId = isset($userDeptMeta['faculty_id']) ? (int)$userDeptMeta['faculty_id'] : 0;
        }
        if ($hocFacultyId > 0) {
          $legacySharedVisibilityParts[] = "(m.dept = 0 AND m.faculty = $hocFacultyId)";
        }
      }

      if (!empty($legacySharedVisibilityParts)) {
        $hocLegacySharedVisibilityWhere = implode(' OR ', $legacySharedVisibilityParts);
      }

      if ($manualsHasDepts && $hocDeptId > 0) {
        $normalized_depts_expr = "REPLACE(REPLACE(REPLACE(REPLACE(m.depts, '[', ''), ']', ''), '\"', ''), ' ', '')";
        $hocSharedVisibilityWhere = "(m.depts IS NOT NULL AND FIND_IN_SET($hocDeptId, $normalized_depts_expr) > 0) OR (m.depts IS NULL AND ($hocLegacySharedVisibilityWhere))";
      } else {
        $hocSharedVisibilityWhere = $hocLegacySharedVisibilityWhere;
      }

      $hocAccessWhere = "m.id = $manualId AND (m.user_id = $hocUserId OR (m.user_id = 0 AND m.school_id = $hocSchoolId AND ($hocSharedVisibilityWhere)))";
      $manualAccessRes = exportRunQuery(
        $conn,
        "SELECT 1 FROM manuals AS m WHERE $hocAccessWhere LIMIT 1",
        $requestId,
        'validate_hoc_manual_visibility',
        ['manual_id' => $manualId, 'hoc_user_id' => $hocUserId, 'hoc_dept_id' => $hocDeptId]
      );
      if (mysqli_num_rows($manualAccessRes) < 1) {
        exportJsonResponse([
          'status' => 'error',
          'message' => 'You are not permitted to export this material.',
          'request_id' => $requestId,
        ], 403);
        exit;
      }
    }

    $manualsBoughtHasId = exportManualsBoughtHasColumn($conn, 'id');
    $manualsBoughtHasGrantStatus = exportManualsBoughtHasColumn($conn, 'grant_status');
    $fromBoughtId = null;
    $toBoughtId = null;

    if ($manualsBoughtHasId) {
      // from_bought_id: first successful payment row that is still pending grant.
      $fromWhereParts = [
        "mb.manual_id = $manualId",
        "mb.status = 'successful'",
        material_copy_non_lost_condition($conn, 'mb'),
      ];
      $fromJoinSql = "";
      if ($applyHocDeptFilter) {
        $fromJoinSql = "JOIN users AS bu ON bu.id = mb.buyer";
        $fromWhereParts[] = "bu.dept = $hocDeptId";
      }
      if ($manualsBoughtHasGrantStatus) {
        $fromWhereParts[] = "(mb.grant_status IS NULL OR LOWER(TRIM(CAST(mb.grant_status AS CHAR))) IN ('', '0', 'pending', 'false'))";
      }
      $fromWhere = implode(" AND ", $fromWhereParts);
      $fromRangeRes = exportRunQuery(
        $conn,
        "SELECT MIN(mb.id) AS from_bought_id FROM manuals_bought AS mb $fromJoinSql WHERE $fromWhere",
        $requestId,
        'load_export_from_bought_id',
        ['manual_id' => $manualId, 'hoc_dept_id' => $hocDeptId]
      );
      $fromRangeRow = mysqli_fetch_assoc($fromRangeRes);
      if ($fromRangeRow && $fromRangeRow['from_bought_id'] !== null) {
        $fromBoughtId = (int)$fromRangeRow['from_bought_id'];
      }

      // to_bought_id: latest successful payment row for this manual.
      $toWhereParts = [
        "mb.manual_id = $manualId",
        "mb.status = 'successful'",
        material_copy_non_lost_condition($conn, 'mb'),
      ];
      $toJoinSql = "";
      if ($applyHocDeptFilter) {
        $toJoinSql = "JOIN users AS bu ON bu.id = mb.buyer";
        $toWhereParts[] = "bu.dept = $hocDeptId";
      }
      $toWhere = implode(" AND ", $toWhereParts);
      $toRangeRes = exportRunQuery(
        $conn,
        "SELECT MAX(mb.id) AS to_bought_id FROM manuals_bought AS mb $toJoinSql WHERE $toWhere",
        $requestId,
        'load_export_to_bought_id',
        ['manual_id' => $manualId, 'hoc_dept_id' => $hocDeptId]
      );
      $toRangeRow = mysqli_fetch_assoc($toRangeRes);
      if ($toRangeRow && $toRangeRow['to_bought_id'] !== null) {
        $toBoughtId = (int)$toRangeRow['to_bought_id'];
      }
    } else {
      exportLog($requestId, 'manuals_bought.id missing; from_bought_id/to_bought_id will not be populated', ['manual_id' => $manualId]);
    }

    $manualBuyerFilters = [
      "mb.manual_id = $manualId",
      "mb.status = 'successful'",
      material_copy_non_lost_condition($conn, 'mb'),
    ];
    if ($manualsBoughtHasGrantStatus) {
      // Only export students still pending in manuals_bought.
      $manualBuyerFilters[] = "(mb.grant_status IS NULL OR LOWER(TRIM(CAST(mb.grant_status AS CHAR))) IN ('', '0', 'pending', 'false'))";
    }
    if ($applyHocDeptFilter) {
      $manualBuyerFilters[] = "u.dept = $hocDeptId";
    }
    $manualBuyerWhere = implode("\n        AND ", $manualBuyerFilters);
    $boughtIdSelect = $manualsBoughtHasId ? "mb.id AS bought_id," : "0 AS bought_id,";

    $query = "
      SELECT
        $boughtIdSelect
        u.id AS user_id,
        u.first_name,
        u.last_name,
        u.matric_no,
        u.adm_year,
        mb.price,
        mb.created_at,
        mb.ref_id
      FROM
        manuals_bought AS mb
      JOIN
        users AS u
      ON
        mb.buyer = u.id
      WHERE
        $manualBuyerWhere
      ORDER BY
        u.matric_no ASC
    ";

    $result = exportRunQuery(
      $conn,
      $query,
      $requestId,
      'load_manual_buyers',
      ['manual_id' => $manualId]
    );
    $usersData = [];
    $totalAmount = 0;
    $lastStudentId = null;
    $lastPurchaseTime = null;
    $boughtIds = [];

    while ($row = mysqli_fetch_assoc($result)) {
      $price = (int)$row['price'];
      $totalAmount += $price;
      $userId = (int)$row['user_id'];
      $boughtId = isset($row['bought_id']) ? (int)$row['bought_id'] : 0;
      $createdAt = $row['created_at'];
      if ($boughtId > 0) {
        $boughtIds[] = $boughtId;
      }

      // Track the last student ID based on purchase time
      if ($lastPurchaseTime === null || strtotime($createdAt) > strtotime($lastPurchaseTime)) {
        $lastStudentId = $userId;
        $lastPurchaseTime = $createdAt;
      }

      $usersData[] = [
        'user_id' => $userId,
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'matric_no' => exportDisplayMatricNo($row['matric_no'] ?? ''),
        'adm_year' => $row['adm_year'],
        'price' => $price,
        'is_external_payment' => exportIsExternalManualPaymentRef($row['ref_id'] ?? ''),
        'payment_source_label' => exportIsExternalManualPaymentRef($row['ref_id'] ?? '') ? 'Paid outside Nivasity' : 'Paid on Nivasity',
      ];
    }

    $studentsCount = count($usersData);
    if ($studentsCount < 1) {
      exportJsonResponse([
        'status' => 'error',
        'message' => 'No pending collection students for this material. Export only works for students yet to be marked as collected.',
        'request_id' => $requestId,
      ], 409);
      exit;
    }

    // Generate and persist a verification code for this export
    $verificationCode = generateManualExportCode($conn);
    $safeCode = mysqli_real_escape_string($conn, $verificationCode);
    $downloadedAt = date('Y-m-d H:i:s');
    $safeDownloadedAt = mysqli_real_escape_string($conn, $downloadedAt);
    $manualIdInt = (int)$manualRow['id'];
    $hocUserIdInt = $hocUserId ?: 0;
    $studentsCountInt = (int)$studentsCount;
    $totalAmountInt = (int)$totalAmount;
    $boughtIdsJson = json_encode(array_values($boughtIds), JSON_UNESCAPED_SLASHES);
    if ($boughtIdsJson === false) {
      $boughtIdsJson = '[]';
    }
    $safeBoughtIdsJson = mysqli_real_escape_string($conn, $boughtIdsJson);

    // Construct the INSERT query with proper NULL handling.
    // Export creation must remain pending; grant action is handled in command center.
    $lastStudentSql = $lastStudentId ? (int)$lastStudentId : 'NULL';
    $insertColumns = ['code', 'manual_id', 'hoc_user_id', 'students_count', 'total_amount', 'downloaded_at'];
    $insertValues = ["'$safeCode'", (string)$manualIdInt, (string)$hocUserIdInt, (string)$studentsCountInt, (string)$totalAmountInt, "'$safeDownloadedAt'"];

    if (exportAuditHasColumn($conn, 'last_student_id')) {
      $insertColumns[] = 'last_student_id';
      $insertValues[] = (string)$lastStudentSql;
    }

    if (exportAuditHasColumn($conn, 'from_bought_id')) {
      $insertColumns[] = 'from_bought_id';
      $insertValues[] = ($fromBoughtId !== null) ? (string)$fromBoughtId : 'NULL';
    }
    if (exportAuditHasColumn($conn, 'to_bought_id')) {
      $insertColumns[] = 'to_bought_id';
      $insertValues[] = ($toBoughtId !== null) ? (string)$toBoughtId : 'NULL';
    }
    if (exportAuditHasColumn($conn, 'bought_ids_json')) {
      $insertColumns[] = 'bought_ids_json';
      $insertValues[] = "'$safeBoughtIdsJson'";
    }
    if ($auditStatusColumn !== '') {
      $safeAuditStatusColumn = ($auditStatusColumn === 'grant_status') ? 'grant_status' : 'status';
      $insertColumns[] = $safeAuditStatusColumn;
      $insertValues[] = "'pending'";
    }

    $insertColumnSql = implode(', ', array_map(function ($col) {
      return "`$col`";
    }, $insertColumns));
    $insertValueSql = implode(', ', $insertValues);
    $insertQuery = "INSERT INTO manual_export_audits ($insertColumnSql) VALUES ($insertValueSql)";

    exportRunQuery(
      $conn,
      $insertQuery,
      $requestId,
      'insert_export_audit',
      [
        'manual_id' => $manualIdInt,
        'hoc_user_id' => $hocUserIdInt,
        'audit_status_column' => $auditStatusColumn,
        'from_bought_id' => $fromBoughtId,
        'to_bought_id' => $toBoughtId,
        'bought_ids_count' => count($boughtIds),
        'dept_filter_applied' => $applyHocDeptFilter,
        'hoc_dept_id' => $hocDeptId
      ]
    );
    $exportAuditId = mysqli_insert_id($conn);

    // Fetch HOC basic info for display (if available)
    $hocName = null;
    $hocEmail = null;
    if ($hocUserIdInt > 0) {
      $hocRes = exportRunQuery(
        $conn,
        "SELECT first_name, last_name, email FROM users WHERE id = $hocUserIdInt LIMIT 1",
        $requestId,
        'load_hoc_details',
        ['hoc_user_id' => $hocUserIdInt]
      );
      if (mysqli_num_rows($hocRes) > 0) {
        $hocRow = mysqli_fetch_assoc($hocRes);
        $hocName = trim($hocRow['first_name'] . ' ' . $hocRow['last_name']);
        $hocEmail = $hocRow['email'];
      }
    }

    exportLog($requestId, 'Manual export completed', [
      'manual_id' => $manualIdInt,
      'students_count' => $studentsCountInt,
      'total_amount' => $totalAmountInt,
      'last_student_id' => $lastStudentId,
      'from_bought_id' => $fromBoughtId,
      'to_bought_id' => $toBoughtId,
      'bought_ids_count' => count($boughtIds),
    ]);

    if ($outputMode === 'pdf') {
      $heading = 'PAYMENTS FOR ' . strtoupper((string)$manualRow['course_code']) . ' MANUAL';
      $verificationUrl = nivasity_app_url('manual-export-verify.php?code=' . urlencode($verificationCode));
      $metaLines = [
        'Verification Code: ' . $verificationCode,
        'Total Students: ' . $studentsCount . '    Total Amount: NGN ' . number_format((float)$totalAmount, 0),
        'Date Exported: ' . date('j M Y, g:ia', strtotime($downloadedAt)),
      ];

      if ($hocName) {
        $metaLines[] = 'HOC: ' . $hocName . ($hocEmail ? ' (' . $hocEmail . ')' : '');
      }

      if ($rrr !== '') {
        $metaLines[] = 'RRR: ' . $rrr;
      }

      $metaLines[] = 'You can verify this export at ' . $verificationUrl;

      $headers = [
        ['key' => 'sn', 'label' => 'S/N', 'x' => 50, 'width' => 24],
        ['key' => 'name', 'label' => 'NAMES', 'x' => 80, 'width' => 205],
        ['key' => 'matric_no', 'label' => 'MATRIC NO', 'x' => 290, 'width' => 82],
        ['key' => 'adm_year', 'label' => 'ADMISSION YEAR', 'x' => 378, 'width' => 72],
        ['key' => 'price', 'label' => 'PRICE PAID', 'x' => ($rrr !== '' ? 484 : 545), 'width' => 56, 'align' => 'right'],
      ];
      if ($rrr !== '') {
        $headers[] = ['key' => 'rrr', 'label' => 'RRR', 'x' => 545, 'width' => 55, 'align' => 'right'];
      }

      $pdfRows = [];
      foreach ($usersData as $index => $row) {
        $name = (string)$row['name'];
        if (!empty($row['is_external_payment'])) {
          $name .= "\nPaid outside Nivasity";
        }
        $pdfRow = [
          'sn' => (string)($index + 1),
          'name' => $name,
          'matric_no' => (string)$row['matric_no'],
          'adm_year' => (string)$row['adm_year'],
          'price' => number_format((float)$row['price'], 0),
        ];
        if ($rrr !== '') {
          $pdfRow['rrr'] = $rrr;
        }
        $pdfRows[] = $pdfRow;
      }

      $pdfBinary = manual_export_pdf_render([
        'heading' => $heading,
        'meta_lines' => $metaLines,
        'headers' => $headers,
        'rows' => $pdfRows,
      ], dirname(__DIR__) . '/assets/images/nivasity-main.png');

      $filename = 'manual-export-' . preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$manualRow['course_code']) . '-' . $verificationCode . '.pdf';
      exportPdfResponse($pdfBinary, $filename);
      exit;
    }

    $response = [
      'status' => 'success',
      'code' => $verificationCode,
      'students_count' => $studentsCount,
      'total_amount' => $totalAmount,
      'downloaded_at' => $downloadedAt,
      'downloaded_at_readable' => date('j M Y, g:ia', strtotime($downloadedAt)),
      'manual' => [
        'id' => $manualIdInt,
        'title' => $manualRow['title'],
        'course_code' => $manualRow['course_code'],
        'code' => $manualRow['code'],
      ],
      'export_audit_id' => (int)$exportAuditId,
      'from_bought_id' => $fromBoughtId,
      'to_bought_id' => $toBoughtId,
      'bought_ids_count' => count($boughtIds),
      'hoc' => [
        'id' => $hocUserIdInt,
        'name' => $hocName,
        'email' => $hocEmail,
      ],
      'rows' => $usersData,
      'request_id' => $requestId,
    ];

    exportJsonResponse($response);
  } catch (Throwable $e) {
    exportLog($requestId, 'Manual export failed', [
      'manual_id' => isset($manualId) ? (int)$manualId : 0,
      'hoc_user_id' => isset($hocUserId) ? (int)$hocUserId : 0,
      'error' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine(),
    ]);
    if (isset($outputMode) && $outputMode === 'pdf') {
      exportJsonResponse([
        'status' => 'error',
        'message' => 'Unable to export material right now. Please try again.',
        'request_id' => $requestId,
      ], 500);
    } else {
      exportJsonResponse([
        'status' => 'error',
        'message' => 'Unable to export material right now. Please try again.',
        'request_id' => $requestId,
      ], 500);
    }
  }
} elseif (isset($_POST['event_id'])) {
  $requestId = exportRequestId();
  try {
    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    if ($event_id <= 0) {
      exportJsonResponse(['status' => 'error', 'message' => 'Invalid event ID', 'request_id' => $requestId], 400);
      exit;
    }

    $userIdsResult = exportRunQuery(
      $conn,
      "SELECT * FROM event_tickets WHERE event_id = $event_id ORDER BY created_at ASC",
      $requestId,
      'load_event_buyers',
      ['event_id' => $event_id]
    );

    $usersData = [];
    while ($row = mysqli_fetch_assoc($userIdsResult)) {
      $userId = (int)$row['buyer'];

      $userDetailsQuery = "SELECT first_name, last_name FROM users WHERE id = $userId";
      $userDetailsResult = exportRunQuery(
        $conn,
        $userDetailsQuery,
        $requestId,
        'load_event_buyer_details',
        ['event_id' => $event_id, 'buyer_id' => $userId]
      );

      // Fetch user details and add them to the result array
      if ($userDetailsRow = mysqli_fetch_assoc($userDetailsResult)) {
        $usersData[] = [
          'name' => $userDetailsRow['first_name'] . ' ' . $userDetailsRow['last_name'],
          'created_at' => $row['created_at'],
          'ref_id' => $row['ref_id'],
        ];
      }
    }

    exportJsonResponse($usersData);
  } catch (Throwable $e) {
    exportLog($requestId, 'Event export failed', [
      'event_id' => isset($event_id) ? (int)$event_id : 0,
      'error' => $e->getMessage(),
      'file' => $e->getFile(),
      'line' => $e->getLine(),
    ]);
    exportJsonResponse([
      'status' => 'error',
      'message' => 'Unable to export event list right now. Please try again.',
      'request_id' => $requestId,
    ], 500);
  }
} else {
  // Handle the case where manual_id is not provided
  exportJsonResponse(['status' => 'error', 'message' => 'Manual ID not provided'], 400);
}

?>
