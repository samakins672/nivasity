<?php
// SMTP credentials (fallback configuration)
// These credentials are used when Resend is unavailable or fails
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'smtp-user@example.com');
define('SMTP_PASSWORD', 'smtp-password');

// Resend credentials (primary email service)
// Get your API key from: https://resend.com/api-keys
define('RESEND_API_KEY', 're_your_resend_api_key');
define('RESEND_SENDER_EMAIL', 'no-reply@yourdomain.com');
define('RESEND_SENDER_NAME', 'Nivasity');
// Optional reply-to address
define('RESEND_REPLY_TO_EMAIL', 'support@yourdomain.com');

// Legacy Brevo credentials (optional)
define('BREVO_API_KEY', 'your-brevo-api-key');
define('BREVO_SENDER_EMAIL', 'no-reply@example.com');
define('BREVO_SENDER_NAME', 'Nivasity');
define('BREVO_REPLY_TO_EMAIL', 'support@example.com');
