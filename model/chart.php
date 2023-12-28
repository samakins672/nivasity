<?php
session_start();
include('config.php');
include('mail.php');
include('functions.php');

$user_id = $_SESSION['nivas_userId'];
$school_id = $_SESSION['nivas_userSch'];

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

  $thisDaySql = "SELECT IFNULL(SUM(price), 0) AS total_sales FROM manuals_bought_$school_id WHERE seller = $user_id AND DATE(created_at) = '$currentDay'";
  $thisDayResult = $conn->query($thisDaySql);
  $thisWeekSales[] = ($thisDayResult->num_rows > 0) ? $thisDayResult->fetch_assoc()['total_sales'] : 0;
  
  $lastWeekSql = "SELECT IFNULL(SUM(price), 0) AS total_sales FROM manuals_bought_$school_id WHERE seller = $user_id AND DATE(created_at) = '$currentDay2'";
  $lastWeekResult = $conn->query($lastWeekSql);
  $lastWeekSales[] = ($lastWeekResult->num_rows > 0) ? $lastWeekResult->fetch_assoc()['total_sales'] : 0;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['thisWeekSales' => $thisWeekSales, 'lastWeekSales' => $lastWeekSales]);
?>
