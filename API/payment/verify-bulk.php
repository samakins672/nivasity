<?php
// API: Bulk Verify Pending Cart Payments
// Supports both web requests (GET) and CLI execution (cron)

// Detect if running in CLI mode
$isCli = (PHP_SAPI === 'cli');

// Use absolute paths for all includes
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../config/fw.php';

// Initialize log file path
$logFile = __DIR__ . '/verify-bulk-cron.log';

// Function to log messages
function logMessage($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// Method validation (only for web requests)
if (!$isCli) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendApiError('Method not allowed', 405);
    }
}

// No authentication required - this is a public endpoint for payment verification

// Parse parameters based on execution mode
$is_get = false;
$user_id = 0;
$date_from = '';
$date_to = '';
$ref_id = '';
$limit = 0; // Optional limit for CLI
$dry_run = false; // Optional dry run mode for CLI

if ($isCli) {
    // CLI mode - parse command line arguments
    logMessage("Starting CLI bulk verification", $logFile);
    
    // Check for arguments
    if ($argc > 1) {
        // Parse command line options
        for ($i = 1; $i < $argc; $i++) {
            $arg = $argv[$i];
            
            if (strpos($arg, '--limit=') === 0) {
                $limit = (int)substr($arg, 8);
            } elseif (strpos($arg, '--dry-run=') === 0) {
                $dry_run = (substr($arg, 10) === '1' || strtolower(substr($arg, 10)) === 'true');
            } elseif (strpos($arg, '--user_id=') === 0) {
                $user_id = (int)substr($arg, 10);
            } elseif (strpos($arg, '--date_from=') === 0) {
                $date_from = substr($arg, 12);
            } elseif (strpos($arg, '--date_to=') === 0) {
                $date_to = substr($arg, 10);
            } elseif (strpos($arg, '--ref_id=') === 0) {
                $ref_id = substr($arg, 9);
            } elseif (strpos($arg, '?') !== false) {
                // Parse query string format (e.g., "user_id=45&limit=50")
                parse_str($arg, $params);
                $user_id = isset($params['user_id']) ? (int)$params['user_id'] : $user_id;
                $date_from = isset($params['date_from']) ? $params['date_from'] : $date_from;
                $date_to = isset($params['date_to']) ? $params['date_to'] : $date_to;
                $ref_id = isset($params['ref_id']) ? $params['ref_id'] : $ref_id;
                $limit = isset($params['limit']) ? (int)$params['limit'] : $limit;
                $dry_run = isset($params['dry_run']) ? ($params['dry_run'] === '1' || strtolower($params['dry_run']) === 'true') : $dry_run;
            }
        }
    }
    
    // Sanitize CLI inputs
    if ($date_from) $date_from = mysqli_real_escape_string($conn, $date_from);
    if ($date_to) $date_to = mysqli_real_escape_string($conn, $date_to);
    if ($ref_id) $ref_id = mysqli_real_escape_string($conn, $ref_id);
    
    logMessage("CLI params - user_id: $user_id, date_from: $date_from, date_to: $date_to, ref_id: $ref_id, limit: $limit, dry_run: " . ($dry_run ? 'yes' : 'no'), $logFile);
    
} else {
    // Web mode - parse HTTP request
    $is_get = ($_SERVER['REQUEST_METHOD'] === 'GET');
    
    if ($is_get) {
        // GET request - global check with no params (last 24 hours, excluding past 30 minutes)
        $user_id = 0;
        $date_from = '';
        $date_to = '';
        $ref_id = '';
    } else {
        // POST request - use provided parameters
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $date_from = isset($_POST['date_from']) ? sanitizeInput($conn, $_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitizeInput($conn, $_POST['date_to']) : '';
        $ref_id = isset($_POST['ref_id']) ? sanitizeInput($conn, $_POST['ref_id']) : '';
    }
}

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
        // No dates provided - check within last 24 hours but exclude last 30 minutes
        $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $where_conditions[] = "created_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
    } elseif ($date_from !== '') {
        // Only from date provided
        $where_conditions[] = "created_at >= '$date_from 00:00:00'";
    } elseif ($date_to !== '') {
        // Only to date provided
        $where_conditions[] = "created_at <= '$date_to 23:59:59'";
    }
}

$where_sql = implode(' AND ', $where_conditions);

// Add limit if specified (CLI only)
$limit_sql = '';
if ($isCli && $limit > 0) {
    $limit_sql = " LIMIT $limit";
}

// Get unique ref_ids from cart table
$query_sql = "SELECT DISTINCT ref_id, gateway, user_id FROM cart WHERE $where_sql ORDER BY created_at DESC" . $limit_sql;
$cart_query = mysqli_query($conn, $query_sql);

if (!$cart_query) {
    $error_msg = 'Database query error: ' . mysqli_error($conn);
    if ($isCli) {
        logMessage("ERROR: $error_msg", $logFile);
        echo "ERROR: $error_msg\n";
        exit(1);
    } else {
        sendApiError($error_msg, 500);
    }
}

$total_refs = mysqli_num_rows($cart_query);
$results = [];
$verified_count = 0;
$failed_count = 0;
$already_processed_count = 0;

