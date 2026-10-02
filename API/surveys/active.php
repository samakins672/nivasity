<?php
// API: The survey to show in the banner, or null. Hidden after 5 dismissals or once answered.
// When there is no survey, `reason` says why (helps admins check a survey that "is active"):
//   tables_missing   survey tables are not in this database (run the cc_dashboard migrations)
//   no_banner_survey no survey is published, unexpired and flagged "show as banner"
//   answered         this student already submitted it (matched by email)
//   dismissed        this student closed it 5 or more times
// Diagnostics: if PHP stops without a reply (fatal error, exit in an included file), say why.
// Not for CORS preflight (OPTIONS), which config.php answers with an empty 200 on purpose.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
ob_start();
register_shutdown_function(static function () {
    $out = ob_get_level() > 0 ? ob_get_clean() : '';
    if ($out !== '' && $out !== false) {
        echo $out;
        return;
    }
    $err = error_get_last();
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode([
        'status' => 'error',
        'message' => 'Survey check stopped without a reply',
        'php_error' => $err ? ($err['message'] . ' in ' . basename((string) $err['file']) . ':' . $err['line']) : null,
    ]);
});
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/survey_banner.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

$user = authenticateApiRequest($conn);
$userId = (int) $user['id'];
$email = strtolower(trim((string) ($user['email'] ?? '')));

// Any database error is returned as JSON (instead of an empty 500) so it can be diagnosed.
set_exception_handler(static function (Throwable $e) {
    error_log('[surveys/active] ' . $e->getMessage());
    sendApiError('Survey check failed: ' . $e->getMessage(), 500);
});

$survey = surveyBannerGetActiveForUser($conn, $userId, $email);
if ($survey !== null) {
    $survey['url'] = 'https://nivasity.com/survey/' . rawurlencode((string) $survey['slug']);
    sendApiSuccess('Survey retrieved successfully', ['survey' => $survey]);
}

$reason = 'no_banner_survey';
$row = null;
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
    // Which account was checked, so admins can match it against the survey tables
    'checked_user_id' => $userId,
    'checked_email' => $email,
    'banner_survey_id' => isset($row['id']) ? (int) $row['id'] : null,
]);
