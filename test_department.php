
<?php
include('model/config.php');

// Simulate user input
$_POST['setup'] = true;
$_POST['user_id'] = 1; // Replace with a valid user ID for testing
$_POST['dept'] = 'A new department';
$_POST['adm_year'] = '2023';
$_POST['matric_no'] = 'TEST/123';
$_POST['school_id'] = 1; // Replace with a valid school ID for testing

// Include the user.php file to process the request
include('model/user.php');

// Check if the new department was added to the database
$dept_query = mysqli_query($conn, "SELECT * FROM depts WHERE name = 'A new department'");
if (mysqli_num_rows($dept_query) > 0) {
    echo "Test failed: New department was added to the database.";
} else {
    echo "Test passed: New department was not added to the database.";
}
?>
