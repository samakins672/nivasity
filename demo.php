<?php
/**
 * DEMO.PHP - Quick Login Handler
 * 
 * This file handles quick login links for students
 * Security: Uses 64-character random codes that expire in 24 hours
 */

session_start();

// Include database configuration
require_once('model/config.php');

// Get the code from URL parameter
$code = $_GET['code'] ?? '';

if (empty($code)) {
    // No code provided - show error
    http_response_code(400);
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Invalid Access</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                padding: 50px; 
                text-align: center;
                background-color: #f5f5f5;
            }
            .error { 
                color: #d32f2f;
                margin-bottom: 20px;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                max-width: 600px;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 class="error">Invalid Access</h1>
            <p>No login code provided. Please use the link provided by your administrator.</p>
        </div>
    </body>
    </html>
    ');
}

// Sanitize the code
$code = mysqli_real_escape_string($conn, $code);

// Get current datetime
$now = date('Y-m-d H:i:s');

// Query to fetch the login code details
// Check user status to ensure only verified/active accounts can use quick login
$query = "SELECT qlc.*, u.email, u.id as user_id, u.role, u.first_name, u.last_name, u.school, u.dept, u.status
          FROM quick_login_codes qlc
          JOIN users u ON qlc.student_id = u.id
          WHERE qlc.code = '$code'
          AND qlc.status = 'active'
          AND qlc.expiry_datetime > '$now'
          AND u.status NOT IN ('unverified', 'denied', 'deactivated')
          LIMIT 1";

$result = mysqli_query($conn, $query);

// Check for database errors
if ($result === FALSE) {
    http_response_code(500);
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>System Error</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                padding: 50px; 
                text-align: center;
                background-color: #f5f5f5;
            }
            .error { 
                color: #d32f2f;
                margin-bottom: 20px;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                max-width: 600px;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 class="error">System Error</h1>
            <p>An error occurred while processing your request. Please try again later.</p>
        </div>
    </body>
    </html>
    ');
}

if (mysqli_num_rows($result) == 0) {
    // Invalid or expired code
    http_response_code(403);
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>Invalid Login Link</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                padding: 50px; 
                text-align: center;
                background-color: #f5f5f5;
            }
            .error { 
                color: #d32f2f;
                margin-bottom: 20px;
            }
            .info { 
                color: #666; 
                margin-top: 20px;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                max-width: 600px;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 class="error">Invalid or Expired Login Link</h1>
            <p>This login link is either invalid, has already been used, or has expired.</p>
            <p class="info">Quick login links are valid for 24 hours from creation.</p>
            <p class="info">Please contact your administrator to generate a new login link.</p>
        </div>
    </body>
    </html>
    ');
}

$login_data = mysqli_fetch_assoc($result);

// Mark the code as used to prevent reuse
$update_result = mysqli_query($conn, "UPDATE quick_login_codes SET status = 'used' WHERE code = '$code'");

// Check if update was successful
if (!$update_result || mysqli_affected_rows($conn) == 0) {
    http_response_code(500);
    die('
    <!DOCTYPE html>
    <html>
    <head>
        <title>System Error</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
        <style>
            body { 
                font-family: Arial, sans-serif; 
                padding: 50px; 
                text-align: center;
                background-color: #f5f5f5;
            }
            .error { 
                color: #d32f2f;
                margin-bottom: 20px;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                max-width: 600px;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1 class="error">System Error</h1>
            <p>Failed to process login. Please try again.</p>
        </div>
    </body>
    </html>
    ');
}

// Set session variables for the user (matching the application's session structure)
$_SESSION['nivas_userId'] = $login_data['user_id'];
$_SESSION['nivas_userRole'] = $login_data['role'];
$_SESSION['nivas_userName'] = $login_data['first_name'];
$_SESSION['nivas_userSch'] = $login_data['school'];

// Update last login timestamp
mysqli_query($conn, "UPDATE users SET last_login = '$now' WHERE id = " . $login_data['user_id']);

// Redirect to the appropriate dashboard based on user role
// Students go to store.php, others go to their respective dashboards
if ($login_data['role'] === 'student') {
    header('Location: /?loggedin');
} elseif ($login_data['role'] === 'hoc' || $login_data['role'] === 'org_admin') {
    header('Location: /admin?loggedin');
} else {
    // Fallback to store page
    header('Location: /?loggedin');
}
exit();
?>
