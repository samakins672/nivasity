<?php
// API: Logout
require_once __DIR__ . '/../config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

session_start();
session_unset();
session_destroy();

sendApiSuccess('You have successfully logged out!');
?>
