<?php
// API: Bulk Verify Pending Cart Payments
// Supports both web requests (GET) and CLI execution (cron)

// Detect if running in CLI mode
$isCli = (PHP_SAPI === 'cli');

// Use absolute paths for all includes
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../model/PaymentGatewayFactory.php';
require_once __DIR__ . '/../../config/fw.php';
require_once __DIR__ . '/../../model/mail.php';
require_once __DIR__ . '/../../model/refund_engine.php';

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
    
} else {
    // Web mode - parse HTTP request
    $is_get = ($_SERVER['REQUEST_METHOD'] === 'GET');
    
    if ($is_get) {
        // GET request - global check with no params (last 24 hours, excluding past 2 minutes)
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
        // No dates provided - check within last 24 hours but exclude last 2 minutes
        $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $where_conditions[] = "created_at <= DATE_SUB(NOW(), INTERVAL 2 MINUTE)";
    } elseif ($date_from !== '') {
        // Only from date provided
        $where_conditions[] = "created_at >= '$date_from 00:00:00'";
    } elseif ($date_to !== '') {
        // Only to date provided
        $where_conditions[] = "created_at <= '$date_to 23:59:59'";
    }
}

$where_sql = implode(' AND ', $where_conditions);

// Housekeeping for stale reserved rows.
if (!$dry_run) {
releaseExpiredReservations($conn, 60);
}

// Add limit if specified (CLI only)
$limit_sql = '';
if ($isCli && $limit > 0) {
    $limit_sql = " LIMIT $limit";
}

// Get unique ref_ids from cart table
$query_sql = "SELECT ref_id, MAX(gateway) AS gateway, MAX(user_id) AS user_id, MIN(created_at) AS first_created_at
              FROM cart
              WHERE $where_sql
              GROUP BY ref_id
              ORDER BY first_created_at DESC" . $limit_sql;
$cart_query = mysqli_query($conn, $query_sql);

