<?php
// API Index - Nivasity Mobile API
header('Content-Type: application/json');

$response = [
    'name' => 'Nivasity Mobile API',
    'version' => '1.0.0',
    'description' => 'API for Nivasity mobile application',
    'status' => 'active',
    'endpoints' => [
        'authentication' => [
            'POST /auth/register.php' => 'Register new student account',
            'POST /auth/login.php' => 'Login to account',
            'POST /auth/logout.php' => 'Logout from account',
            'POST /auth/resend-verification.php' => 'Resend verification email'
        ],
        'profile' => [
            'GET /profile/profile.php' => 'Get user profile',
            'POST /profile/update-profile.php' => 'Update profile',
            'POST /profile/request-email-change.php' => 'Send an OTP to a new email address before changing it',
            'POST /profile/verify-email-change.php' => 'Verify the OTP for a pending email change and update the account email',
            'POST /profile/change-password.php' => 'Change password',
            'POST /profile/delete-account.php' => 'Delete account'
        ],
        'app' => [
            'GET /app/update-config.php' => 'Get the latest Android and iOS app update prompt configuration'
        ],
        'materials' => [
            'GET /materials/list.php' => 'List available materials',
            'GET /materials/details.php' => 'Get material details',
            'POST /materials/cart-add.php' => 'Add material to cart',
            'POST /materials/cart-remove.php' => 'Remove material from cart',
            'GET /materials/cart-view.php' => 'View cart',
            'GET /materials/purchased.php' => 'List purchased materials',
            'GET /materials/change.php' => 'List eligible replacement materials for a purchased item',
            'POST /materials/change.php' => 'Change a purchased material once if it is not granted and the replacement price matches'
        ],
        'payment' => [
            'POST /payment/init.php' => 'Initialize payment',
            'GET /payment/verify.php' => 'Verify payment',
            'GET /payment/transactions.php' => 'Get transaction history',
            'GET /payment/process-settlements.php' => 'Run Friday school settlement sweep (cron/manual trigger)'
        ],
        'wallet' => [
            'POST /wallet/create.php' => 'Create wallet on explicit user request',
            'GET /wallet/summary.php' => 'Get wallet summary without auto-provisioning',
            'GET /wallet/transactions.php' => 'List wallet credit and debit ledger entries (20 per page)',
            'POST /wallet/pin.php' => 'Send Wallet PIN code, verify it, and save/update 4-digit Wallet PIN',
            'POST /wallet/refresh-credits.php' => 'Refresh missed DVA wallet credits from Paystack'
        ],
        'support' => [
            'POST /support/create-ticket.php' => 'Create support ticket',
            'GET /support/list-tickets.php' => 'List support tickets',
            'GET /support/ticket-details.php' => 'Get ticket details',
            'POST /support/reply.php' => 'Reply to ticket'
        ]
    ],
    'documentation' => 'See README.md for full API documentation'
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
