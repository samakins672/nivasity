<?php
require("../../../config/db.php");

if (!defined('APP_SCHOOL_ID')) {
  define('APP_SCHOOL_ID', 1);
}

if (!defined('APP_SCHOOL_NAME')) {
  define('APP_SCHOOL_NAME', 'FUNAAB');
}

if (!defined('APP_DOMAIN')) {
  define('APP_DOMAIN', 'https://funaab.nivasity.com');
}

if (!function_exists('nivasity_app_domain')) {
  function nivasity_app_domain() {
    return rtrim(APP_DOMAIN, '/');
  }
}

if (!function_exists('nivasity_app_url')) {
  function nivasity_app_url($path = '') {
    $baseUrl = nivasity_app_domain();
    $normalizedPath = ltrim((string) $path, '/');

    if ($normalizedPath === '') {
      return $baseUrl;
    }

    return $baseUrl . '/' . $normalizedPath;
  }
}

if (!function_exists('nivasity_asset_url')) {
  function nivasity_asset_url($path = '') {
    return nivasity_app_url($path);
  }
}

if (!function_exists('nivasity_school_id')) {
  function nivasity_school_id() {
    return (int) APP_SCHOOL_ID;
  }
}

$conn = mysqli_connect("localhost", DB_USERNAME, DB_PASSWORD, "niverpay_db");

if (!$conn) {
  die("Error: Failed to connect to database!");
}

?>
