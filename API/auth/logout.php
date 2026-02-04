<?php
// API: Logout
require_once __DIR__ . '/../config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

// With JWT, logout is handled client-side by deleting the token
// This endpoint serves as a confirmation point
// In a more advanced implementation, you could add token to a blacklist

sendApiSuccess('You have successfully logged out! Please delete your access and refresh tokens on the client side.');
?>
