<?php
session_start();
include('config.php');
include('functions.php');
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

// Check if the manual ID is provided in the POST request
if (isset($_POST['manual_id'])) {
  $requestId = exportRequestId();
  try {
    $manualId = isset($_POST['manual_id']) ? (int)$_POST['manual_id'] : 0;
    if ($manualId <= 0) {
      exportJsonResponse(['status' => 'error', 'message' => 'Invalid manual ID', 'request_id' => $requestId], 400);
      exit;
    }

    $hocUserId = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;
    $auditStatusColumn = exportResolveAuditStatusColumn($conn, $requestId);
    exportLog($requestId, 'Manual export started', ['manual_id' => $manualId, 'hoc_user_id' => $hocUserId]);

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

    $manualBuyerFilters = [
      "mb.manual_id = $manualId",
      "mb.status = 'successful'",
    ];
    if (exportManualsBoughtHasColumn($conn, 'grant_status')) {
      // Only export students still pending in manuals_bought.
      $manualBuyerFilters[] = "(mb.grant_status IS NULL OR LOWER(TRIM(CAST(mb.grant_status AS CHAR))) IN ('', '0', 'pending', 'false'))";
    }
    $manualBuyerWhere = implode("\n        AND ", $manualBuyerFilters);

    $query = "
      SELECT
        u.id AS user_id,
        u.first_name,
        u.last_name,
        u.matric_no,
        u.adm_year,
        mb.price,
        mb.created_at
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

    while ($row = mysqli_fetch_assoc($result)) {
      $price = (int)$row['price'];
      $totalAmount += $price;
      $userId = (int)$row['user_id'];
      $createdAt = $row['created_at'];

      // Track the last student ID based on purchase time
      if ($lastPurchaseTime === null || strtotime($createdAt) > strtotime($lastPurchaseTime)) {
        $lastStudentId = $userId;
        $lastPurchaseTime = $createdAt;
      }

      $usersData[] = [
        'user_id' => $userId,
        'name' => $row['first_name'] . ' ' . $row['last_name'],
        'matric_no' => $row['matric_no'],
        'adm_year' => $row['adm_year'],
        'price' => $price,
      ];
    }

    $studentsCount = count($usersData);
    if ($studentsCount < 1) {
      exportJsonResponse([
        'status' => 'error',
        'message' => 'No pending students to export for this material.',
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

    // Construct the INSERT query with proper NULL handling.
    // Export creation must remain pending; grant action is handled in command center.
    $lastStudentSql = $lastStudentId ? (int)$lastStudentId : 'NULL';
    if ($auditStatusColumn !== '') {
      $safeAuditStatusColumn = ($auditStatusColumn === 'grant_status') ? 'grant_status' : 'status';
      $auditStatusInitial = 'pending';
      $insertQuery = "INSERT INTO manual_export_audits (code, manual_id, hoc_user_id, students_count, total_amount, downloaded_at, last_student_id, `$safeAuditStatusColumn`)
                      VALUES ('$safeCode', $manualIdInt, $hocUserIdInt, $studentsCountInt, $totalAmountInt, '$safeDownloadedAt', $lastStudentSql, '$auditStatusInitial')";
    } else {
      $insertQuery = "INSERT INTO manual_export_audits (code, manual_id, hoc_user_id, students_count, total_amount, downloaded_at, last_student_id)
                      VALUES ('$safeCode', $manualIdInt, $hocUserIdInt, $studentsCountInt, $totalAmountInt, '$safeDownloadedAt', $lastStudentSql)";
    }
    exportRunQuery(
      $conn,
      $insertQuery,
      $requestId,
      'insert_export_audit',
      ['manual_id' => $manualIdInt, 'hoc_user_id' => $hocUserIdInt, 'audit_status_column' => $auditStatusColumn]
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
    ]);

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
    exportJsonResponse([
      'status' => 'error',
      'message' => 'Unable to export material right now. Please try again.',
      'request_id' => $requestId,
    ], 500);
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
