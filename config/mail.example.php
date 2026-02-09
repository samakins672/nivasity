<?php
// SMTP credentials (fallback configuration)
// These credentials are used when Brevo credits are low or unavailable
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'smtp-user@example.com');
define('SMTP_PASSWORD', 'smtp-password');

// Brevo credentials (primary email service)
// The system automatically checks Brevo credits before sending emails
// If subscription credits are <= 50, it falls back to SMTP automatically
define('BREVO_API_KEY', 'your-brevo-api-key');
define('BREVO_SENDER_EMAIL', 'no-reply@example.com');
define('BREVO_SENDER_NAME', 'Nivasity');
// Optional reply-to address for Brevo messages
define('BREVO_REPLY_TO_EMAIL', 'support@example.com');
