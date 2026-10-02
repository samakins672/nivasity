<?php
// API: The survey to show in the banner, or null. Hidden after 5 dismissals or once answered.
// When there is no survey, `reason` says why (helps admins check a survey that "is active"):
//   tables_missing   survey tables are not in this database (run the cc_dashboard migrations)
//   no_banner_survey no survey is published, unexpired and flagged "show as banner"
//   answered         this student already submitted it (matched by email)
//   dismissed        this student closed it 5 or more times
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/survey_banner.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
$userId = (int) $user['id'];
$email = strtolower(trim((string) ($user['email'] ?? '')));

$survey = surveyBannerGetActiveForUser($conn, $userId, $email);
if ($survey !== null) {
    $survey['url'] = 'https://nivasity.com/survey/' . rawurlencode((string) $survey['slug']);
    sendApiSuccess('Survey retrieved successfully', ['survey' => $survey]);
}

$reason = 'no_banner_survey';
if (!surveyBannerTablesReady($conn)) {
    $reason = 'tables_missing';
} else {
    $rs = mysqli_query(
        $conn,
        "SELECT id FROM surveys
          WHERE show_as_banner = 1 AND status = 'published'
            AND (expiry_date IS NULL OR expiry_date > NOW())
          ORDER BY updated_at DESC LIMIT 1"
    );
    $row = $rs ? mysqli_fetch_assoc($rs) : null;
    if ($row) {
        $surveyId = (int) $row['id'];
        if (surveyBannerHasResponded($conn, $surveyId, $email)) {
            $reason = 'answered';
        } elseif (surveyBannerHasDismissed($conn, $surveyId, $userId)) {
            $reason = 'dismissed';
        }
    }
}

sendApiSuccess('Survey retrieved successfully', [
    'survey' => null,
    'reason' => $reason,
]);
