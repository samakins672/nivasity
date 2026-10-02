<?php
// API Configuration
header('Content-Type: application/json');

// CORS: allow credentials from known Nivasity origins
$allowed_origins = [
    'https://marketplace.nivasity.com',
    'https://school.nivasity.com',
    'https://app.nivasity.com',
    'http://localhost:8080',
    'http://localhost:5173',
    'http://localhost:3000',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include main config
require_once __DIR__ . '/../model/config.php';
require_once __DIR__ . '/../model/functions.php';

// API Response Helper
// Keep valid UTF-8 characters as they are and convert only stray bytes (Windows-1252 curly
// quotes, dashes) to UTF-8, so a mixed string keeps its emoji and accents.
function nivasityFixUtf8(string $value): string {
    $validChar = '[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'
        . '|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2}';
    $fixed = preg_replace_callback('/(' . $validChar . ')|(.)/s', static function ($m) {
        return (isset($m[2]) && $m[2] !== '') ? mb_convert_encoding($m[2], 'UTF-8', 'Windows-1252') : $m[1];
    }, $value);
    return $fixed === null ? $value : $fixed;
}

function sendApiResponse($status, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }

    // Text pasted from Word/Windows (curly quotes, dashes) can reach us as Windows-1252
    // bytes, which made json_encode() return false and the reply came out empty. Convert
    // such strings to UTF-8, and substitute anything still invalid instead of failing.
    array_walk_recursive($response, static function (&$value) {
        if (is_string($value) && $value !== '' && function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
            $value = nivasityFixUtf8($value);
        }
    });
    $json = json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        $json = json_encode(['status' => 'error', 'message' => 'Could not prepare the response: ' . json_last_error_msg()]);
    }
    echo $json;
    exit();
}

// API Error Handler
function sendApiError($message, $statusCode = 400) {
    sendApiResponse('error', $message, null, $statusCode);
}

// API Success Handler
function sendApiSuccess($message, $data = null, $statusCode = 200) {
    sendApiResponse('success', $message, $data, $statusCode);
}

// Validate required fields
function validateRequiredFields($fields, $data) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        sendApiError('Missing required fields: ' . implode(', ', $missing), 400);
    }
}

// Sanitize input
// Serialises signups for one email address (normal and Google) so a double tap or two
// simultaneous requests cannot create two accounts. Released when the request ends.
function acquireSignupLock($conn, $email) {
    $key = 'nvsignup:' . md5(strtolower(trim((string)$email)));
    $rs = mysqli_query($conn, "SELECT GET_LOCK('$key', 10) AS l");
    $row = $rs ? mysqli_fetch_assoc($rs) : null;
    if (!$row || (int)$row['l'] !== 1) {
        sendApiError('Your sign-up is already being processed. Please wait a moment and try again.', 429);
    }
}

// Partly hidden email (ad•••@gmail.com) to point a student to their other account without revealing it.
function maskEmailForHint($email) {
    $email = trim((string)$email);
    $at = strpos($email, '@');
    if ($at === false || $at < 1) {
        return '';
    }
    $local = substr($email, 0, $at);
    $visible = strlen($local) <= 2 ? substr($local, 0, 1) : substr($local, 0, 2);
    return $visible . '•••' . substr($email, $at);
}

function sanitizeInput($conn, $data) {
    if (is_array($data)) {
        return array_map(function($item) use ($conn) {
            return sanitizeInput($conn, $item);
        }, $data);
    }
    return mysqli_real_escape_string($conn, trim($data));
}
?>
