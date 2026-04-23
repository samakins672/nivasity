<?php

if (!function_exists('material_copy_has_column')) {
  function material_copy_has_column(mysqli $conn, string $table, string $column): bool
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

if (!function_exists('material_copy_status_select_sql')) {
  function material_copy_status_select_sql(mysqli $conn, string $alias = 'mb'): string
  {
    $qualified = $alias !== '' ? $alias . '.' : '';
    return material_copy_has_column($conn, 'manuals_bought', 'copy_status')
      ? $qualified . 'copy_status'
      : "'active' AS copy_status";
  }
}

if (!function_exists('material_copy_lost_at_select_sql')) {
  function material_copy_lost_at_select_sql(mysqli $conn, string $alias = 'mb'): string
  {
    $qualified = $alias !== '' ? $alias . '.' : '';
    return material_copy_has_column($conn, 'manuals_bought', 'lost_at')
      ? $qualified . 'lost_at'
      : 'NULL AS lost_at';
  }
}

if (!function_exists('material_copy_is_lost_value')) {
  function material_copy_is_lost_value($value): bool
  {
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['lost', 'missing'], true);
  }
}

if (!function_exists('material_copy_is_lost_row')) {
  function material_copy_is_lost_row(array $row): bool
  {
    return material_copy_is_lost_value($row['copy_status'] ?? 'active');
  }
}

if (!function_exists('material_copy_non_lost_condition')) {
  function material_copy_non_lost_condition(mysqli $conn, string $alias = 'mb'): string
  {
    if (!material_copy_has_column($conn, 'manuals_bought', 'copy_status')) {
      return '1 = 1';
    }

    $qualified = $alias !== '' ? $alias . '.' : '';
    return "({$qualified}copy_status IS NULL OR LOWER(TRIM(CAST({$qualified}copy_status AS CHAR))) NOT IN ('lost', 'missing'))";
  }
}

if (!function_exists('material_copy_mark_lost')) {
  function material_copy_mark_lost(mysqli $conn, int $buyerId, int $schoolId, int $boughtId): array
  {
    if (!material_copy_has_column($conn, 'manuals_bought', 'id') || !material_copy_has_column($conn, 'manuals_bought', 'copy_status')) {
      return [
        'ok' => false,
        'status_code' => 500,
        'message' => 'Lost-material tracking is not ready yet. Run the latest SQL migration first.',
      ];
    }

    $buyerId = (int) $buyerId;
    $schoolId = (int) $schoolId;
    $boughtId = (int) $boughtId;

    if ($boughtId <= 0) {
      return [
        'ok' => false,
        'status_code' => 400,
        'message' => 'A valid purchased material is required.',
      ];
    }

    $selectCopyStatus = material_copy_status_select_sql($conn, 'mb');
    $selectLostAt = material_copy_lost_at_select_sql($conn, 'mb');
    $query = mysqli_query(
      $conn,
      "SELECT
        mb.id,
        mb.manual_id,
        mb.ref_id,
        mb.status,
        mb.created_at,
        {$selectCopyStatus},
        {$selectLostAt},
        m.title,
        m.course_code
      FROM manuals_bought AS mb
      INNER JOIN manuals AS m ON m.id = mb.manual_id
      WHERE mb.id = {$boughtId}
        AND mb.buyer = {$buyerId}
        AND mb.school_id = {$schoolId}
      LIMIT 1"
    );

    if (!$query) {
      return [
        'ok' => false,
        'status_code' => 500,
        'message' => 'Unable to validate the selected material purchase right now.',
      ];
    }

    if (mysqli_num_rows($query) < 1) {
      return [
        'ok' => false,
        'status_code' => 404,
        'message' => 'The selected purchased material could not be found for this account.',
      ];
    }

    $order = mysqli_fetch_assoc($query) ?: [];
    $status = strtolower(trim((string) ($order['status'] ?? '')));
    if ($status !== 'successful') {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'Only successful material purchases can be marked as lost.',
      ];
    }

    if (material_copy_is_lost_row($order)) {
      return [
        'ok' => true,
        'status_code' => 200,
        'message' => 'This material purchase was already marked as lost.',
        'data' => [
          'bought_id' => (int) ($order['id'] ?? 0),
          'manual_id' => (int) ($order['manual_id'] ?? 0),
          'ref_id' => (string) ($order['ref_id'] ?? ''),
          'copy_status' => 'lost',
        ],
      ];
    }

    $setParts = ["copy_status = 'lost'"];
    if (material_copy_has_column($conn, 'manuals_bought', 'lost_at')) {
      $setParts[] = 'lost_at = NOW()';
    }
    if (material_copy_has_column($conn, 'manuals_bought', 'updated_at')) {
      $setParts[] = 'updated_at = NOW()';
    }
    $setSql = implode(', ', $setParts);

    if (!mysqli_query($conn, "UPDATE manuals_bought SET {$setSql} WHERE id = {$boughtId} LIMIT 1")) {
      return [
        'ok' => false,
        'status_code' => 500,
        'message' => 'Unable to mark this material as lost right now.',
      ];
    }

    return [
      'ok' => true,
      'status_code' => 200,
      'message' => 'Material marked as lost. You can now buy another copy of it.',
      'data' => [
        'bought_id' => (int) ($order['id'] ?? 0),
        'manual_id' => (int) ($order['manual_id'] ?? 0),
        'ref_id' => (string) ($order['ref_id'] ?? ''),
        'title' => (string) ($order['title'] ?? ''),
        'course_code' => (string) ($order['course_code'] ?? ''),
        'copy_status' => 'lost',
      ],
    ];
  }
}
?>