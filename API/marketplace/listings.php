<?php
// API: Marketplace Listings
// GET  (no id)   → paginated list with filters
// GET  (?id=X)   → single listing detail
// POST           → create listing (multipart/form-data, level 2 required)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/marketplace_helpers.php';

$method = $_SERVER['REQUEST_METHOD'];

// ── GET: list or detail ──────────────────────────────────────────────────────
if ($method === 'GET') {
    // Optional auth: visitors can browse
    $user = getAuthenticatedUser($conn);

    if (isset($_GET['id'])) {
        // ── Single listing detail ────────────────────────────────────────────
        $listing_id = (int)$_GET['id'];
        if ($listing_id <= 0) sendApiError('Invalid listing ID', 400);

        $q = mysqli_query($conn, "SELECT * FROM marketplace_listings WHERE id = $listing_id AND status != 'draft' LIMIT 1");
        if (!$q || mysqli_num_rows($q) === 0) sendApiError('Listing not found', 404);

        $row = mysqli_fetch_assoc($q);

        // Sellers can see their own drafts
        if ($row['status'] === 'draft' && (!$user || (int)$user['id'] !== (int)$row['seller_id'])) {
            sendApiError('Listing not found', 404);
        }

        sendApiSuccess('Listing retrieved successfully', marketplaceFormatListing($conn, $row));
    }

    // ── Paginated listing list ────────────────────────────────────────────────
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $limit   = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset  = ($page - 1) * $limit;

    $where = ["ml.status = 'active'"];

    if (!empty($_GET['school_id'])) {
        $sid = (int)$_GET['school_id'];
        $where[] = "ml.school_id = $sid";
    }
    if (!empty($_GET['category'])) {
        $valid_cats = ['academic','hostel','skills','food','fashion','tech','beauty'];
        $cat = strtolower(sanitizeInput($conn, $_GET['category']));
        if (in_array($cat, $valid_cats, true)) {
            $where[] = "ml.category = '$cat'";
        }
    }
    if (!empty($_GET['product_type'])) {
        $valid_types = ['product','service','free'];
        $pt = strtolower(sanitizeInput($conn, $_GET['product_type']));
        if (in_array($pt, $valid_types, true)) {
            $where[] = "ml.product_type = '$pt'";
        }
    }
    if (!empty($_GET['search'])) {
        $q_str = sanitizeInput($conn, $_GET['search']);
        $where[] = "(ml.title LIKE '%$q_str%' OR ml.description LIKE '%$q_str%')";
    }
    if (isset($_GET['is_free']) && $_GET['is_free'] === 'true') {
        $where[] = "ml.is_free = 1";
    }
    if (isset($_GET['is_moving_out']) && $_GET['is_moving_out'] === 'true') {
        $where[] = "ml.is_moving_out = 1";
    }
    if (isset($_GET['flash_sale']) && $_GET['flash_sale'] === 'true') {
        $where[] = "ml.flash_until IS NOT NULL AND ml.flash_until > NOW()";
    }
    if (!empty($_GET['seller_id'])) {
        $sel = (int)$_GET['seller_id'];
        $where[] = "ml.seller_id = $sel";
    }

    $where_clause = implode(' AND ', $where);

    $sort = strtolower($_GET['sort'] ?? 'newest');
    $order_by = match ($sort) {
        'price_asc'  => 'ml.price ASC',
        'price_desc' => 'ml.price DESC',
        'popular'    => 'ml.created_at DESC',   // placeholder
        default      => 'ml.created_at DESC',   // newest
    };

    $count_q = mysqli_query($conn, "SELECT COUNT(*) AS total FROM marketplace_listings ml WHERE $where_clause");
    $total   = (int)mysqli_fetch_assoc($count_q)['total'];

    $result = mysqli_query($conn, "SELECT ml.* FROM marketplace_listings ml WHERE $where_clause ORDER BY $order_by LIMIT $limit OFFSET $offset");

    $listings = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $listings[] = marketplaceFormatListing($conn, $row);
    }

    sendApiSuccess('Listings retrieved successfully', $listings, 200);

