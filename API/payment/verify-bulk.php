<?php
// API: Bulk Verify Pending Cart Payments
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../config/fw.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendApiError('Method not allowed', 405);
}

// Authenticate user (admin/staff should be able to verify all, but we'll still validate)
$auth_user = authenticateApiRequest($conn);

// Get parameters
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$date_from = isset($_POST['date_from']) ? sanitizeInput($conn, $_POST['date_from']) : '';
$date_to = isset($_POST['date_to']) ? sanitizeInput($conn, $_POST['date_to']) : '';
$ref_id = isset($_POST['ref_id']) ? sanitizeInput($conn, $_POST['ref_id']) : '';

// Build WHERE conditions
$where_conditions = ["status = 'pending'"];

// If ref_id is passed, only check that specific reference
if ($ref_id !== '') {
    $where_conditions[] = "ref_id = '$ref_id'";
} else {
    // If no ref_id, apply other filters
    
    // User filter
    if ($user_id > 0) {
        $where_conditions[] = "user_id = $user_id";
    }
    
    // Date range filter
    if ($date_from !== '' && $date_to !== '') {
        // Both dates provided
        $where_conditions[] = "created_at >= '$date_from 00:00:00'";
        $where_conditions[] = "created_at <= '$date_to 23:59:59'";
    } elseif ($date_from === '' && $date_to === '') {
        // No dates provided - check within last 24 hours
        $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    } elseif ($date_from !== '') {
        // Only from date provided
        $where_conditions[] = "created_at >= '$date_from 00:00:00'";
    } elseif ($date_to !== '') {
        // Only to date provided
        $where_conditions[] = "created_at <= '$date_to 23:59:59'";
    }
}

$where_sql = implode(' AND ', $where_conditions);

// Get unique ref_ids from cart table
$cart_query = mysqli_query($conn, "SELECT DISTINCT ref_id, gateway, user_id FROM cart WHERE $where_sql ORDER BY created_at DESC");

if (!$cart_query) {
    sendApiError('Database query error: ' . mysqli_error($conn), 500);
}

$total_refs = mysqli_num_rows($cart_query);
$results = [];
$verified_count = 0;
$failed_count = 0;
$already_processed_count = 0;

