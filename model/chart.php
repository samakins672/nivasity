<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];
if ($_SESSION['nivas_userRole'] == 'hoc') {
  $item_table = "manuals_bought";
} else {
  $item_table = "event_tickets";
}

$hocDeptInt = isset($_SESSION['nivas_userDept']) ? (int)$_SESSION['nivas_userDept'] : 0;
$hocSchoolInt = (int)$school_id;
$hocFacultyId = 0;
$hocManualVisibilityWhere = "m.user_id = $user_id";
$hocLegacySharedVisibilityWhere = "1 = 0";
$hocSharedVisibilityWhere = "1 = 0";

if ($_SESSION['nivas_userRole'] == 'hoc') {
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
        $hocFacultyId = isset($userDeptMeta['faculty_id']) ? (int)$userDeptMeta['faculty_id'] : 0;
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
    error_log('[model/chart] hoc visibility fallback: ' . $e->getMessage());
  }
}

// Get the first day and last day of the current week
$currentWeekStart = date('Y-m-d', strtotime('last sunday'));
$currentWeekEnd = date('Y-m-d', strtotime('next saturday'));

// Get the first day and last day of the previous week
$prevWeekStart = date('Y-m-d', strtotime('last sunday', strtotime($currentWeekStart)));
$prevWeekEnd = date('Y-m-d', strtotime('last saturday', strtotime($currentWeekStart)));

// Fetch data for the current and last week
$thisWeekSales = array();
$lastWeekSales = array();

for ($i = 0; $i < 7; $i++) {
  $currentDay = date('Y-m-d', strtotime("$currentWeekStart +$i days"));
  $currentDay2 = date('Y-m-d', strtotime("$prevWeekStart +$i days"));

  if ($_SESSION['nivas_userRole'] == 'hoc') {
    $hocBuyerDeptWhere = ($hocDeptInt > 0) ? "bu.dept = $hocDeptInt" : "1 = 0";

    $thisDaySql = "SELECT IFNULL(SUM(mb.price), 0) AS total_sales
                   FROM manuals_bought AS mb
                   JOIN manuals AS m ON m.id = mb.manual_id
                   JOIN users AS bu ON bu.id = mb.buyer
                   WHERE mb.status = 'successful'
                     AND DATE(mb.created_at) = '$currentDay'
                     AND $hocBuyerDeptWhere
                     AND ($hocManualVisibilityWhere)";
    $thisDayResult = $conn->query($thisDaySql);
    $thisWeekSales[] = ($thisDayResult && $thisDayResult->num_rows > 0) ? $thisDayResult->fetch_assoc()['total_sales'] : 0;

    $lastWeekSql = "SELECT IFNULL(SUM(mb.price), 0) AS total_sales
                    FROM manuals_bought AS mb
                    JOIN manuals AS m ON m.id = mb.manual_id
                    JOIN users AS bu ON bu.id = mb.buyer
                    WHERE mb.status = 'successful'
                      AND DATE(mb.created_at) = '$currentDay2'
                      AND $hocBuyerDeptWhere
                      AND ($hocManualVisibilityWhere)";
    $lastWeekResult = $conn->query($lastWeekSql);
    $lastWeekSales[] = ($lastWeekResult && $lastWeekResult->num_rows > 0) ? $lastWeekResult->fetch_assoc()['total_sales'] : 0;
  } else {
    $thisDaySql = "SELECT IFNULL(SUM(price), 0) AS total_sales FROM $item_table WHERE seller = $user_id AND DATE(created_at) = '$currentDay'";
    $thisDayResult = $conn->query($thisDaySql);
    $thisWeekSales[] = ($thisDayResult && $thisDayResult->num_rows > 0) ? $thisDayResult->fetch_assoc()['total_sales'] : 0;

    $lastWeekSql = "SELECT IFNULL(SUM(price), 0) AS total_sales FROM $item_table WHERE seller = $user_id AND DATE(created_at) = '$currentDay2'";
    $lastWeekResult = $conn->query($lastWeekSql);
    $lastWeekSales[] = ($lastWeekResult && $lastWeekResult->num_rows > 0) ? $lastWeekResult->fetch_assoc()['total_sales'] : 0;
  }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['thisWeekSales' => $thisWeekSales, 'lastWeekSales' => $lastWeekSales]);
?>