// ── POST: create listing ─────────────────────────────────────────────────────
} elseif ($method === 'POST') {
    $user = authenticateApiRequest($conn);
    requireStudentRole($user);

    $user_id   = (int)$user['id'];
    $school_id = (int)$user['school'];

    // Check seller level
    $level_q = mysqli_query($conn, "SELECT id FROM marketplace_seller_verifications WHERE user_id = $user_id AND status = 'approved' LIMIT 1");
    if (!$level_q || mysqli_num_rows($level_q) === 0) {
        sendApiError('Seller verification required. Submit your Student ID to become a seller.', 403);
    }

    // Collect form fields (multipart)
    $title        = sanitizeInput($conn, trim($_POST['title'] ?? ''));
    $description  = sanitizeInput($conn, trim($_POST['description'] ?? ''));
    $category     = sanitizeInput($conn, strtolower(trim($_POST['category'] ?? '')));
    $product_type = sanitizeInput($conn, strtolower(trim($_POST['product_type'] ?? 'product')));
    $price        = max(0, (float)($_POST['price'] ?? 0));
    $condition    = sanitizeInput($conn, strtolower(trim($_POST['condition'] ?? '')));
    $course_code  = sanitizeInput($conn, strtoupper(trim($_POST['course_code'] ?? '')));
    $is_free      = (isset($_POST['is_free']) && $_POST['is_free'] === 'true') ? 1 : 0;
    $is_moving_out = (isset($_POST['is_moving_out']) && $_POST['is_moving_out'] === 'true') ? 1 : 0;
    $stock        = isset($_POST['stock']) ? max(0, (int)$_POST['stock']) : 'NULL';

    if (empty($title))    sendApiError('Title is required', 400);
    if (empty($category)) sendApiError('Category is required', 400);

    $valid_cats  = ['academic','hostel','skills','food','fashion','tech','beauty'];
    $valid_types = ['product','service','free'];
    $valid_conds = ['new','like_new','good','fair',''];

    if (!in_array($category, $valid_cats, true))     sendApiError('Invalid category', 400);
    if (!in_array($product_type, $valid_types, true)) sendApiError('Invalid product_type', 400);
    if (!in_array($condition, $valid_conds, true))    sendApiError('Invalid condition', 400);

    if ($product_type === 'free') { $price = 0; $is_free = 1; }
    if ($product_type !== 'free' && $price <= 0) {
        sendApiError('Price must be greater than 0 for paid listings', 400);
    }

    $condition_sql = empty($condition) ? 'NULL' : "'$condition'";
    $course_sql    = empty($course_code) ? 'NULL' : "'$course_code'";
    $stock_sql     = is_numeric($stock) ? $stock : 'NULL';

    // Handle image uploads (up to 5)
    $image_urls = [];
    $upload_dir = __DIR__ . '/../../../assets/images/marketplace/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }

    for ($i = 0; $i < 5; $i++) {
        $file_key = "images[$i]";
        if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) continue;

        $file     = $_FILES[$file_key];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg','jpeg','png','webp','gif'];
        if (!in_array($ext, $allowed, true)) continue;

        $filename = 'listing_' . $user_id . '_' . time() . '_' . $i . '.' . $ext;
        $dest     = $upload_dir . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $image_urls[] = 'assets/images/marketplace/' . $filename;
        }
    }
    $images_json = mysqli_real_escape_string($conn, json_encode($image_urls));

    // Insert listing as 'active' immediately
    $ins = mysqli_query($conn, "
        INSERT INTO marketplace_listings
            (title, description, category, product_type, price, `condition`, status,
             seller_id, school_id, course_code, stock, is_free, is_moving_out, images)
        VALUES
            ('$title', '$description', '$category', '$product_type', $price, $condition_sql, 'active',
             $user_id, $school_id, $course_sql, $stock_sql, $is_free, $is_moving_out, '$images_json')
    ");

    if (!$ins || mysqli_affected_rows($conn) === 0) {
        sendApiError('Failed to create listing. ' . mysqli_error($conn), 500);
    }

    $new_id = mysqli_insert_id($conn);
    $row_q  = mysqli_query($conn, "SELECT * FROM marketplace_listings WHERE id = $new_id LIMIT 1");
    $row    = mysqli_fetch_assoc($row_q);

    sendApiSuccess('Listing created successfully', marketplaceFormatListing($conn, $row), 201);

} else {
    sendApiError('Method not allowed', 405);
}
?>
