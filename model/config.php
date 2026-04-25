<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/auth_redirect.php';

$conn = mysqli_connect("localhost", DB_USERNAME, DB_PASSWORD, "niverpay_db");

if (!$conn) {
  die("Error: Failed to connect to database!");
}

nivasity_resolve_tenant_from_request($conn);

// Set the timezone to Africa/Lagos
date_default_timezone_set('Africa/Lagos');

?>
