<?php
// API Configuration
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
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
function sanitizeInput($conn, $data) {
    if (is_array($data)) {
        return array_map(function($item) use ($conn) {
            return sanitizeInput($conn, $item);
        }, $data);
    }
    return mysqli_real_escape_string($conn, trim($data));
}
?>