if ($isCli) {
    echo "Found $total_refs pending cart reference(s) to verify\n";
    if ($dry_run) {
        echo "DRY RUN MODE - No changes will be made\n";
    }
    echo str_repeat('-', 60) . "\n";
}

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
    
    if ($isCli) {
        echo "Processing ref_id: $current_ref (User: $cart_user_id, Gateway: $cart_gateway)...\n";
    }
    
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
        if (!$dry_run) {
            mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$current_ref'");
        }
        $result['status'] = 'already_processed';
        $result['message'] = 'Already processed';
        $already_processed_count++;
        $results[] = $result;
        
        if ($isCli) {
            echo "  -> Already processed\n";
        }
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
        
        if ($isCli) {
            echo "  -> ERROR: Gateway configuration error\n";
            logMessage("ERROR for $current_ref: Gateway configuration error", $logFile);
        }
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
        
        if ($isCli) {
            echo "  -> ERROR: Verification failed: " . $e->getMessage() . "\n";
            logMessage("ERROR for $current_ref: " . $e->getMessage(), $logFile);
        }
        continue;
    }
    
    // Check if verification was successful
    if (!$verificationResult || !isset($verificationResult['status']) || $verificationResult['status'] !== true) {
        $result['status'] = 'not_found';
        $result['message'] = 'No successful payment found';
        $failed_count++;
        $results[] = $result;
        
        if ($isCli) {
            echo "  -> No successful payment found\n";
        }
        continue;
    }
    
    // Fetch cart items for this ref_id
    $cart_items_query = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$current_ref'");
    if (!$cart_items_query || mysqli_num_rows($cart_items_query) < 1) {
        $result['status'] = 'error';
        $result['message'] = 'Cart data not found';
        $failed_count++;
        $results[] = $result;
        
        if ($isCli) {
            echo "  -> ERROR: Cart data not found\n";
            logMessage("ERROR for $current_ref: Cart data not found", $logFile);
        }
        continue;
    }
    
    // Get user school_id
    $user_query = mysqli_query($conn, "SELECT school FROM users WHERE id = $cart_user_id LIMIT 1");
    if (!$user_query || mysqli_num_rows($user_query) === 0) {
        $result['status'] = 'error';
        $result['message'] = 'User not found';
        $failed_count++;
        $results[] = $result;
        
        if ($isCli) {
            echo "  -> ERROR: User not found\n";
            logMessage("ERROR for $current_ref: User not found", $logFile);
        }
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
                    if (!$dry_run) {
                        $insert = mysqli_query($conn, "INSERT INTO manuals_bought (manual_id, price, seller, buyer, ref_id, status, school_id) 
                                                        VALUES ($item_id, $price, $seller, $cart_user_id, '$current_ref', '$status', $school_id)");
                        if ($insert) {
                            $items_processed++;
                        }
                    } else {
                        $items_processed++; // Count for dry run
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
                    if (!$dry_run) {
                        $insert = mysqli_query($conn, "INSERT INTO event_tickets (event_id, price, seller, buyer, ref_id, status) 
                                                        VALUES ($item_id, $price, $seller, $cart_user_id, '$current_ref', '$status')");
                        if ($insert) {
                            $items_processed++;
                        }
                    } else {
                        $items_processed++; // Count for dry run
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
        
        if ($isCli) {
            echo "  -> ERROR: No items could be processed\n";
            logMessage("ERROR for $current_ref: No items could be processed", $logFile);
        }
        continue;
    }
    
    // Calculate charges
    $calc = calculateGatewayCharges($sum_amount, strtolower($cart_gateway));
    $total_amount = round((float)$calc['total_amount']);
    $charge = round((float)$calc['charge']);
    $profit = round((float)$calc['profit']);
    
    // Record transaction
    $medium = mysqli_real_escape_string($conn, strtoupper($cart_gateway));
    
    if (!$dry_run) {
        $tx_insert = mysqli_query($conn, "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, status, medium) 
                                          VALUES ('$current_ref', $cart_user_id, $total_amount, $charge, $profit, '$status', '$medium')");
        
        if (!$tx_insert) {
            $result['status'] = 'error';
            $result['message'] = 'Failed to record transaction';
            $failed_count++;
            $results[] = $result;
            
            if ($isCli) {
                echo "  -> ERROR: Failed to record transaction\n";
                logMessage("ERROR for $current_ref: Failed to record transaction", $logFile);
            }
            continue;
        }
        
        // Update cart status
        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$current_ref'");
    }
    
    // Success
    $result['status'] = 'verified';
    $result['message'] = $dry_run ? 'Payment verified (DRY RUN)' : 'Payment verified and processed';
    $result['amount'] = $total_amount;
    $result['items_processed'] = $items_processed;
    $verified_count++;
    $results[] = $result;
    
    if ($isCli) {
        echo "  -> SUCCESS: Verified $items_processed item(s), Amount: $total_amount" . ($dry_run ? " (DRY RUN)" : "") . "\n";
        logMessage("SUCCESS for $current_ref: Verified $items_processed items, amount $total_amount", $logFile);
    }
}

// Prepare summary
$summary = [
    'total_refs_checked' => $total_refs,
    'verified' => $verified_count,
    'already_processed' => $already_processed_count,
    'failed' => $failed_count
];

// Output based on execution mode
if ($isCli) {
    // CLI output
    echo str_repeat('-', 60) . "\n";
    echo "SUMMARY:\n";
    echo "  Total refs checked: {$summary['total_refs_checked']}\n";
    echo "  Verified: {$summary['verified']}\n";
    echo "  Already processed: {$summary['already_processed']}\n";
    echo "  Failed: {$summary['failed']}\n";
    
    logMessage("Bulk verification completed - Total: {$summary['total_refs_checked']}, Verified: {$summary['verified']}, Already: {$summary['already_processed']}, Failed: {$summary['failed']}", $logFile);
    
    // Exit with appropriate code
    if ($summary['failed'] > 0 && $summary['verified'] === 0) {
        echo "\nExiting with failure code (all verifications failed)\n";
        exit(1);
    } else {
        echo "\nExiting with success code\n";
        exit(0);
    }
} else {
    // Web output - JSON response
    sendApiSuccess([
        'summary' => $summary,
        'results' => $results
    ], 'Bulk verification completed');
}
