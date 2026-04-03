<?php
require("../../../config/db.php");
require_once __DIR__ . '/../../model/tenant.php';

$conn = mysqli_connect("localhost", DB_USERNAME, DB_PASSWORD, "niverpay_db");

if (!$conn) {
  die("Error: Failed to connect to database!");
}

nivasity_resolve_tenant_from_request($conn);

?>
