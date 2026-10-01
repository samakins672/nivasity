<?php
// API: Active, non-expired system alerts (public). color: red (default) | green | info
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/system_alerts.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$alerts = [];
foreach (get_active_system_alerts($conn) as $alert) {
    $alerts[] = [
        'id' => (int)$alert['id'],
        'title' => (string)($alert['title'] ?? ''),
        'message' => (string)($alert['message'] ?? ''),
        'color' => normalize_system_alert_color($alert['alert_color'] ?? 'red'),
        'expiry_date' => $alert['expiry_date'] ?? null,
        'created_at' => $alert['created_at'] ?? null,
    ];
}

sendApiSuccess('System alerts retrieved successfully', [
    'alerts' => $alerts,
]);
