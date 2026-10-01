<?php
// API: The survey to show in the banner, or null. Hidden after 5 dismissals or once answered.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/survey_banner.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);

$survey = surveyBannerGetActiveForUser($conn, (int)$user['id'], (string)($user['email'] ?? ''));
if ($survey !== null) {
    $survey['url'] = 'https://nivasity.com/survey/' . rawurlencode((string)$survey['slug']);
}

sendApiSuccess('Survey retrieved successfully', [
    'survey' => $survey,
]);
