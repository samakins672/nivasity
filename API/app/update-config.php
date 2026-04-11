<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$tableExistsResult = mysqli_query($conn, "SHOW TABLES LIKE 'app_update_configs'");
if (!$tableExistsResult || mysqli_num_rows($tableExistsResult) < 1) {
    sendApiError('App update configuration is not available. Apply the latest SQL update.', 500);
}

$query = mysqli_query($conn, "SELECT * FROM app_update_configs ORDER BY id DESC LIMIT 1");
if (!$query) {
    sendApiError('Failed to retrieve app update configuration', 500);
}

$row = mysqli_fetch_assoc($query);
if (!$row) {
    sendApiError('No app update configuration has been configured yet', 404);
}

sendApiSuccess('App update configuration retrieved successfully', [
    'android' => [
        'latestVersion' => (string)($row['android_latest_version'] ?? ''),
        'minimumVersion' => (string)($row['android_minimum_version'] ?? ''),
        'storeUrl' => (string)($row['android_store_url'] ?? ''),
        'title' => (string)($row['android_title'] ?? ''),
        'message' => (string)($row['android_message'] ?? ''),
        'required' => (bool)($row['android_required'] ?? 0),
    ],
    'ios' => [
        'latestVersion' => (string)($row['ios_latest_version'] ?? ''),
        'minimumVersion' => (string)($row['ios_minimum_version'] ?? ''),
        'storeUrl' => (string)($row['ios_store_url'] ?? ''),
        'title' => (string)($row['ios_title'] ?? ''),
        'message' => (string)($row['ios_message'] ?? ''),
        'required' => (bool)($row['ios_required'] ?? 0),
    ],
]);
?>