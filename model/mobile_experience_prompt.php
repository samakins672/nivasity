<?php

if (!function_exists('mobile_experience_prompt_get_current_campaign_key')) {
  function mobile_experience_prompt_get_current_campaign_key(): string
  {
    return 'app_store_launch_2026_05';
  }
}

if (!function_exists('mobile_experience_prompt_ensure_schema')) {
  function mobile_experience_prompt_ensure_schema(mysqli $conn): void
  {
    static $schemaReady = false;

    if ($schemaReady) {
      return;
    }

    $createTableSql = "CREATE TABLE IF NOT EXISTS `mobile_experience_feedback` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `device_choice` enum('android','iphone') NOT NULL,
      `comfort_level` enum('love_it','its_cool','its_okay','kinda_stressful','not_good_experience') NOT NULL,
      `comfort_label` varchar(64) NOT NULL,
      `source_page` varchar(64) NOT NULL DEFAULT 'store',
      `campaign_key` varchar(64) NOT NULL DEFAULT 'legacy',
      `user_agent` varchar(255) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `idx_mef_user_id` (`user_id`),
      KEY `idx_mef_campaign_key` (`campaign_key`),
      KEY `idx_mef_device_choice` (`device_choice`),
      KEY `idx_mef_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($conn, $createTableSql);

    $campaignColumnCheck = mysqli_query($conn, "SHOW COLUMNS FROM `mobile_experience_feedback` LIKE 'campaign_key'");
    if ($campaignColumnCheck && mysqli_num_rows($campaignColumnCheck) === 0) {
      mysqli_query($conn, "ALTER TABLE `mobile_experience_feedback` ADD COLUMN `campaign_key` VARCHAR(64) NOT NULL DEFAULT 'legacy' AFTER `source_page`");
      mysqli_query($conn, "ALTER TABLE `mobile_experience_feedback` ADD KEY `idx_mef_campaign_key` (`campaign_key`)");
    }

    $columnCheck = mysqli_query($conn, "SHOW COLUMNS FROM `users` LIKE 'mobile_experience_prompt_visits'");
    if ($columnCheck && mysqli_num_rows($columnCheck) === 0) {
      mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN `mobile_experience_prompt_visits` INT(11) NOT NULL DEFAULT 0 AFTER `last_login`");
    }

    $schemaReady = true;
  }
}

if (!function_exists('mobile_experience_prompt_has_feedback')) {
  function mobile_experience_prompt_has_feedback(mysqli $conn, int $userId, ?string $campaignKey = null): bool
  {
    if ($userId <= 0) {
      return false;
    }

    mobile_experience_prompt_ensure_schema($conn);
    $campaignKey = substr((string) ($campaignKey ?: mobile_experience_prompt_get_current_campaign_key()), 0, 64);

    $stmt = mysqli_prepare($conn, "SELECT id FROM mobile_experience_feedback WHERE user_id = ? AND campaign_key = ? LIMIT 1");
    if (!$stmt) {
      return false;
    }

    mysqli_stmt_bind_param($stmt, 'is', $userId, $campaignKey);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $hasFeedback = $result && mysqli_num_rows($result) > 0;
    mysqli_stmt_close($stmt);

    return $hasFeedback;
  }
}

if (!function_exists('mobile_experience_prompt_get_visit_count')) {
  function mobile_experience_prompt_get_visit_count(mysqli $conn, int $userId): int
  {
    if ($userId <= 0) {
      return 0;
    }

    mobile_experience_prompt_ensure_schema($conn);

    $stmt = mysqli_prepare($conn, "SELECT mobile_experience_prompt_visits FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
      return 0;
    }

    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $count = 0;

    if ($result) {
      $row = mysqli_fetch_assoc($result);
      if ($row && isset($row['mobile_experience_prompt_visits'])) {
        $count = (int) $row['mobile_experience_prompt_visits'];
      }
    }

    mysqli_stmt_close($stmt);
    return max(0, $count);
  }
}

if (!function_exists('mobile_experience_prompt_get_state')) {
  function mobile_experience_prompt_get_state(mysqli $conn, int $userId, int $minVisits = 6, bool $registerVisit = false): array
  {
    $state = [
      'captured' => false,
      'visit_count' => 0,
      'min_visits' => max(1, $minVisits),
      'should_show' => false,
    ];

    if ($userId <= 0) {
      return $state;
    }

    mobile_experience_prompt_ensure_schema($conn);

    $state['captured'] = mobile_experience_prompt_has_feedback(
      $conn,
      $userId,
      mobile_experience_prompt_get_current_campaign_key()
    );

    if ($registerVisit && !$state['captured']) {
      $stmt = mysqli_prepare($conn, "UPDATE users SET mobile_experience_prompt_visits = COALESCE(mobile_experience_prompt_visits, 0) + 1 WHERE id = ?");
      if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
      }
    }

    $state['visit_count'] = mobile_experience_prompt_get_visit_count($conn, $userId);
    $state['should_show'] = !$state['captured'] && $state['visit_count'] >= $state['min_visits'];

    return $state;
  }
}
