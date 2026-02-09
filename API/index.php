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
            'POST /profile/change-password.php' => 'Change password',
            'POST /profile/delete-account.php' => 'Delete account'
        ],
        'materials' => [
            'GET /materials/list.php' => 'List available materials',
            'GET /materials/details.php' => 'Get material details',
            'POST /materials/cart-add.php' => 'Add material to cart',
            'POST /materials/cart-remove.php' => 'Remove material from cart',
            'GET /materials/cart-view.php' => 'View cart',
            'GET /materials/purchased.php' => 'List purchased materials'
        ],
        'payment' => [
            'POST /payment/init.php' => 'Initialize payment',
            'GET /payment/verify.php' => 'Verify payment',
            'GET /payment/transactions.php' => 'Get transaction history'
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
