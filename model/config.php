<?php
require("../config/db.php");
$conn = mysqli_connect("localhost", DB_USERNAME, DB_PASSWORD, "niverpay_db");

if (!$conn) {
  die("Error: Failed to connect to database!");
}

// Set the timezone to Africa/Lagos
date_default_timezone_set('Africa/Lagos');

$tawkEnvironment = __DIR__ . '/../config/tawk.php';
if (!file_exists($tawkEnvironment)) {
  $tawkEnvironment = __DIR__ . '/../config/tawk.example.php';
}

if (file_exists($tawkEnvironment)) {
  require_once $tawkEnvironment;
}

if (!defined('TAWK_ENABLED')) {
  define('TAWK_ENABLED', true);
}

if (!defined('TAWK_WIDGET_ID')) {
  define('TAWK_WIDGET_ID', '6722bbbb4304e3196adae0cd/1ibfqqm4s');
}

if (!defined('TAWK_HELP_URL')) {
  define('TAWK_HELP_URL', 'https://nivasity.tawk.help');
}

if (!function_exists('nivasity_tawk_payload')) {
  function nivasity_tawk_payload(): array {
    return [
      'enabled' => TAWK_ENABLED,
      'widgetId' => TAWK_WIDGET_ID,
      'helpUrl' => TAWK_HELP_URL,
    ];
  }
}

if (!function_exists('nivasity_render_tawk_config')) {
  function nivasity_render_tawk_config(): void {
    header('Content-Type: application/javascript; charset=utf-8');
    echo "window.NIVASITY_ENV = window.NIVASITY_ENV || {};\n";
    echo "window.NIVASITY_ENV.tawk = " . json_encode(nivasity_tawk_payload(), JSON_UNESCAPED_SLASHES) . ";\n";
  }
}

?>
