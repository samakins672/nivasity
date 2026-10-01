<?php
// API: Dismiss the survey banner once. JSON: { survey_id }
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/survey_banner.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}
$surveyId = (int)($input['survey_id'] ?? 0);
if ($surveyId <= 0) {
    sendApiError('Invalid survey', 400);
}

if (!surveyBannerDismiss($conn, $surveyId, (int)$user['id'])) {
    sendApiError('Unable to dismiss the survey right now.', 500);
}

sendApiSuccess('Survey dismissed', null);
