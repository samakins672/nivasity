<?php
// Shared helpers for all marketplace PHP endpoints.
// Include this after config.php and auth.php.

if (!function_exists('marketplaceAssetsBase')) {
    function marketplaceAssetsBase() {
        $proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? 'api.nivasity.com';
        return $proto . '://' . $host;
    }
}

// Turn a JSON-encoded images string from DB into a real array of URLs.
if (!function_exists('marketplaceParseImages')) {
    function marketplaceParseImages($images_json) {
        if (empty($images_json)) return [];
        $paths = json_decode($images_json, true);
        if (!is_array($paths)) return [];
        $base = marketplaceAssetsBase();
        return array_map(function($p) use ($base) {
            // Already a full URL → return as-is
            if (strpos($p, 'http') === 0) return $p;
            return $base . '/' . ltrim($p, '/');
        }, array_filter($paths));
    }
}

// Returns a seller profile array for the given user_id.
if (!function_exists('marketplaceGetSellerProfile')) {
    function marketplaceGetSellerProfile($conn, $user_id) {
        $user_id = (int)$user_id;
        if ($user_id <= 0) return null;

        $q = mysqli_query($conn, "
            SELECT u.id, u.first_name, u.last_name, u.profile_pic, u.school,
                   s.name AS school_name,
                   v.status AS verify_status,
                   COALESCE(AVG(r.rating), NULL) AS avg_rating,
                   COUNT(r.id) AS total_reviews,
                   (SELECT COUNT(*) FROM marketplace_listings ml WHERE ml.seller_id = u.id AND ml.status = 'active') AS total_listings
            FROM users u
            LEFT JOIN schools s ON s.id = u.school
            LEFT JOIN marketplace_seller_verifications v ON v.user_id = u.id
            LEFT JOIN marketplace_reviews r ON r.seller_id = u.id
            WHERE u.id = $user_id
            LIMIT 1
        ");
        if (!$q || mysqli_num_rows($q) === 0) return null;
        $row = mysqli_fetch_assoc($q);

        return [
            'id'            => (int)$row['id'],
            'first_name'    => $row['first_name'],
            'last_name'     => $row['last_name'],
            'profile_pic'   => $row['profile_pic'],
            'school_name'   => $row['school_name'],
            'level'         => ($row['verify_status'] === 'approved') ? 2 : 1,
            'avg_rating'    => $row['avg_rating'] !== null ? round((float)$row['avg_rating'], 1) : null,
            'total_reviews' => (int)$row['total_reviews'],
            'total_listings'=> (int)$row['total_listings'],
        ];
    }
}

// Format a marketplace_listings DB row into the API Listing shape.
if (!function_exists('marketplaceFormatListing')) {
    function marketplaceFormatListing($conn, $row) {
        return [
            'id'             => (int)$row['id'],
            'title'          => $row['title'],
            'description'    => $row['description'],
            'category'       => $row['category'],
            'product_type'   => $row['product_type'],
            'price'          => (float)$row['price'],
            'original_price' => $row['original_price'] !== null ? (float)$row['original_price'] : null,
            'condition'      => $row['condition'],
            'status'         => $row['status'],
            'seller_id'      => (int)$row['seller_id'],
            'seller'         => marketplaceGetSellerProfile($conn, (int)$row['seller_id']),
            'school_id'      => (int)$row['school_id'],
            'course_code'    => $row['course_code'],
            'stock'          => $row['stock'] !== null ? (int)$row['stock'] : null,
            'is_free'        => (bool)$row['is_free'],
            'is_moving_out'  => (bool)$row['is_moving_out'],
            'flash_until'    => $row['flash_until'],
            'images'         => marketplaceParseImages($row['images']),
            'created_at'     => $row['created_at'],
        ];
    }
}

// Format a marketplace_orders DB row into the API Order shape.
if (!function_exists('marketplaceFormatOrder')) {
    function marketplaceFormatOrder($conn, $row) {
        $timeline = [];
        if (!empty($row['timeline'])) {
            $tl = json_decode($row['timeline'], true);
            if (is_array($tl)) $timeline = $tl;
        }
        return [
            'id'            => (int)$row['id'],
            'listing_id'    => (int)$row['listing_id'],
            'listing_title' => $row['listing_title'],
            'product_type'  => $row['product_type'],
            'buyer_id'      => (int)$row['buyer_id'],
            'seller_id'     => (int)$row['seller_id'],
            'amount'        => (float)$row['amount'],
            'status'        => $row['status'],
            'escrow_locked' => (bool)$row['escrow_locked'],
            'delivered_at'  => $row['delivered_at'],
            'completed_at'  => $row['completed_at'],
            'timeline'      => $timeline,
            'created_at'    => $row['created_at'],
        ];
    }
}

// Append an event to an order's JSON timeline and update the row.
if (!function_exists('marketplaceAddOrderTimeline')) {
    function marketplaceAddOrderTimeline($conn, $order_id, $status_label, $label) {
        $order_id = (int)$order_id;
        $q = mysqli_query($conn, "SELECT timeline FROM marketplace_orders WHERE id = $order_id LIMIT 1");
        if (!$q || mysqli_num_rows($q) === 0) return;
        $row      = mysqli_fetch_assoc($q);
        $timeline = [];
        if (!empty($row['timeline'])) {
            $tl = json_decode($row['timeline'], true);
            if (is_array($tl)) $timeline = $tl;
        }
        $timeline[] = [
            'status'    => $status_label,
            'label'     => $label,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        $json = mysqli_real_escape_string($conn, json_encode($timeline));
        mysqli_query($conn, "UPDATE marketplace_orders SET timeline = '$json' WHERE id = $order_id");
    }
}

// Release escrow: credit seller wallet, clear escrow_locked flag.
if (!function_exists('marketplaceReleaseEscrow')) {
    function marketplaceReleaseEscrow($conn, $order_id) {
        $order_id = (int)$order_id;
        $order_q  = mysqli_query($conn, "SELECT * FROM marketplace_orders WHERE id = $order_id AND escrow_locked = 1 LIMIT 1");
        if (!$order_q || mysqli_num_rows($order_q) === 0) return; // already released

        $order     = mysqli_fetch_assoc($order_q);
        $seller_id = (int)$order['seller_id'];
        $amount    = (int)round((float)$order['amount']);

        if ($amount <= 0) {
            // Free item: just unlock
            mysqli_query($conn, "UPDATE marketplace_orders SET escrow_locked = 0 WHERE id = $order_id");
            return;
        }

        // Credit seller wallet (use a transaction)
        mysqli_begin_transaction($conn);
        try {
            $wallet_q = mysqli_query($conn, "SELECT id, balance FROM user_wallets WHERE user_id = $seller_id LIMIT 1 FOR UPDATE");
            if (!$wallet_q || mysqli_num_rows($wallet_q) === 0) {
                // Seller has no wallet — store the credit as pending (log + rollback safely)
                error_log("[MARKETPLACE] Seller $seller_id has no wallet. Escrow for order $order_id cannot be released.");
                mysqli_rollback($conn);
                return;
            }

            $wallet_row   = mysqli_fetch_assoc($wallet_q);
            $wallet_id    = (int)$wallet_row['id'];
            $bal_before   = (int)$wallet_row['balance'];
            $bal_after    = $bal_before + $amount;
            $ref          = mysqli_real_escape_string($conn, 'marketplace_sale_' . $order_id);
            $desc         = mysqli_real_escape_string($conn, 'Marketplace sale — order #' . $order_id);

            mysqli_query($conn, "UPDATE user_wallets SET balance = $bal_after, updated_at = NOW() WHERE id = $wallet_id");
            mysqli_query($conn, "INSERT INTO wallet_ledger_entries (wallet_id, entry_type, amount, balance_before, balance_after, status, reference, description)
                                 VALUES ($wallet_id, 'credit', $amount, $bal_before, $bal_after, 'posted', '$ref', '$desc')");

            mysqli_query($conn, "UPDATE marketplace_orders SET escrow_locked = 0 WHERE id = $order_id");

            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            error_log('[MARKETPLACE] escrow release failed for order ' . $order_id . ': ' . $e->getMessage());
        }
    }
}

// Auto-complete any orders that have been in "delivered" status for > 24 hours.
if (!function_exists('marketplaceAutoCompleteDelivered')) {
    function marketplaceAutoCompleteDelivered($conn) {
        $auto_q = mysqli_query($conn, "
            SELECT id FROM marketplace_orders
            WHERE status = 'delivered'
              AND delivered_at IS NOT NULL
              AND delivered_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        while ($row = mysqli_fetch_assoc($auto_q)) {
            $oid = (int)$row['id'];
            marketplaceReleaseEscrow($conn, $oid);
            marketplaceAddOrderTimeline($conn, $oid, 'completed', 'Auto-completed — 24h passed after delivery');
            mysqli_query($conn, "UPDATE marketplace_orders SET status = 'completed', completed_at = NOW() WHERE id = $oid");
        }
    }
}
?>
