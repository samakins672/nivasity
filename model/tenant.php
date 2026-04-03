<?php

if (!function_exists('nivasity_default_domain')) {
  function nivasity_default_domain() {
    return 'https://nivasity.com';
  }
}

if (!function_exists('nivasity_normalize_host')) {
  function nivasity_normalize_host($host) {
    $host = strtolower(trim((string) $host));

    if ($host === '') {
      return '';
    }

    if (strpos($host, '://') !== false) {
      $parsedHost = parse_url($host, PHP_URL_HOST);
      if (is_string($parsedHost) && $parsedHost !== '') {
        $host = $parsedHost;
      }
    }

    if (strpos($host, '/') !== false) {
      $host = explode('/', $host, 2)[0];
    }

    $host = preg_replace('/:\d+$/', '', $host);
    $host = preg_replace('/^www\./', '', $host);

    return $host;
  }
}

if (!function_exists('nivasity_detect_request_scheme')) {
  function nivasity_detect_request_scheme() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
      $forwardedProto = trim((string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
      if ($forwardedProto !== '') {
        return strtolower(explode(',', $forwardedProto)[0]) === 'https' ? 'https' : 'http';
      }
    }

    if (!empty($_SERVER['REQUEST_SCHEME'])) {
      return strtolower((string) $_SERVER['REQUEST_SCHEME']) === 'https' ? 'https' : 'http';
    }

    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
      return 'https';
    }

    if (!empty($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
      return 'https';
    }

    return 'http';
  }
}

if (!function_exists('nivasity_detect_request_host')) {
  function nivasity_detect_request_host() {
    $candidates = [];

    if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
      $candidates[] = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_HOST'])[0];
    }

    if (!empty($_SERVER['HTTP_HOST'])) {
      $candidates[] = (string) $_SERVER['HTTP_HOST'];
    }

    if (!empty($_SERVER['SERVER_NAME'])) {
      $candidates[] = (string) $_SERVER['SERVER_NAME'];
    }

    foreach ($candidates as $candidate) {
      $host = nivasity_normalize_host($candidate);
      if ($host !== '') {
        return $host;
      }
    }

    return '';
  }
}

if (!function_exists('nivasity_get_tenant_context')) {
  function nivasity_get_tenant_context() {
    if (!isset($GLOBALS['nivasity_tenant_context']) || !is_array($GLOBALS['nivasity_tenant_context'])) {
      $host = nivasity_detect_request_host();
      $domain = $host !== ''
        ? nivasity_detect_request_scheme() . '://' . $host
        : nivasity_default_domain();

      $GLOBALS['nivasity_tenant_context'] = [
        'school_id' => 0,
        'school_name' => '',
        'school_code' => '',
        'host' => $host,
        'domain' => rtrim($domain, '/'),
        'resolved' => false,
      ];
    }

    return $GLOBALS['nivasity_tenant_context'];
  }
}

if (!function_exists('nivasity_set_tenant_context')) {
  function nivasity_set_tenant_context(array $context) {
    $current = nivasity_get_tenant_context();

    if (isset($context['host'])) {
      $current['host'] = nivasity_normalize_host($context['host']);
    }

    if (isset($context['school_id'])) {
      $current['school_id'] = (int) $context['school_id'];
    }

    if (isset($context['school_name'])) {
      $current['school_name'] = (string) $context['school_name'];
    }

    if (isset($context['school_code'])) {
      $current['school_code'] = (string) $context['school_code'];
    }

    if (isset($context['resolved'])) {
      $current['resolved'] = (bool) $context['resolved'];
    }

    if (isset($context['domain']) && trim((string) $context['domain']) !== '') {
      $current['domain'] = rtrim((string) $context['domain'], '/');
    } elseif (!empty($current['host'])) {
      $current['domain'] = nivasity_detect_request_scheme() . '://' . $current['host'];
    }

    if (empty($current['domain'])) {
      $current['domain'] = nivasity_default_domain();
    }

    $GLOBALS['nivasity_tenant_context'] = $current;

    return $current;
  }
}

if (!function_exists('nivasity_app_domain')) {
  function nivasity_app_domain() {
    $context = nivasity_get_tenant_context();
    $domain = isset($context['domain']) ? trim((string) $context['domain']) : '';

    if ($domain === '') {
      $host = nivasity_detect_request_host();
      $domain = $host !== ''
        ? nivasity_detect_request_scheme() . '://' . $host
        : nivasity_default_domain();
    }

    return rtrim($domain, '/');
  }
}

if (!function_exists('nivasity_app_url')) {
  function nivasity_app_url($path = '') {
    $baseUrl = nivasity_app_domain();
    $normalizedPath = ltrim((string) $path, '/');

    if ($normalizedPath === '') {
      return $baseUrl;
    }

    return $baseUrl . '/' . $normalizedPath;
  }
}

if (!function_exists('nivasity_asset_url')) {
  function nivasity_asset_url($path = '') {
    return nivasity_app_url($path);
  }
}

if (!function_exists('nivasity_school_id')) {
  function nivasity_school_id() {
    $context = nivasity_get_tenant_context();
    return isset($context['school_id']) ? (int) $context['school_id'] : 0;
  }
}

if (!function_exists('nivasity_stage_school_id')) {
  function nivasity_stage_school_id() {
    return 1;
  }
}

if (!function_exists('nivasity_is_stage_host')) {
  function nivasity_is_stage_host($host) {
    $host = nivasity_normalize_host($host);
    return in_array($host, ['stage.nivasity.com', 'staging.nivasity.com'], true);
  }
}

if (!function_exists('nivasity_resolve_stage_school')) {
  function nivasity_resolve_stage_school($conn, $host) {
    $schoolId = nivasity_stage_school_id();
    $schoolName = '';
    $schoolCode = '';

    $stmt = mysqli_prepare($conn, "SELECT id, name, code FROM schools WHERE id = ? LIMIT 1");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, 'i', $schoolId);
      mysqli_stmt_execute($stmt);
      $result = mysqli_stmt_get_result($stmt);
      if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);
        $schoolId = (int) $row['id'];
        $schoolName = isset($row['name']) ? (string) $row['name'] : '';
        $schoolCode = isset($row['code']) ? (string) $row['code'] : '';
      }
      mysqli_stmt_close($stmt);
    }

    return nivasity_set_tenant_context([
      'school_id' => $schoolId,
      'school_name' => $schoolName,
      'school_code' => $schoolCode,
      'host' => $host,
      'domain' => nivasity_detect_request_scheme() . '://' . $host,
      'resolved' => true,
    ]);
  }
}

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

    if (nivasity_is_stage_host($host)) {
      return nivasity_resolve_stage_school($conn, $host);
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