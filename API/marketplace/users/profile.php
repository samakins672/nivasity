<?php
// API: Public student seller profile (GET ?id=X)
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../../model/marketplace_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendApiError('Method not allowed', 405);

// Optional auth — visitors can view profiles
$viewer = getAuthenticatedUser($conn);

if (empty($_GET['id'])) sendApiError('User ID is required', 400);
$target_id = (int)$_GET['id'];
if ($target_id <= 0) sendApiError('Invalid user ID', 400);

$user_q = mysqli_query($conn, "
    SELECT u.id, u.first_name, u.last_name, u.profile_pic, u.school, u.created_at,
           s.name AS school_name,
           v.status AS verify_status,
           COALESCE(AVG(r.rating), NULL) AS avg_rating,
           COUNT(DISTINCT r.id) AS total_reviews,
           (SELECT COUNT(*) FROM marketplace_listings ml WHERE ml.seller_id = u.id AND ml.status = 'active') AS total_listings,
           (SELECT COUNT(*) FROM marketplace_orders mo WHERE mo.seller_id = u.id AND mo.status = 'completed') AS total_sales
    FROM users u
    LEFT JOIN schools s ON s.id = u.school
    LEFT JOIN marketplace_seller_verifications v ON v.user_id = u.id
    LEFT JOIN marketplace_reviews r ON r.seller_id = u.id
    WHERE u.id = $target_id
      AND u.status NOT IN ('deactivated','denied')
    GROUP BY u.id
    LIMIT 1
");

if (!$user_q || mysqli_num_rows($user_q) === 0) sendApiError('User not found', 404);
$user = mysqli_fetch_assoc($user_q);

// Recent reviews
$reviews_q = mysqli_query($conn, "
    SELECT r.*, u2.first_name, u2.last_name, u2.profile_pic AS reviewer_pic
    FROM marketplace_reviews r
    LEFT JOIN users u2 ON u2.id = r.reviewer_id
    WHERE r.seller_id = $target_id
    ORDER BY r.created_at DESC
    LIMIT 10
");
$reviews = [];
while ($row = mysqli_fetch_assoc($reviews_q)) {
    $reviews[] = [
        'id'         => (int)$row['id'],
        'rating'     => (int)$row['rating'],
        'comment'    => $row['comment'],
        'listing_id' => (int)$row['listing_id'],
        'reviewer'   => [
            'id'          => (int)$row['reviewer_id'],
            'first_name'  => $row['first_name'],
            'last_name'   => $row['last_name'],
            'profile_pic' => $row['reviewer_pic'],
        ],
        'created_at' => $row['created_at'],
    ];
}

sendApiSuccess('Profile retrieved', [
    'id'             => (int)$user['id'],
    'first_name'     => $user['first_name'],
    'last_name'      => $user['last_name'],
    'profile_pic'    => $user['profile_pic'],
    'school_name'    => $user['school_name'],
    'level'          => ($user['verify_status'] === 'approved') ? 2 : 1,
    'avg_rating'     => $user['avg_rating'] !== null ? round((float)$user['avg_rating'], 1) : null,
    'total_reviews'  => (int)$user['total_reviews'],
    'total_listings' => (int)$user['total_listings'],
    'total_sales'    => (int)$user['total_sales'],
    'member_since'   => substr($user['created_at'], 0, 10),
    'reviews'        => $reviews,
]);
?>
