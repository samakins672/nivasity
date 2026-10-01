<?php
// Semester tagging for course materials (docs/WHITE_LABEL_TRANSITION_PLAN.md, section 4).
// Students only see open materials tagged for their school's current semester.
// Legacy untagged (NULL) materials stay visible until the school's first semester switch,
// when cc_dashboard moves them to 'awaiting_confirmation'.
// Every helper is a no-op until sql/add_semester_tagging.sql has been applied.

if (!function_exists('material_semester_status_awaiting_confirmation')) {
  function material_semester_status_awaiting_confirmation(): string
  {
    return 'awaiting_confirmation';
  }
}

if (!function_exists('material_semester_has_column')) {
  function material_semester_has_column(mysqli $conn, string $table, string $column): bool
  {
    static $cache = [];

    $key = strtolower($table . '.' . $column);
    if (array_key_exists($key, $cache)) {
      return $cache[$key];
    }

    $safeTable = mysqli_real_escape_string($conn, $table);
    $safeColumn = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $cache[$key] = $result && mysqli_num_rows($result) > 0;

    return $cache[$key];
  }
}

if (!function_exists('material_semester_ready')) {
  function material_semester_ready(mysqli $conn): bool
  {
    return material_semester_has_column($conn, 'manuals', 'semester')
      && material_semester_has_column($conn, 'schools', 'current_semester');
  }
}

if (!function_exists('material_semester_normalize')) {
  // Returns 1 or 2, or null for anything else (untagged / invalid).
  function material_semester_normalize($value): ?int
  {
    $semester = (int) $value;
    return ($semester === 1 || $semester === 2) ? $semester : null;
  }
}

if (!function_exists('material_semester_current_for_school')) {
  // The school's current semester (1 or 2), or null when tagging is not set up yet.
  function material_semester_current_for_school(mysqli $conn, int $schoolId): ?int
  {
    static $cache = [];

    if ($schoolId <= 0 || !material_semester_ready($conn)) {
      return null;
    }
    if (array_key_exists($schoolId, $cache)) {
      return $cache[$schoolId];
    }

    $result = mysqli_query($conn, "SELECT current_semester FROM schools WHERE id = {$schoolId} LIMIT 1");
    $row = $result ? mysqli_fetch_assoc($result) : null;
    $cache[$schoolId] = $row ? (material_semester_normalize($row['current_semester'] ?? null) ?? 1) : 1;

    return $cache[$schoolId];
  }
}

if (!function_exists('material_semester_where_sql')) {
  // SQL condition limiting manuals to the school's current semester. Always safe to AND into a WHERE.
  function material_semester_where_sql(mysqli $conn, int $schoolId, string $alias = 'm'): string
  {
    $currentSemester = material_semester_current_for_school($conn, $schoolId);
    if ($currentSemester === null) {
      return '1 = 1';
    }

    $qualified = $alias !== '' ? $alias . '.' : '';
    return "({$qualified}semester = {$currentSemester} OR {$qualified}semester IS NULL)";
  }
}

if (!function_exists('material_semester_is_visible')) {
  // Single-row check (cart add, checkout, details) for a manuals row that includes semester and school_id.
  function material_semester_is_visible(mysqli $conn, array $manual, int $schoolId = 0): bool
  {
    if ($schoolId <= 0) {
      $schoolId = (int) ($manual['school_id'] ?? 0);
    }

    $currentSemester = material_semester_current_for_school($conn, $schoolId);
    if ($currentSemester === null || !array_key_exists('semester', $manual)) {
      return true;
    }

    $semester = material_semester_normalize($manual['semester']);
    return $semester === null || $semester === $currentSemester;
  }
}

if (!function_exists('material_semester_label')) {
  function material_semester_label($semester): string
  {
    $semester = material_semester_normalize($semester);
    if ($semester === 1) {
      return 'First Semester';
    }
    if ($semester === 2) {
      return 'Second Semester';
    }
    return '';
  }
}
