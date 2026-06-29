<?php
// API: Seller dashboard analytics
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') sendApiError('Method not allowed', 405);

$user    = authenticateApiRequest($conn);
requireStudentRole($user);
$user_id = (int)$user['id'];

// Total orders (all time)
$total_orders_q = mysqli_query($conn, "SELECT COUNT(*) AS c FROM marketplace_orders WHERE seller_id = $user_id");
$total_orders   = (int)mysqli_fetch_assoc($total_orders_q)['c'];

// Total revenue (completed orders only)
$revenue_q = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS total FROM marketplace_orders WHERE seller_id = $user_id AND status = 'completed'");
$total_revenue = (float)mysqli_fetch_assoc($revenue_q)['total'];

// Average rating
$rating_q  = mysqli_query($conn, "SELECT COALESCE(AVG(rating), 0) AS avg, COUNT(*) AS cnt FROM marketplace_reviews WHERE seller_id = $user_id");
$rating_row = mysqli_fetch_assoc($rating_q);
$avg_rating     = round((float)$rating_row['avg'], 1);
$total_reviews  = (int)$rating_row['cnt'];

// Unique customers
$customers_q = mysqli_query($conn, "SELECT COUNT(DISTINCT buyer_id) AS c FROM marketplace_orders WHERE seller_id = $user_id AND status != 'cancelled'");
$total_customers = (int)mysqli_fetch_assoc($customers_q)['c'];

// Orders by product type
$type_q = mysqli_query($conn, "
    SELECT product_type, COUNT(*) AS c
    FROM marketplace_orders
    WHERE seller_id = $user_id
    GROUP BY product_type
");
$orders_by_type = ['product' => 0, 'service' => 0, 'free' => 0];
while ($row = mysqli_fetch_assoc($type_q)) {
    if (isset($orders_by_type[$row['product_type']])) {
        $orders_by_type[$row['product_type']] = (int)$row['c'];
    }
}

// Revenue chart: last 30 days grouped by date
$chart_q = mysqli_query($conn, "
    SELECT DATE(created_at) AS date, SUM(amount) AS amount
    FROM marketplace_orders
    WHERE seller_id = $user_id
      AND status = 'completed'
      AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$revenue_chart = [];
while ($row = mysqli_fetch_assoc($chart_q)) {
    $revenue_chart[] = [
        'date'   => $row['date'],
        'amount' => (float)$row['amount'],
    ];
}

// Recent reviews (last 5)
$reviews_q = mysqli_query($conn, "
    SELECT r.*, u.first_name, u.last_name, u.profile_pic
    FROM marketplace_reviews r
    LEFT JOIN users u ON u.id = r.reviewer_id
    WHERE r.seller_id = $user_id
    ORDER BY r.created_at DESC
    LIMIT 5
");
$recent_reviews = [];
while ($row = mysqli_fetch_assoc($reviews_q)) {
    $recent_reviews[] = [
        'id'         => (int)$row['id'],
        'rating'     => (int)$row['rating'],
        'comment'    => $row['comment'],
        'listing_id' => (int)$row['listing_id'],
        'reviewer'   => [
            'id'          => (int)$row['reviewer_id'],
            'first_name'  => $row['first_name'],
            'last_name'   => $row['last_name'],
            'profile_pic' => $row['profile_pic'],
        ],
        'created_at' => $row['created_at'],
    ];
}

sendApiSuccess('Dashboard retrieved', [
    'total_orders'    => $total_orders,
    'total_revenue'   => $total_revenue,
    'avg_rating'      => $avg_rating,
    'total_reviews'   => $total_reviews,
    'total_customers' => $total_customers,
    'orders_by_type'  => $orders_by_type,
    'revenue_chart'   => $revenue_chart,
    'recent_reviews'  => $recent_reviews,
]);
?>
