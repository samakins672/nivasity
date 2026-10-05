<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/tenant.php';
require_once __DIR__ . '/auth_redirect.php';

$conn = mysqli_connect("localhost", DB_USERNAME, DB_PASSWORD, "niverpay_db");

if (!$conn) {
  die("Error: Failed to connect to database!");
}

// Talk to the database in UTF-8 (like the command center). Without this the connection used the
// server default (latin1 on the live server), so text like "it's" with a curly apostrophe or
// emoji was stored double-encoded and showed as "itâ€™s" in the command center.
mysqli_set_charset($conn, 'utf8mb4');

nivasity_resolve_tenant_from_request($conn);

// Set the timezone to Africa/Lagos
date_default_timezone_set('Africa/Lagos');

?>
