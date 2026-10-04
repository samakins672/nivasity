<?php
// Academic period tagging for course materials: every material belongs to one session (e.g.
// 2026/2027) and one semester (1 or 2). Students only see open materials whose session AND
// semester match their school's current period (schools.current_session / current_semester);
// materials missing either are hidden. The command center switches periods (Academic calendar).
// Session support needs sql/add_academic_session.sql; until then only the semester is checked.

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

if (!function_exists('material_session_ready')) {
  function material_session_ready(mysqli $conn): bool
  {
    return material_semester_ready($conn)
      && material_semester_has_column($conn, 'manuals', 'session')
      && material_semester_has_column($conn, 'schools', 'current_session');
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

if (!function_exists('material_session_normalize')) {
  // 'YYYY/YYYY' with consecutive years, or null.
  function material_session_normalize($value): ?string
  {
    $value = trim((string) $value);
    if (!preg_match('#^(\d{4})/(\d{4})$#', $value, $m) || (int) $m[2] !== (int) $m[1] + 1) {
      return null;
    }
    return $value;
  }
}

if (!function_exists('material_period_current_for_school')) {
  // ['session' => '2026/2027'|null, 'semester' => 1|2, 'started_at' => datetime|null], or null when
  // tagging is not set up yet.
  function material_period_current_for_school(mysqli $conn, int $schoolId): ?array
  {
    static $cache = [];

    if ($schoolId <= 0 || !material_semester_ready($conn)) {
      return null;
    }
    if (array_key_exists($schoolId, $cache)) {
      return $cache[$schoolId];
    }

    $sessionSelect = material_session_ready($conn) ? 'current_session' : 'NULL AS current_session';
    $result = mysqli_query($conn, "SELECT current_semester, current_semester_updated_at, {$sessionSelect} FROM schools WHERE id = {$schoolId} LIMIT 1");
    $row = $result ? mysqli_fetch_assoc($result) : null;
    $cache[$schoolId] = [
      'semester' => $row ? (material_semester_normalize($row['current_semester'] ?? null) ?? 1) : 1,
      'session' => $row ? material_session_normalize($row['current_session'] ?? '') : null,
      'started_at' => $row['current_semester_updated_at'] ?? null,
    ];

    return $cache[$schoolId];
  }
}

if (!function_exists('material_semester_current_for_school')) {
  // The school's current semester (1 or 2), or null when tagging is not set up yet.
  function material_semester_current_for_school(mysqli $conn, int $schoolId): ?int
  {
    $period = material_period_current_for_school($conn, $schoolId);
    return $period ? $period['semester'] : null;
  }
}

if (!function_exists('material_semester_where_sql')) {
  // SQL condition limiting manuals to the school's current period. Always safe to AND into a WHERE.
  // Materials without a semester (or, once sessions exist, without a session) never match.
  function material_semester_where_sql(mysqli $conn, int $schoolId, string $alias = 'm'): string
  {
    $period = material_period_current_for_school($conn, $schoolId);
    if ($period === null) {
      return '1 = 1';
    }

    $qualified = $alias !== '' ? $alias . '.' : '';
    $where = "{$qualified}semester = {$period['semester']}";
    if (material_session_ready($conn)) {
      if ($period['session'] === null) {
        return '1 = 0'; // school has no current session yet: nothing is on sale
      }
      $session = mysqli_real_escape_string($conn, $period['session']);
      $where .= " AND {$qualified}session = '{$session}'";
    }
    return "({$where})";
  }
}

if (!function_exists('material_semester_is_visible')) {
  // Single-row check (cart add, checkout, details) for a manuals row. Untagged rows are not visible.
  function material_semester_is_visible(mysqli $conn, array $manual, int $schoolId = 0): bool
  {
    if ($schoolId <= 0) {
      $schoolId = (int) ($manual['school_id'] ?? 0);
    }

    $period = material_period_current_for_school($conn, $schoolId);
    if ($period === null) {
      return true;
    }

    // Callers may have selected only some columns: load the tags when missing.
    if ((!array_key_exists('semester', $manual) || (material_session_ready($conn) && !array_key_exists('session', $manual))) && !empty($manual['id'])) {
      $id = (int) $manual['id'];
      $cols = material_session_ready($conn) ? 'semester, session' : 'semester';
      $res = mysqli_query($conn, "SELECT {$cols} FROM manuals WHERE id = {$id} LIMIT 1");
      $manual = array_merge($manual, ($res ? mysqli_fetch_assoc($res) : null) ?: []);
    }

    if (material_semester_normalize($manual['semester'] ?? null) !== $period['semester']) {
      return false;
    }
    if (material_session_ready($conn)) {
      return $period['session'] !== null && material_session_normalize($manual['session'] ?? '') === $period['session'];
    }
    return true;
  }
}

if (!function_exists('material_semester_label')) {
  function material_semester_label($semester, $session = null): string
  {
    $semester = material_semester_normalize($semester);
    $label = $semester === 1 ? 'First Semester' : ($semester === 2 ? 'Second Semester' : '');
    $session = material_session_normalize($session ?? '');
    return ($label !== '' && $session !== null) ? "{$label} {$session}" : $label;
  }
}

if (!function_exists('material_period_purchases_since')) {
  // When the school's current period started: purchases before it belong to earlier periods and
  // never appear in class rep exports. Null when unknown.
  function material_period_purchases_since(mysqli $conn, int $schoolId): ?string
  {
    $period = material_period_current_for_school($conn, $schoolId);
    return $period && !empty($period['started_at']) ? (string) $period['started_at'] : null;
  }
}

if (!function_exists('material_session_started_at')) {
  // When the school's current session started (its first period in academic_periods). Class reps
  // only see this session's materials and purchases. Null when unknown.
  function material_session_started_at(mysqli $conn, int $schoolId): ?string
  {
    $period = material_period_current_for_school($conn, $schoolId);
    if (!$period || $period['session'] === null) {
      return null;
    }
    if (material_semester_has_column($conn, 'academic_periods', 'session')) {
      $session = mysqli_real_escape_string($conn, $period['session']);
      $res = mysqli_query($conn, "SELECT MIN(started_at) AS s FROM academic_periods WHERE school_id = {$schoolId} AND session = '{$session}'");
      $row = $res ? mysqli_fetch_assoc($res) : null;
      if (!empty($row['s'])) {
        return (string) $row['s'];
      }
    }
    return $period['started_at'] ?: null;
  }
}