// Loop through each unique ref_id and verify
while ($cart_row = mysqli_fetch_assoc($cart_query)) {
    $current_ref = $cart_row['ref_id'];
    $cart_gateway = $cart_row['gateway'] ?? 'FLUTTERWAVE';
    $cart_user_id = (int)$cart_row['user_id'];
    
    $result = [
        'ref_id' => $current_ref,
        'user_id' => $cart_user_id,
        'gateway' => $cart_gateway,
        'status' => 'pending',
        'message' => ''
    ];
    
    // Check if already processed (duplicate protection)
    $dupe = false;
    $check_tx = mysqli_query($conn, "SELECT 1 FROM transactions WHERE ref_id = '$current_ref' LIMIT 1");
    if ($check_tx && mysqli_num_rows($check_tx) > 0) {
        $dupe = true;
    }
    if (!$dupe) {
        $check_mb = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$current_ref' LIMIT 1");
        if ($check_mb && mysqli_num_rows($check_mb) > 0) {
            $dupe = true;
        }
    }
    if (!$dupe) {
        $check_et = mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$current_ref' LIMIT 1");
        if ($check_et && mysqli_num_rows($check_et) > 0) {
            $dupe = true;
        }
    }
    
    if ($dupe) {
        // Already processed - mark as confirmed
        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$current_ref'");
        $result['status'] = 'already_processed';
        $result['message'] = 'Already processed';
        $already_processed_count++;
        $results[] = $result;
        continue;
    }
    
    // Get gateway instance
    $gateway = null;
    $gateway_name = strtolower($cart_gateway);
    try {
        $gateway = PaymentGatewayFactory::getGateway($gateway_name);
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['message'] = 'Gateway configuration error';
        $failed_count++;
        $results[] = $result;
        continue;
    }
    
    // Verify transaction with gateway
    $verificationResult = null;
    try {
        $verificationResult = $gateway->verifyTransaction($current_ref);
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['message'] = 'Verification failed: ' . $e->getMessage();
        $failed_count++;
        $results[] = $result;
        continue;
    }
    
    // Check if verification was successful
    if (!$verificationResult || !isset($verificationResult['status']) || $verificationResult['status'] !== true) {
        $result['status'] = 'not_found';
        $result['message'] = 'No successful payment found';
        $failed_count++;
        $results[] = $result;
        continue;
    }
    
    // Fetch cart items for this ref_id
    $cart_items_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$current_ref'");
    if (!$cart_items_query || mysqli_num_rows($cart_items_query) < 1) {
        $result['status'] = 'error';
        $result['message'] = 'Cart data not found';
        $failed_count++;
        $results[] = $result;
        continue;
    }
    
    // Get user school_id
    $user_query = mysqli_query($conn, "SELECT school FROM users WHERE id = $cart_user_id LIMIT 1");
    if (!$user_query || mysqli_num_rows($user_query) === 0) {
        $result['status'] = 'error';
        $result['message'] = 'User not found';
        $failed_count++;
        $results[] = $result;
        continue;
    }
    $user_data = mysqli_fetch_assoc($user_query);
    $school_id = (int)$user_data['school'];
    
    // Process each cart item
    $sum_amount = 0.0;
    $status = 'successful';
    $items_processed = 0;
    
    while ($item_row = mysqli_fetch_assoc($cart_items_query)) {
        $item_id = (int)$item_row['item_id'];
        $type = $item_row['type'];
        
        if ($type === 'manual') {
            // Process manual purchase
            $manual_query = mysqli_query($conn, "SELECT price, user_id FROM manuals WHERE id = $item_id AND school_id = $school_id");
            if ($manual_query && mysqli_num_rows($manual_query) > 0) {
                $manual_data = mysqli_fetch_assoc($manual_query);
                $price = (float)$manual_data['price'];
                $seller = (int)$manual_data['user_id'];
                $sum_amount += $price;
                
                // Check for duplicate
                $exists = mysqli_query($conn, "SELECT 1 FROM manuals_bought WHERE ref_id = '$current_ref' AND manual_id = $item_id LIMIT 1");
                if (mysqli_num_rows($exists) === 0) {
                    $insert = mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) 
                                                    VALUES ($item_id, $price, $seller, $cart_user_id, '$current_ref', '$status', $school_id)");
                    if ($insert) {
                        $items_processed++;
                    }
                }
            }
        } elseif ($type === 'event') {
            // Process event ticket purchase
            $event_query = mysqli_query($conn, "SELECT price, user_id FROM events WHERE id = $item_id");
            if ($event_query && mysqli_num_rows($event_query) > 0) {
                $event_data = mysqli_fetch_assoc($event_query);
                $price = (float)$event_data['price'];
                $seller = (int)$event_data['user_id'];
                $sum_amount += $price;
                
                // Check for duplicate
                $exists = mysqli_query($conn, "SELECT 1 FROM event_tickets WHERE ref_id = '$current_ref' AND event_id = $item_id LIMIT 1");
                if (mysqli_num_rows($exists) === 0) {
                    $insert = mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) 
                                                    VALUES ($item_id, $price, $seller, $cart_user_id, '$current_ref', '$status')");
                    if ($insert) {
                        $items_processed++;
                    }
                }
            }
        }
    }
    
    if ($items_processed === 0) {
        $result['status'] = 'error';
        $result['message'] = 'No items could be processed';
        $failed_count++;
        $results[] = $result;
        continue;
    }
    
    // Calculate charges
    $calc = calculateGatewayCharges($sum_amount, strtolower($cart_gateway));
    $total_amount = round((float)$calc['total_amount']);
    $charge = round((float)$calc['charge']);
    $profit = round((float)$calc['profit']);
    
    // Record transaction
    $medium = mysqli_real_escape_string($conn, strtoupper($cart_gateway));
    $tx_insert = mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) 
                                      VALUES ('$current_ref', $cart_user_id, $total_amount, $charge, $profit, '$status', '$medium')");
    
    if (!$tx_insert) {
        $result['status'] = 'error';
        $result['message'] = 'Failed to record transaction';
        $failed_count++;
        $results[] = $result;
        continue;
    }
    
    // Update cart status
    mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$current_ref'");
    
    // Success
    $result['status'] = 'verified';
    $result['message'] = 'Payment verified and processed';
    $result['amount'] = $total_amount;
    $result['items_processed'] = $items_processed;
    $verified_count++;
    $results[] = $result;
}

// Send response
sendApiSuccess([
    'summary' => [
        'total_refs_checked' => $total_refs,
        'verified' => $verified_count,
        'already_processed' => $already_processed_count,
        'failed' => $failed_count
    ],
    'results' => $results
], 'Bulk verification completed');
