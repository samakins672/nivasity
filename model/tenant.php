<?php

if (!function_exists('nivasity_db_has_column')) {
  function nivasity_db_has_column($conn, $table, $column) {
    static $cache = [];

    $table = (string) $table;
    $column = (string) $column;
    $cacheKey = $table . '.' . $column;

    if (array_key_exists($cacheKey, $cache)) {
      return $cache[$cacheKey];
    }

    $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^A-Za-z0-9_]/', '', $column);

    if ($safeTable === '' || $safeColumn === '') {
      $cache[$cacheKey] = false;
      return false;
    }

    $query = mysqli_query($conn, "SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    $cache[$cacheKey] = $query && mysqli_num_rows($query) > 0;

    return $cache[$cacheKey];
  }
}

if (!function_exists('nivasity_extract_school_code_from_host')) {
  function nivasity_extract_school_code_from_host($host) {
    $host = nivasity_normalize_host($host);

    if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
      return '';
    }

    $parts = explode('.', $host);
    if (count($parts) < 3) {
      return '';
    }

    $candidate = trim((string) $parts[0]);
    if ($candidate === '' || in_array($candidate, ['www', 'api', 'admin', 'stage', 'staging'], true)) {
      return '';
    }

    return strtolower($candidate);
  }
}

if (!function_exists('nivasity_resolve_tenant_from_request')) {
  function nivasity_resolve_tenant_from_request($conn) {
    $context = nivasity_get_tenant_context();
    $host = isset($context['host']) ? nivasity_normalize_host($context['host']) : nivasity_detect_request_host();

    if ($host === '' || !$conn) {
      return $context;
    }

    $scheme = nivasity_detect_request_scheme();
    $resolvedSchool = null;

    if (nivasity_db_has_column($conn, 'schools', 'domain')) {
      $stmt = mysqli_prepare($conn, "SELECT id, name, code, domain FROM schools WHERE status = 'active' AND LOWER(domain) = ? LIMIT 1");
      if ($stmt) {
        $lowerHost = strtolower($host);
        mysqli_stmt_bind_param($stmt, 's', $lowerHost);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
          $resolvedSchool = mysqli_fetch_assoc($result);
        }
        mysqli_stmt_close($stmt);
      }
    }

    if ($resolvedSchool === null) {
      $schoolCode = nivasity_extract_school_code_from_host($host);
      if ($schoolCode !== '') {
        $stmt = mysqli_prepare($conn, "SELECT id, name, code FROM schools WHERE status = 'active' AND LOWER(code) = ? LIMIT 1");
        if ($stmt) {
          mysqli_stmt_bind_param($stmt, 's', $schoolCode);
          mysqli_stmt_execute($stmt);
          $result = mysqli_stmt_get_result($stmt);
          if ($result && mysqli_num_rows($result) === 1) {
            $resolvedSchool = mysqli_fetch_assoc($result);
          }
          mysqli_stmt_close($stmt);
        }
      }
    }

    if ($resolvedSchool !== null) {
      $resolvedHost = !empty($resolvedSchool['domain'])
        ? nivasity_normalize_host($resolvedSchool['domain'])
        : $host;

      return nivasity_set_tenant_context([
        'school_id' => (int) $resolvedSchool['id'],
        'school_name' => isset($resolvedSchool['name']) ? $resolvedSchool['name'] : '',
        'school_code' => isset($resolvedSchool['code']) ? $resolvedSchool['code'] : '',
        'host' => $resolvedHost,
        'domain' => $scheme . '://' . $resolvedHost,
        'resolved' => true,
      ]);
    }

    return nivasity_set_tenant_context([
      'host' => $host,
      'domain' => $scheme . '://' . $host,
      'resolved' => false,
    ]);
  }
}

?>