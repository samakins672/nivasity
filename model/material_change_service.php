<?php

if (!function_exists('material_change_has_column')) {
  function material_change_has_column(mysqli $conn, string $table, string $column): bool
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

if (!function_exists('material_change_has_table')) {
  function material_change_has_table(mysqli $conn, string $table): bool
  {
    static $cache = [];

    $key = strtolower($table);
    if (array_key_exists($key, $cache)) {
      return $cache[$key];
    }

    $safeTable = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '{$safeTable}'");
    $cache[$key] = $result && mysqli_num_rows($result) > 0;

    return $cache[$key];
  }
}

if (!function_exists('material_change_ensure_schema')) {
  function material_change_ensure_schema(mysqli $conn): bool
  {
    static $schemaReady = false;

    if ($schemaReady) {
      return true;
    }

    $createTableSql = "CREATE TABLE IF NOT EXISTS `manual_change_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `buyer_id` int(11) NOT NULL,
      `school_id` int(11) NOT NULL DEFAULT 1,
      `manuals_bought_id` int(11) DEFAULT NULL,
      `ref_id` varchar(50) NOT NULL,
      `old_manual_id` int(11) NOT NULL,
      `new_manual_id` int(11) NOT NULL,
      `old_seller_id` int(11) DEFAULT NULL,
      `new_seller_id` int(11) DEFAULT NULL,
      `old_manual_price` int(11) NOT NULL DEFAULT 0,
      `new_manual_price` int(11) NOT NULL DEFAULT 0,
      `source` varchar(20) NOT NULL DEFAULT 'web',
      `request_context` varchar(32) DEFAULT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `user_agent` varchar(255) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_manual_change_order` (`buyer_id`, `ref_id`, `old_manual_id`),
      UNIQUE KEY `uniq_manual_change_bought` (`manuals_bought_id`),
      KEY `idx_manual_change_new_manual` (`new_manual_id`),
      KEY `idx_manual_change_buyer_created` (`buyer_id`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    mysqli_query($conn, $createTableSql);

    $schemaReady = material_change_has_table($conn, 'manual_change_logs');

    return $schemaReady;
  }
}

if (!function_exists('material_change_boolish_is_true')) {
  function material_change_boolish_is_true($value): bool
  {
    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ['1', 'true', 'yes', 'granted', 'completed'], true);
  }
}

if (!function_exists('material_change_is_within_window')) {
  function material_change_is_within_window(string $createdAt, int $hours = 72): bool
  {
    $timestamp = strtotime($createdAt);
    if ($timestamp === false) {
      return false;
    }

    $hours = max(1, $hours);
    return $timestamp >= strtotime('-' . $hours . ' hours');
  }
}

if (!function_exists('material_change_get_user_faculty_id')) {
  function material_change_get_user_faculty_id(mysqli $conn, int $deptId, int $schoolId): int
  {
    static $cache = [];

    $cacheKey = $schoolId . ':' . $deptId;
    if (array_key_exists($cacheKey, $cache)) {
      return $cache[$cacheKey];
    }

    if ($deptId <= 0 || !material_change_has_column($conn, 'depts', 'faculty_id')) {
      $cache[$cacheKey] = 0;
      return 0;
    }

    $query = mysqli_query($conn, "SELECT faculty_id FROM depts WHERE id = {$deptId} AND school_id = {$schoolId} LIMIT 1");
    if ($query && mysqli_num_rows($query) > 0) {
      $row = mysqli_fetch_assoc($query);
      $cache[$cacheKey] = (int) ($row['faculty_id'] ?? 0);
      return $cache[$cacheKey];
    }

    $cache[$cacheKey] = 0;
    return 0;
  }
}

if (!function_exists('material_change_build_visibility_where')) {
  function material_change_build_visibility_where(mysqli $conn, string $manualAlias, int $userDeptId, int $schoolId): string
  {
    if ($userDeptId <= 0) {
      return '1 = 0';
    }

    $manualsHasFaculty = material_change_has_column($conn, 'manuals', 'faculty');
    $manualsHasDepts = material_change_has_column($conn, 'manuals', 'depts');

    $legacyVisibilityWhere = "{$manualAlias}.dept = {$userDeptId}";
    if ($manualsHasFaculty) {
      $userFacultyId = material_change_get_user_faculty_id($conn, $userDeptId, $schoolId);
      if ($userFacultyId > 0) {
        $legacyVisibilityWhere .= " OR ({$manualAlias}.dept = 0 AND {$manualAlias}.faculty = {$userFacultyId})";
      }
    }

    if ($manualsHasDepts) {
      $normalizedDeptsExpr = "REPLACE(REPLACE(REPLACE(REPLACE({$manualAlias}.depts, '[', ''), ']', ''), '\"', ''), ' ', '')";
      return "(({$manualAlias}.depts IS NOT NULL AND FIND_IN_SET({$userDeptId}, {$normalizedDeptsExpr}) > 0) OR ({$manualAlias}.depts IS NULL AND ({$legacyVisibilityWhere})))";
    }

    return "({$legacyVisibilityWhere})";
  }
}

if (!function_exists('material_change_get_existing_log')) {
  function material_change_get_existing_log(mysqli $conn, int $buyerId, string $refId, int $oldManualId, int $manualsBoughtId = 0): ?array
  {
    if (!material_change_ensure_schema($conn)) {
      return null;
    }

    $buyerId = (int) $buyerId;
    $oldManualId = (int) $oldManualId;
    $manualsBoughtId = (int) $manualsBoughtId;
    $refIdSafe = mysqli_real_escape_string($conn, $refId);

    if ($manualsBoughtId > 0) {
      $query = mysqli_query(
        $conn,
        "SELECT * FROM manual_change_logs WHERE manuals_bought_id = {$manualsBoughtId} LIMIT 1"
      );

      if ($query && mysqli_num_rows($query) > 0) {
        return mysqli_fetch_assoc($query) ?: null;
      }
    }

    $query = mysqli_query(
      $conn,
      "SELECT * FROM manual_change_logs WHERE buyer_id = {$buyerId} AND ref_id = '{$refIdSafe}' AND old_manual_id = {$oldManualId} LIMIT 1"
    );

    if ($query && mysqli_num_rows($query) > 0) {
      return mysqli_fetch_assoc($query) ?: null;
    }

    return null;
  }
}

if (!function_exists('material_change_is_order_granted')) {
  function material_change_is_order_granted(array $orderRow): bool
  {
    $grantStatus = $orderRow['grant_status'] ?? null;
    $exportId = isset($orderRow['export_id']) ? (int) $orderRow['export_id'] : 0;

    return material_change_boolish_is_true($grantStatus) || $exportId > 0;
  }
}

if (!function_exists('material_change_get_order_context')) {
  function material_change_get_order_context(mysqli $conn, int $buyerId, int $schoolId, int $oldManualId, string $refId): array
  {
    if (!material_change_ensure_schema($conn)) {
      return [
        'ok' => false,
        'status_code' => 500,
        'message' => 'Material change is temporarily unavailable because the audit log table is not ready.',
      ];
    }

    $hasBoughtId = material_change_has_column($conn, 'manuals_bought', 'id');
    $hasGrantStatus = material_change_has_column($conn, 'manuals_bought', 'grant_status');
    $hasExportId = material_change_has_column($conn, 'manuals_bought', 'export_id');
    $hasDepts = material_change_has_column($conn, 'manuals', 'depts');
    $hasCoverage = material_change_has_column($conn, 'manuals', 'coverage');

    $buyerId = (int) $buyerId;
    $schoolId = (int) $schoolId;
    $oldManualId = (int) $oldManualId;
    $refIdSafe = mysqli_real_escape_string($conn, $refId);

    $selectBoughtId = $hasBoughtId ? 'mb.id AS bought_id' : '0 AS bought_id';
    $selectGrantStatus = $hasGrantStatus ? 'mb.grant_status' : "'0' AS grant_status";
    $selectExportId = $hasExportId ? 'mb.export_id' : 'NULL AS export_id';
    $selectDepts = $hasDepts ? 'm.depts' : 'NULL AS depts';
    $selectCoverage = $hasCoverage ? 'm.coverage' : 'NULL AS coverage';

    $query = mysqli_query(
      $conn,
      "SELECT
        {$selectBoughtId},
        mb.manual_id,
        mb.price,
        mb.seller,
        mb.buyer,
        mb.school_id,
        mb.ref_id,
        mb.status,
        mb.created_at,
        {$selectGrantStatus},
        {$selectExportId},
        m.title,
        m.course_code,
        m.code,
        m.user_id AS current_manual_owner_id,
        m.status AS manual_status,
        m.price AS manual_price,
        m.due_date,
        m.dept,
        m.faculty,
        {$selectDepts},
        {$selectCoverage}
      FROM manuals_bought AS mb
      INNER JOIN manuals AS m ON m.id = mb.manual_id
      WHERE mb.buyer = {$buyerId}
        AND mb.school_id = {$schoolId}
        AND mb.manual_id = {$oldManualId}
        AND mb.ref_id = '{$refIdSafe}'
      ORDER BY mb.created_at DESC"
    );

    if (!$query) {
      return [
        'ok' => false,
        'status_code' => 500,
        'message' => 'Unable to validate the selected order right now.',
      ];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($query)) {
      $rows[] = $row;
    }

    $rowCount = count($rows);
    if ($rowCount === 0) {
      return [
        'ok' => false,
        'status_code' => 404,
        'message' => 'The selected material purchase could not be found for this account.',
      ];
    }

    if ($rowCount > 1) {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'Duplicate purchased rows were found for this material and transaction. Change aborted.',
      ];
    }

    $order = $rows[0];
    $order['bought_id'] = (int) ($order['bought_id'] ?? 0);
    $order['manual_id'] = (int) ($order['manual_id'] ?? 0);
    $order['buyer'] = (int) ($order['buyer'] ?? 0);
    $order['seller'] = (int) ($order['seller'] ?? 0);
    $order['school_id'] = (int) ($order['school_id'] ?? 0);
    $order['price'] = (int) ($order['price'] ?? 0);
    $order['manual_price'] = (int) ($order['manual_price'] ?? 0);
    $order['dept'] = (int) ($order['dept'] ?? 0);
    $order['faculty'] = isset($order['faculty']) ? (int) $order['faculty'] : 0;

    $status = strtolower(trim((string) ($order['status'] ?? '')));
    if ($status !== 'successful') {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'Only successful material purchases can be changed.',
      ];
    }

    if (!material_change_is_within_window((string) ($order['created_at'] ?? ''), 72)) {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'Materials can only be changed within 72 hours of purchase.',
      ];
    }

    if (material_change_is_order_granted($order)) {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'This material has already been granted and can no longer be changed.',
      ];
    }

    $existingLog = material_change_get_existing_log($conn, $buyerId, $refId, $oldManualId, (int) ($order['bought_id'] ?? 0));
    if ($existingLog !== null) {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'This material has already been changed once for the selected transaction.',
        'existing_change' => $existingLog,
      ];
    }

    return [
      'ok' => true,
      'status_code' => 200,
      'message' => 'Order is eligible for material change.',
      'order' => $order,
    ];
  }
}

if (!function_exists('material_change_get_candidate_materials')) {
  function material_change_get_candidate_materials(mysqli $conn, int $buyerId, int $schoolId, int $userDeptId, int $oldManualId, string $refId): array
  {
    $context = material_change_get_order_context($conn, $buyerId, $schoolId, $oldManualId, $refId);
    if (!$context['ok']) {
      return $context;
    }

    $order = $context['order'];
    $price = (int) ($order['price'] ?? 0);
    $visibilityWhere = material_change_build_visibility_where($conn, 'm', $userDeptId, $schoolId);
    $hasDepts = material_change_has_column($conn, 'manuals', 'depts');
    $selectDepts = $hasDepts ? 'm.depts' : 'NULL AS depts';

    $query = mysqli_query(
      $conn,
      "SELECT
        m.id,
        m.title,
        m.course_code,
        m.code,
        m.price,
        m.due_date,
        m.dept,
        m.status,
        {$selectDepts},
        d.name AS dept_name
      FROM manuals AS m
      LEFT JOIN depts AS d ON d.id = m.dept
      WHERE m.school_id = {$schoolId}
        AND m.id <> {$oldManualId}
        AND m.status = 'open'
        AND m.price = {$price}
        AND m.due_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND ({$visibilityWhere})
        AND NOT EXISTS (
          SELECT 1
          FROM manuals_bought AS mb2
          WHERE mb2.buyer = {$buyerId}
            AND mb2.manual_id = m.id
        )
      ORDER BY m.due_date ASC, m.title ASC"
    );

    $candidates = [];
    if ($query) {
      while ($row = mysqli_fetch_assoc($query)) {
        $candidates[] = [
          'id' => (int) $row['id'],
          'title' => (string) $row['title'],
          'course_code' => (string) ($row['course_code'] ?? ''),
          'code' => (string) ($row['code'] ?? ''),
          'price' => (float) ($row['price'] ?? 0),
          'due_date' => (string) ($row['due_date'] ?? ''),
          'dept_name' => ((int) ($row['dept'] ?? 0) === 0) ? 'All Departments' : (string) ($row['dept_name'] ?? 'Department'),
        ];
      }
    }

    return [
      'ok' => true,
      'status_code' => 200,
      'message' => empty($candidates)
        ? 'No eligible replacement materials are currently available for this purchase.'
        : 'Eligible replacement materials retrieved successfully.',
      'order' => [
        'ref_id' => (string) $order['ref_id'],
        'old_manual_id' => (int) $order['manual_id'],
        'title' => (string) $order['title'],
        'course_code' => (string) ($order['course_code'] ?? ''),
        'code' => (string) ($order['code'] ?? ''),
        'price' => (float) ($order['price'] ?? 0),
        'created_at' => (string) ($order['created_at'] ?? ''),
      ],
      'candidates' => $candidates,
    ];
  }
}

if (!function_exists('material_change_validate_target_manual')) {
  function material_change_validate_target_manual(mysqli $conn, int $buyerId, int $schoolId, int $userDeptId, int $oldManualId, int $oldPrice, int $newManualId): array
  {
    if ($newManualId <= 0) {
      return [
        'ok' => false,
        'status_code' => 400,
        'message' => 'A valid replacement material is required.',
      ];
    }

    if ($newManualId === $oldManualId) {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'Please choose a different material to continue.',
      ];
    }

    $newManualId = (int) $newManualId;
    $schoolId = (int) $schoolId;
    $buyerId = (int) $buyerId;
    $oldPrice = (int) $oldPrice;

    $manualQuery = mysqli_query($conn, "SELECT * FROM manuals WHERE id = {$newManualId} AND school_id = {$schoolId} LIMIT 1");
    if (!$manualQuery || mysqli_num_rows($manualQuery) === 0) {
      return [
        'ok' => false,
        'status_code' => 404,
        'message' => 'The selected replacement material could not be found.',
      ];
    }

    $manual = mysqli_fetch_assoc($manualQuery);
    if (!$manual) {
      return [
        'ok' => false,
        'status_code' => 404,
        'message' => 'The selected replacement material could not be found.',
      ];
    }

    if (strtolower(trim((string) ($manual['status'] ?? ''))) !== 'open') {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'The selected replacement material is no longer open.',
      ];
    }

    if ((int) ($manual['price'] ?? 0) !== $oldPrice) {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'The selected replacement material must have the same price as the original purchase.',
      ];
    }

    $dueDate = (string) ($manual['due_date'] ?? '');
    if ($dueDate === '' || strtotime($dueDate) === false || strtotime($dueDate) < strtotime('-24 hours')) {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'The selected replacement material is no longer available.',
      ];
    }

    $visibilityWhere = material_change_build_visibility_where($conn, 'm', $userDeptId, $schoolId);
    $visibilityCheck = mysqli_query(
      $conn,
      "SELECT m.id FROM manuals AS m WHERE m.id = {$newManualId} AND m.school_id = {$schoolId} AND ({$visibilityWhere}) LIMIT 1"
    );
    if (!$visibilityCheck || mysqli_num_rows($visibilityCheck) === 0) {
      return [
        'ok' => false,
        'status_code' => 403,
        'message' => 'You are not eligible to switch to the selected material.',
      ];
    }

    $alreadyBought = mysqli_query(
      $conn,
      "SELECT 1 FROM manuals_bought WHERE buyer = {$buyerId} AND manual_id = {$newManualId} LIMIT 1"
    );
    if ($alreadyBought && mysqli_num_rows($alreadyBought) > 0) {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'You already have this material in your purchase history.',
      ];
    }

    return [
      'ok' => true,
      'status_code' => 200,
      'message' => 'Replacement material validated successfully.',
      'manual' => $manual,
    ];
  }
}

if (!function_exists('material_change_save_log')) {
  function material_change_save_log(mysqli $conn, array $logData): bool
  {
    if (!material_change_ensure_schema($conn)) {
      return false;
    }

    $buyerId = (int) ($logData['buyer_id'] ?? 0);
    $schoolId = (int) ($logData['school_id'] ?? 0);
    $manualsBoughtId = (int) ($logData['manuals_bought_id'] ?? 0);
    $oldManualId = (int) ($logData['old_manual_id'] ?? 0);
    $newManualId = (int) ($logData['new_manual_id'] ?? 0);
    $oldSellerId = (int) ($logData['old_seller_id'] ?? 0);
    $newSellerId = (int) ($logData['new_seller_id'] ?? 0);
    $oldManualPrice = (int) ($logData['old_manual_price'] ?? 0);
    $newManualPrice = (int) ($logData['new_manual_price'] ?? 0);
    $refIdSafe = mysqli_real_escape_string($conn, (string) ($logData['ref_id'] ?? ''));
    $sourceSafe = mysqli_real_escape_string($conn, (string) ($logData['source'] ?? 'web'));
    $requestContextSafe = mysqli_real_escape_string($conn, (string) ($logData['request_context'] ?? 'change-material'));
    $ipAddressSafe = mysqli_real_escape_string($conn, (string) ($logData['ip_address'] ?? ''));
    $userAgentSafe = mysqli_real_escape_string($conn, substr((string) ($logData['user_agent'] ?? ''), 0, 255));
    $manualsBoughtValue = $manualsBoughtId > 0 ? (string) $manualsBoughtId : 'NULL';
    $oldSellerValue = $oldSellerId > 0 ? (string) $oldSellerId : 'NULL';
    $newSellerValue = $newSellerId > 0 ? (string) $newSellerId : 'NULL';
    $ipValue = $ipAddressSafe !== '' ? "'{$ipAddressSafe}'" : 'NULL';
    $userAgentValue = $userAgentSafe !== '' ? "'{$userAgentSafe}'" : 'NULL';

    return (bool) mysqli_query(
      $conn,
      "INSERT INTO manual_change_logs (
        buyer_id, school_id, manuals_bought_id, ref_id, old_manual_id, new_manual_id,
        old_seller_id, new_seller_id, old_manual_price, new_manual_price,
        source, request_context, ip_address, user_agent
      ) VALUES (
        {$buyerId}, {$schoolId}, {$manualsBoughtValue}, '{$refIdSafe}', {$oldManualId}, {$newManualId},
        {$oldSellerValue}, {$newSellerValue}, {$oldManualPrice}, {$newManualPrice},
        '{$sourceSafe}', '{$requestContextSafe}', {$ipValue}, {$userAgentValue}
      )"
    );
  }
}

if (!function_exists('material_change_execute')) {
  function material_change_execute(mysqli $conn, int $buyerId, int $schoolId, int $userDeptId, int $oldManualId, int $newManualId, string $refId, string $source = 'web'): array
  {
    $context = material_change_get_order_context($conn, $buyerId, $schoolId, $oldManualId, $refId);
    if (!$context['ok']) {
      return $context;
    }

    $order = $context['order'];
    $target = material_change_validate_target_manual(
      $conn,
      $buyerId,
      $schoolId,
      $userDeptId,
      (int) $order['manual_id'],
      (int) $order['price'],
      $newManualId
    );
    if (!$target['ok']) {
      return $target;
    }

    $newManual = $target['manual'];
    $newManualId = (int) ($newManual['id'] ?? 0);
    $newSellerId = (int) ($newManual['user_id'] ?? 0);
    $newPrice = (int) ($newManual['price'] ?? 0);
    $buyerId = (int) $buyerId;
    $refIdSafe = mysqli_real_escape_string($conn, $refId);
    $source = strtolower(trim($source)) === 'api' ? 'api' : 'web';

    if ($order['bought_id'] > 0) {
      $updateSql = "UPDATE manuals_bought SET manual_id = {$newManualId}, seller = {$newSellerId}, price = {$newPrice} WHERE id = {$order['bought_id']} LIMIT 1";
    } else {
      $updateSql = "UPDATE manuals_bought SET manual_id = {$newManualId}, seller = {$newSellerId}, price = {$newPrice} WHERE buyer = {$buyerId} AND school_id = {$schoolId} AND manual_id = {$oldManualId} AND ref_id = '{$refIdSafe}' LIMIT 1";
    }

    if (!mysqli_query($conn, $updateSql)) {
      return [
        'ok' => false,
        'status_code' => 500,
        'message' => 'Unable to update this purchase right now.',
      ];
    }

    if (mysqli_affected_rows($conn) < 1) {
      return [
        'ok' => false,
        'status_code' => 409,
        'message' => 'This purchase could not be updated. Please refresh and try again.',
      ];
    }

    $logSaved = material_change_save_log($conn, [
      'buyer_id' => $buyerId,
      'school_id' => $schoolId,
      'manuals_bought_id' => (int) ($order['bought_id'] ?? 0),
      'ref_id' => $refId,
      'old_manual_id' => (int) $oldManualId,
      'new_manual_id' => $newManualId,
      'old_seller_id' => (int) ($order['seller'] ?? 0),
      'new_seller_id' => $newSellerId,
      'old_manual_price' => (int) ($order['price'] ?? 0),
      'new_manual_price' => $newPrice,
      'source' => $source,
      'request_context' => 'change-material',
      'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
      'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
    ]);

    if (!$logSaved) {
      $existingLog = material_change_get_existing_log($conn, $buyerId, $refId, $oldManualId, (int) ($order['bought_id'] ?? 0));
      if ($existingLog === null) {
        return [
          'ok' => false,
          'status_code' => 500,
          'message' => 'The material was changed, but the audit log could not be saved.',
        ];
      }
    }

    $updatedQuery = mysqli_query(
      $conn,
      "SELECT mb.*, m.title, m.course_code, m.code
      FROM manuals_bought AS mb
      INNER JOIN manuals AS m ON m.id = mb.manual_id
      WHERE mb.buyer = {$buyerId}
        AND mb.school_id = {$schoolId}
        AND mb.manual_id = {$newManualId}
        AND mb.ref_id = '{$refIdSafe}'
      ORDER BY mb.created_at DESC
      LIMIT 1"
    );
    $updatedRow = $updatedQuery && mysqli_num_rows($updatedQuery) > 0 ? mysqli_fetch_assoc($updatedQuery) : null;

    return [
      'ok' => true,
      'status_code' => 200,
      'message' => 'Material changed successfully.',
      'data' => [
        'ref_id' => $refId,
        'old_manual_id' => (int) $oldManualId,
        'new_manual_id' => $newManualId,
        'record' => $updatedRow,
      ],
    ];
  }
}