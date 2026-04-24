<?php

if (!function_exists('nivasity_current_request_path')) {
  function nivasity_current_request_path() {
    $requestUri = isset($_SERVER['REQUEST_URI']) ? trim((string) $_SERVER['REQUEST_URI']) : '';
    if ($requestUri !== '') {
      $parsed = parse_url($requestUri);
      $path = isset($parsed['path']) ? trim((string) $parsed['path']) : '';
      $query = isset($parsed['query']) ? trim((string) $parsed['query']) : '';

      if ($path !== '' && strpos($path, '/') === 0) {
        return $path . ($query !== '' ? '?' . $query : '');
      }
    }

    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? trim((string) $_SERVER['SCRIPT_NAME']) : '';
    $queryString = isset($_SERVER['QUERY_STRING']) ? trim((string) $_SERVER['QUERY_STRING']) : '';
    if ($scriptName === '') {
      return '/';
    }

    return $scriptName . ($queryString !== '' ? '?' . $queryString : '');
  }
}

if (!function_exists('nivasity_signin_url_with_redirect')) {
  function nivasity_signin_url_with_redirect($params = array(), $redirectTarget = null) {
    $queryParams = is_array($params) ? $params : array();
    $target = $redirectTarget;
    if ($target === null || trim((string) $target) === '') {
      $target = nivasity_current_request_path();
    }

    $target = trim((string) $target);
    if ($target !== '') {
      $queryParams['redirect'] = $target;
    }

    $queryString = http_build_query($queryParams);
    return nivasity_app_url('signin.html' . ($queryString !== '' ? '?' . $queryString : ''));
  }
}

?>