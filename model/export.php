<?php
session_start();
include('config.php');
include('functions.php');
$statusRes = $schools = 'failed';

// Check if the manual ID is provided in the POST request
if (isset($_POST['manual_id'])) {
  $school = $_SESSION['nivas_userSch'];
  $manualId = $_POST['manual_id'];

  // Fetch user IDs from the manuals_bought_2 table based on the manual ID
  $userIdsResult = mysqli_query($conn, "SELECT buyer FROM manuals_bought_$school WHERE manual_id = $manualId");

  // Fetch user details from the users table based on the obtained user IDs
  $usersData = [];
  while ($row = mysqli_fetch_assoc($userIdsResult)) {
    $userId = $row['buyer'];

    // Modify the query to include ORDER BY matric_no
    $userDetailsQuery = "SELECT first_name, last_name, matric_no FROM users WHERE id = $userId ORDER BY matric_no ASC";
    $userDetailsResult = mysqli_query($conn, $userDetailsQuery);

    // Fetch user details and add them to the result array
    if ($userDetailsRow = mysqli_fetch_assoc($userDetailsResult)) {
      $usersData[] = [
        'name' => $userDetailsRow['first_name'] . ' ' . $userDetailsRow['last_name'],
        'matric_no' => $userDetailsRow['matric_no'],
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