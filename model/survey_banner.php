<?php

// Determines whether the bottom-right survey banner should be shown to the
// current student, and handles dismiss/submit against the shared survey
// tables (surveys, survey_responses, survey_banner_dismissals) that also
// back the cc_dashboard survey builder. No separate table is created here.

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
      "SELECT id, slug, title, description, questions_json, allow_duplicate_email
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

    $questionsData = json_decode((string) ($survey['questions_json'] ?? '{}'), true);

    return [
      'id' => $surveyId,
      'slug' => (string) $survey['slug'],
      'title' => (string) ($questionsData['title'] ?? $survey['title']),
      'description' => (string) ($questionsData['description'] ?? ($survey['description'] ?? '')),
      'questions' => $questionsData['questions'] ?? null,
      'sections' => $questionsData['sections'] ?? null,
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

if (!function_exists('surveyBannerSubmitResponse')) {
  /**
   * Records a submission and treats it as an implicit dismissal so the
   * banner never reappears for this survey once answered.
   */
  function surveyBannerSubmitResponse(
    mysqli $conn,
    int $surveyId,
    int $userId,
    string $firstName,
    string $lastName,
    string $email,
    string $phone,
    string $responsesJson
  ): bool {
    if ($surveyId <= 0 || $userId <= 0 || !surveyBannerTablesReady($conn)) {
      return false;
    }

    $email = strtolower(trim($email));
    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return false;
    }

    $stmt = mysqli_prepare(
      $conn,
      "INSERT INTO survey_responses (survey_id, first_name, last_name, email, phone, responses_json, submitter_ip, user_agent, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    if (!$stmt) {
      return false;
    }

    $submitterIp = isset($_SERVER['REMOTE_ADDR']) ? substr((string) $_SERVER['REMOTE_ADDR'], 0, 45) : '';
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : '';

    mysqli_stmt_bind_param(
      $stmt,
      'isssssss',
      $surveyId,
      $firstName,
      $lastName,
      $email,
      $phone,
      $responsesJson,
      $submitterIp,
      $userAgent
    );
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
      surveyBannerDismiss($conn, $surveyId, $userId);
    }

    return (bool) $ok;
  }
}
