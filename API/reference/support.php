<?php
// API: Get Support Contact Information
require_once __DIR__ . '/../config.php';

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendApiError('Method not allowed', 405);
}

// No authentication required for support contact information
// This is public information users need to contact support

// Fetch support contact information
$query = "SELECT id, whatsapp, email, phone, status, updated_at 
          FROM support_contacts 
          WHERE status = 'active' 
          ORDER BY id DESC 
          LIMIT 1";

$result = mysqli_query($conn, $query);

if (!$result) {
    sendApiError('Database query failed', 500);
}

$contact = mysqli_fetch_assoc($result);

if (!$contact) {
    // Return default/empty response if no contact info configured
    sendApiSuccess('No support contact information available', [
        'contact' => [
            'whatsapp' => null,
            'email' => null,
            'phone' => null
        ]
    ]);
}

// Return support contact information
sendApiSuccess('Support contact information retrieved successfully', [
    'contact' => [
        'whatsapp' => $contact['whatsapp'],
        'email' => $contact['email'],
        'phone' => $contact['phone'],
        'updated_at' => $contact['updated_at']
    ]
]);
?>
