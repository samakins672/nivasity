<?php
session_start();
include('config.php');
include('functions.php');
$statusRes = $schools = 'failed';

// Check if the manual ID is provided in the POST request
if (isset($_POST['manual_id'])) {
  $manualId = $_POST['manual_id'];

  // Fetch user details from the users table and price from manuals_bought table
  $query = "
    SELECT
      u.first_name,
      u.last_name,
      u.matric_no,
      u.adm_year,
      mb.price
    FROM
      manuals_bought AS mb
    JOIN
      users AS u
    ON
      mb.buyer = u.id
    WHERE
      mb.manual_id = $manualId
    ORDER BY
      u.matric_no ASC
  ";

  $result = mysqli_query($conn, $query);
  $usersData = [];

  while ($row = mysqli_fetch_assoc($result)) {
    $usersData[] = [
      'name' => $row['first_name'] . ' ' . $row['last_name'],
      'matric_no' => $row['matric_no'],
      'adm_year' => $row['adm_year'],
      'price' => $row['price'],
    ];
  }

  // Return the result as JSON
  header('Content-Type: application/json');
  echo json_encode($usersData);
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