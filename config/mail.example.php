<?php
// SMTP credentials (existing configuration)
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'smtp-user@example.com');
define('SMTP_PASSWORD', 'smtp-password');

// Brevo credentials (new configuration)
define('BREVO_API_KEY', 'your-brevo-api-key');
define('BREVO_SENDER_EMAIL', 'no-reply@example.com');
define('BREVO_SENDER_NAME', 'Nivasity');
// Optional reply-to address for Brevo messages
define('BREVO_REPLY_TO_EMAIL', 'support@example.com');
