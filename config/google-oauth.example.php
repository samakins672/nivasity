<?php
/**
 * Google OAuth Configuration Example
 * 
 * SETUP INSTRUCTIONS:
 * 1. Copy this file to google-oauth.php
 * 2. Replace the placeholder values with your actual Google OAuth credentials
 * 3. Never commit google-oauth.php to version control
 * 
 * HOW TO GET CREDENTIALS:
 * 1. Go to https://console.cloud.google.com/
 * 2. Create a new project or select existing project
 * 3. Enable "Google Identity Services API" or "Google+ API"
 * 4. Go to "Credentials" section
 * 5. Click "Create Credentials" > "OAuth 2.0 Client ID"
 * 6. Configure OAuth consent screen if not already done
 * 7. Select "Web application" or "Android/iOS" as application type
 * 8. Add authorized redirect URIs (your API domain)
 * 9. Copy the Client ID and Client Secret below
 * 
 * SECURITY NOTES:
 * - Keep these credentials SECRET
 * - Never share or commit to public repositories
 * - Rotate credentials if compromised
 * - Use environment variables in production if possible
 */

// Google OAuth Client ID
// Example: 123456789-abcdefghijklmnop.apps.googleusercontent.com
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID_HERE');

// Google OAuth Client Secret (optional for mobile apps, required for web)
// Example: GOCSPX-abcdefghijklmnop123456
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET_HERE');

// Optional: Redirect URI for web applications
// define('GOOGLE_REDIRECT_URI', 'https://yourdomain.com/auth/google/callback');

?>