if (!$cart_query) {
    $error_msg = 'Database query error: ' . mysqli_error($conn);
    if ($isCli) {
        $failed_summary = [
            'total_refs_checked' => 0,
            'verified' => 0,
            'already_processed' => 0,
            'failed' => 1,
            'failed_not_found' => 0,
            'failed_errors' => 1,
            'dry_run' => $dry_run ? 1 : 0,
            'error' => $error_msg
        ];
        logMessage('SUMMARY ' . json_encode($failed_summary, JSON_UNESCAPED_SLASHES), $logFile);
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
$not_found_count = 0;
$error_count = 0;

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
    $first_created_at = isset($cart_row['first_created_at']) ? $cart_row['first_created_at'] : null;
    
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
    $mb_count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM manuals_bought WHERE ref_id = '$current_ref' AND buyer = $cart_user_id"));
    $et_count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM event_tickets WHERE ref_id = '$current_ref' AND buyer = $cart_user_id"));
    $delivery_count = (int)($mb_count_row['c'] ?? 0) + (int)($et_count_row['c'] ?? 0);
    $cart_count_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM cart WHERE ref_id = '$current_ref' AND user_id = $cart_user_id"));
    $cart_count = (int)($cart_count_row['c'] ?? 0);
    $dupe = ($delivery_count > 0) && ($cart_count <= 0 || $delivery_count >= $cart_count);
    
    if ($dupe) {
        // Already processed - mark as confirmed
        if (!$dry_run) {
            mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$current_ref'");
            $result['refund_applied'] = (int)consumeReservationsForSettledTx($conn, $current_ref);

            // Re-trigger congratulatory email for already-processed refs to avoid missed receipts
            $manual_ids = array();
            $event_ids = array();
            $cart_items_for_email = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$current_ref' AND user_id = $cart_user_id");
            while ($cart_item = mysqli_fetch_assoc($cart_items_for_email)) {
                if ($cart_item['type'] === 'manual') {
                    $manual_ids[] = $cart_item['item_id'];
                } elseif ($cart_item['type'] === 'event') {
                    $event_ids[] = $cart_item['item_id'];
                }
            }

            sendCongratulatoryEmail($conn, $cart_user_id, $current_ref, $manual_ids, $event_ids, 0);
        }
        $result['status'] = 'already_processed';
        $result['reason'] = 'already_processed';
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
        $result['reason'] = 'gateway_configuration_error';
        $result['message'] = 'Gateway configuration error: ' . $e->getMessage();
        $failed_count++;
        $error_count++;
        $results[] = $result;
        
        if ($isCli) {
            echo "  -> ERROR: Gateway configuration error\n";
        }
        continue;
    }
    
    // Verify transaction with gateway
    $verificationResult = null;
    try {
        $verificationResult = $gateway->verifyTransaction($current_ref);
    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['reason'] = 'verification_exception';
        $result['message'] = 'Verification failed: ' . $e->getMessage();
        $failed_count++;
        $error_count++;
        $results[] = $result;
        
        if ($isCli) {
            echo "  -> ERROR: Verification failed: " . $e->getMessage() . "\n";
        }
        continue;
    }
    
    // Check if verification was successful
    if (!$verificationResult || !isset($verificationResult['status']) || $verificationResult['status'] !== true) {
        $result['status'] = 'not_found';
        $result['reason'] = 'no_successful_payment_found';
        $result['message'] = isset($verificationResult['message']) ? $verificationResult['message'] : 'No successful payment found';

        // Release reservation when ref stays unresolved beyond TTL.
        if (!$dry_run && !empty($first_created_at)) {
            $createdTs = strtotime($first_created_at);
            if ($createdTs !== false && (time() - $createdTs) > (60 * 60)) {
                releaseReservationsForTx($conn, $current_ref, 'verification_timeout');
            }
        }

        $failed_count++;
        $not_found_count++;
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
        $result['reason'] = 'cart_not_found';
        $result['message'] = 'Cart data not found';
        $failed_count++;
        $error_count++;
        $results[] = $result;
        
        if ($isCli) {
            echo "  -> ERROR: Cart data not found\n";
        }
        continue;
    }
    
    // Get user school_id
    $user_query = mysqli_query($conn, "SELECT school FROM users WHERE id = $cart_user_id LIMIT 1");
    if (!$user_query || mysqli_num_rows($user_query) === 0) {
        $result['status'] = 'error';
        $result['reason'] = 'user_not_found';
        $result['message'] = 'User not found';
        $failed_count++;
        $error_count++;
        $results[] = $result;
        
        if ($isCli) {
            echo "  -> ERROR: User not found\n";
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
        $result['reason'] = 'no_items_processed';
        $result['message'] = 'No items could be processed';
        $failed_count++;
        $error_count++;
        $results[] = $result;
        
        if ($isCli) {
            echo "  -> ERROR: No items could be processed\n";
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
    
    $refund_applied = 0;
    if (!$dry_run) {
        try {
            $refund_applied = withTxProcessingLock($conn, $current_ref, function() use ($conn, $current_ref, $cart_user_id, $total_amount, $charge, $profit, $status, $medium) {
                $alreadyTx = mysqli_query($conn, "SELECT id FROM transactions WHERE ref_id = '$current_ref' ORDER BY id DESC LIMIT 1");
                if ($alreadyTx && mysqli_num_rows($alreadyTx) > 0) {
                    $refund = consumeReservationsForSettledTx($conn, $current_ref);
                    $updTxSql = "UPDATE transactions SET amount = $total_amount, charge = $charge, profit = $profit, refund = $refund, status = '$status', medium = '$medium' WHERE ref_id = '$current_ref'";
                    mysqli_query($conn, $updTxSql);
                    return (int)$refund;
                }
                $refund = 0;
                mysqli_begin_transaction($conn);
                try {
                    $refund = consumeReservationsCore($conn, $current_ref);
                    $insertTxSql = "INSERT INTO transactions (ref_id, user_id, amount, charge, profit, refund, status, medium)
                                    VALUES ('$current_ref', $cart_user_id, $total_amount, $charge, $profit, $refund, '$status', '$medium')";
                    if (!mysqli_query($conn, $insertTxSql)) {
                        throw new Exception('Failed to record transaction: ' . mysqli_error($conn));
                    }
                    mysqli_commit($conn);
                } catch (Throwable $e) {
                    mysqli_rollback($conn);
                    throw $e;
                }
                return (int)$refund;
            });
        } catch (Exception $e) {
            $refund_applied = 0;
        }

        $tx_insert = mysqli_query($conn, "SELECT id FROM transactions WHERE ref_id = '$current_ref' LIMIT 1");
        
        if (!$tx_insert || mysqli_num_rows($tx_insert) < 1) {
            $result['status'] = 'error';
            $result['reason'] = 'transaction_record_failed';
            $result['message'] = 'Failed to record transaction: ' . mysqli_error($conn);
            $failed_count++;
            $error_count++;
            $results[] = $result;
            
            if ($isCli) {
                echo "  -> ERROR: Failed to record transaction\n";
            }
            continue;
        }
        
        // Update cart status
        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$current_ref'");

        // Send congratulatory email with receipt after successful processing
        $manual_ids = array();
        $event_ids = array();
        $cart_items_for_email = mysqli_query($conn, "SELECT * FROM cart WHERE ref_id = '$current_ref' AND user_id = $cart_user_id");
        while ($cart_item = mysqli_fetch_assoc($cart_items_for_email)) {
            if ($cart_item['type'] === 'manual') {
                $manual_ids[] = $cart_item['item_id'];
            } elseif ($cart_item['type'] === 'event') {
                $event_ids[] = $cart_item['item_id'];
            }
        }

        sendCongratulatoryEmail($conn, $cart_user_id, $current_ref, $manual_ids, $event_ids, $total_amount);
    }
    
    // Success
    $result['status'] = 'verified';
    $result['reason'] = 'verified';
    $result['message'] = $dry_run ? 'Payment verified (DRY RUN)' : 'Payment verified and processed';
    $result['amount'] = $total_amount;
    $result['refund_applied'] = (int)$refund_applied;
    $result['items_processed'] = $items_processed;
    $verified_count++;
    $results[] = $result;
    
    if ($isCli) {
        echo "  -> SUCCESS: Verified $items_processed item(s), Amount: $total_amount" . ($dry_run ? " (DRY RUN)" : "") . "\n";
    }
}

// Prepare summary
$summary = [
    'total_refs_checked' => $total_refs,
    'verified' => $verified_count,
    'already_processed' => $already_processed_count,
    'failed' => $failed_count,
    'failed_not_found' => $not_found_count,
    'failed_errors' => $error_count
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
    echo "    - No successful payment found: {$summary['failed_not_found']}\n";
    echo "    - Processing/config errors: {$summary['failed_errors']}\n";
    
    $compact_summary = [
        'total_refs_checked' => $summary['total_refs_checked'],
        'verified' => $summary['verified'],
        'already_processed' => $summary['already_processed'],
        'failed' => $summary['failed'],
        'failed_not_found' => $summary['failed_not_found'],
        'failed_errors' => $summary['failed_errors'],
        'dry_run' => $dry_run ? 1 : 0
    ];
    logMessage('SUMMARY ' . json_encode($compact_summary, JSON_UNESCAPED_SLASHES), $logFile);
    
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
