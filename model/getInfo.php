<?php
include('config.php');
include('functions.php');
$statusRes = $schools = 'failed';

if (isset($_GET['get_data'])) {
  $get_data = $_GET['get_data'];

  if ($get_data == 'schools') {
    $school_query = mysqli_query($conn, "SELECT * FROM schools WHERE status = 'active'");

    if (mysqli_num_rows($school_query) >= 1) {
      $schools = array();

      while ($school = mysqli_fetch_array($school_query)) {
        $schools[] = array(
          'id' => $school['id'],
          'name' => $school['name']
        );
      }

      $statusRes = "success";

    } else {
      $statusRes = "not found";
    }
  }
}

$responseData = array(
  "status" => "$statusRes",
  "schools" => $schools
);

// Set the appropriate headers for JSON response
header('Content-Type: application/json');

// Encode the data as JSON and send it
echo json_encode($responseData);
?>