<?php

// Determines whether the bottom survey banner should be shown to the
// current student, and records dismissals. The actual survey is answered
// on the public survey page (main_site), opened in a new tab — this file
// only reads/writes the shared survey tables (surveys, survey_responses,
// survey_banner_dismissals) that also back the cc_dashboard survey builder.
// No separate table is created here beyond survey_banner_dismissals.

if (!function_exists('surveyBannerTablesReady')) {
  function surveyBannerTablesReady(mysqli $conn): bool
  {
    static $ready = null;

    if ($ready !== null) {
      return $ready;
    }

    $required = ['surveys', 'survey_responses', 'survey_banner_dismissals'];
    foreach ($required as $table) {
      $tableSafe = mysqli_real_escape_string($conn, $table);
      $result = mysqli_query($conn, "SHOW TABLES LIKE '$tableSafe'");
      if (!$result || mysqli_num_rows($result) === 0) {
        $ready = false;
        return $ready;
      }
    }

    $ready = true;
    return $ready;
  }
}

if (!function_exists('surveyBannerHasDismissed')) {
  function surveyBannerHasDismissed(mysqli $conn, int $surveyId, int $userId): bool
  {
    if ($surveyId <= 0 || $userId <= 0) {
      return false;
    }

    $stmt = mysqli_prepare($conn, "SELECT id FROM survey_banner_dismissals WHERE survey_id = ? AND user_id = ? LIMIT 1");
    if (!$stmt) {
      return false;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $surveyId, $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $dismissed = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $dismissed;
  }
}

if (!function_exists('surveyBannerHasResponded')) {
  function surveyBannerHasResponded(mysqli $conn, int $surveyId, string $email): bool
  {
    if ($surveyId <= 0 || $email === '') {
      return false;
    }

    $stmt = mysqli_prepare($conn, "SELECT id FROM survey_responses WHERE survey_id = ? AND email = ? LIMIT 1");
    if (!$stmt) {
      return false;
    }

    mysqli_stmt_bind_param($stmt, 'is', $surveyId, $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $responded = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $responded;
  }
}

if (!function_exists('surveyBannerGetActiveForUser')) {
  /**
   * Returns the currently active banner survey for this user, or null if
   * none is flagged, published, unexpired, or the user already dismissed
   * / responded to it.
   */
  function surveyBannerGetActiveForUser(mysqli $conn, int $userId, string $userEmail): ?array
  {
    if ($userId <= 0 || !surveyBannerTablesReady($conn)) {
      return null;
    }

    $result = mysqli_query(
      $conn,
      "SELECT id, slug, title, description
       FROM surveys
       WHERE show_as_banner = 1
         AND status = 'published'
         AND (expiry_date IS NULL OR expiry_date > NOW())
       ORDER BY updated_at DESC
       LIMIT 1"
    );

    if (!$result || mysqli_num_rows($result) === 0) {
      return null;
    }

    $survey = mysqli_fetch_assoc($result);
    $surveyId = (int) $survey['id'];

    if (surveyBannerHasDismissed($conn, $surveyId, $userId)) {
      return null;
    }

    if (surveyBannerHasResponded($conn, $surveyId, strtolower(trim($userEmail)))) {
      return null;
    }

    return [
      'id' => $surveyId,
      'slug' => (string) $survey['slug'],
      'title' => (string) $survey['title'],
      'description' => (string) ($survey['description'] ?? ''),
    ];
  }
}

if (!function_exists('surveyBannerDismiss')) {
  function surveyBannerDismiss(mysqli $conn, int $surveyId, int $userId): bool
  {
    if ($surveyId <= 0 || $userId <= 0 || !surveyBannerTablesReady($conn)) {
      return false;
    }

    $stmt = mysqli_prepare(
      $conn,
      "INSERT INTO survey_banner_dismissals (survey_id, user_id, dismissed_at)
       VALUES (?, ?, NOW())
       ON DUPLICATE KEY UPDATE dismissed_at = dismissed_at"
    );
    if (!$stmt) {
      return false;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $surveyId, $userId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return (bool) $ok;
  }
}

