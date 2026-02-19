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

// Check if the manual ID is provided in the POST request
if (isset($_POST['manual_id'])) {
  header('Content-Type: application/json');

  $manualId = isset($_POST['manual_id']) ? (int)$_POST['manual_id'] : 0;
  if ($manualId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid manual ID']);
    exit;
  }

  $hocUserId = isset($_SESSION['nivas_userId']) ? (int)$_SESSION['nivas_userId'] : 0;

  // Fetch manual details
  $manualRes = mysqli_query($conn, "SELECT id, title, course_code, code FROM manuals WHERE id = $manualId LIMIT 1");
  if (!$manualRes || mysqli_num_rows($manualRes) < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Manual not found']);
    exit;
  }
  $manualRow = mysqli_fetch_assoc($manualRes);

  // Fetch user details from the users table and price from manuals_bought table
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
      mb.manual_id = $manualId
      AND mb.status = 'successful'
    ORDER BY
      u.matric_no ASC
  ";

  $result = mysqli_query($conn, $query);
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
      'status' => 'given', // Default status, will be updated later
    ];
  }

  $studentsCount = count($usersData);

  // Check for previous exports with status='granted' for this manual
  $grantedStudentIds = [];
  $prevExportQuery = "
    SELECT last_student_id
    FROM manual_export_audits
    WHERE manual_id = $manualId
      AND status = 'granted'
      AND last_student_id IS NOT NULL
    ORDER BY downloaded_at DESC
    LIMIT 1
  ";
  $prevExportRes = mysqli_query($conn, $prevExportQuery);
  
  if ($prevExportRes && mysqli_num_rows($prevExportRes) > 0) {
    $prevExportRow = mysqli_fetch_assoc($prevExportRes);
    $prevLastStudentId = (int)$prevExportRow['last_student_id'];
    
    // Get all student IDs up to and including the last granted student ID
    // based on their purchase order (created_at)
    $grantedQuery = "
      SELECT DISTINCT mb.buyer
      FROM manuals_bought AS mb
      WHERE mb.manual_id = $manualId
        AND mb.status = 'successful'
        AND mb.created_at <= (
          SELECT created_at
          FROM manuals_bought
          WHERE manual_id = $manualId
            AND buyer = $prevLastStudentId
            AND status = 'successful'
          ORDER BY created_at DESC
          LIMIT 1
        )
    ";
    $grantedRes = mysqli_query($conn, $grantedQuery);
    while ($grantedRow = mysqli_fetch_assoc($grantedRes)) {
      $grantedStudentIds[] = (int)$grantedRow['buyer'];
    }
  }
  
  // Update status for granted students
  foreach ($usersData as &$user) {
    if (in_array($user['user_id'], $grantedStudentIds)) {
      $user['status'] = 'granted';
    }
  }
  unset($user); // Break reference

  // Generate and persist a verification code for this export
  $verificationCode = generateManualExportCode($conn);
  $safeCode = mysqli_real_escape_string($conn, $verificationCode);
  $downloadedAt = date('Y-m-d H:i:s');
  $safeDownloadedAt = mysqli_real_escape_string($conn, $downloadedAt);
  $manualIdInt = (int)$manualRow['id'];
  $hocUserIdInt = $hocUserId ?: 0;
  $studentsCountInt = (int)$studentsCount;
  $totalAmountInt = (int)$totalAmount;
  
  // Construct the INSERT query with proper NULL handling
  if ($lastStudentId) {
    $lastStudentIdValue = (int)$lastStudentId;
    $insertQuery = "INSERT INTO manual_export_audits (code, manual_id, hoc_user_id, students_count, total_amount, downloaded_at, last_student_id, status)
                    VALUES ('$safeCode', $manualIdInt, $hocUserIdInt, $studentsCountInt, $totalAmountInt, '$safeDownloadedAt', $lastStudentIdValue, 'given')";
  } else {
    $insertQuery = "INSERT INTO manual_export_audits (code, manual_id, hoc_user_id, students_count, total_amount, downloaded_at, last_student_id, status)
                    VALUES ('$safeCode', $manualIdInt, $hocUserIdInt, $studentsCountInt, $totalAmountInt, '$safeDownloadedAt', NULL, 'given')";
  }

  mysqli_query($conn, $insertQuery);

  // Fetch HOC basic info for display (if available)
  $hocName = null;
  $hocEmail = null;
  if ($hocUserIdInt > 0) {
    $hocRes = mysqli_query($conn, "SELECT first_name, last_name, email FROM users WHERE id = $hocUserIdInt LIMIT 1");
    if ($hocRes && mysqli_num_rows($hocRes) > 0) {
      $hocRow = mysqli_fetch_assoc($hocRes);
      $hocName = trim($hocRow['first_name'] . ' ' . $hocRow['last_name']);
      $hocEmail = $hocRow['email'];
    }
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
    'hoc' => [
      'id' => $hocUserIdInt,
      'name' => $hocName,
      'email' => $hocEmail,
    ],
    'rows' => $usersData,
  ];

  echo json_encode($response);
} elseif (isset($_POST['event_id'])) {
  $event_id = $_POST['event_id'];

  // Fetch user IDs from the manuals_bought_2 table based on the manual ID
  $userIdsResult = mysqli_query($conn, "SELECT * FROM event_tickets WHERE event_id = $event_id ORDER BY created_at ASC");

  // Fetch user details from the users table based on the obtained user IDs
  $usersData = [];
  while ($row = mysqli_fetch_assoc($userIdsResult)) {
    $userId = $row['buyer'];

    $userDetailsQuery = "SELECT first_name, last_name FROM users WHERE id = $userId";
    $userDetailsResult = mysqli_query($conn, $userDetailsQuery);

    // Fetch user details and add them to the result array
    if ($userDetailsRow = mysqli_fetch_assoc($userDetailsResult)) {
      $usersData[] = [
        'name' => $userDetailsRow['first_name'] . ' ' . $userDetailsRow['last_name'],
        'created_at' => $row['created_at'],
        'ref_id' => $row['ref_id'],
      ];
    }
  }

  // Return the result as JSON
  header('Content-Type: application/json');
  echo json_encode($usersData);
} else {
  // Handle the case where manual_id is not provided
  echo json_encode(['error' => 'Manual ID not provided']);
}

?>
