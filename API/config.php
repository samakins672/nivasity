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
function sendApiResponse($status, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
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
