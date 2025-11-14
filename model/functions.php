<?php

if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headerName = str_replace('_', ' ', substr($name, 5));
                $headerName = str_replace(' ', '-', ucwords(strtolower($headerName)));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }
}

function generateVerificationCode($length) {
    // Generate a random verification code of the specified length
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

function isCodeUnique($code, $conn, $db_table) {
    // Check if the code already exists in the table
    $query = "SELECT COUNT(*) as count FROM $db_table WHERE code = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['count'];
    $stmt->close();

    return $count == 0; // If count is 0, the code is unique
}

function buildReceiptHtmlFromRef($conn, $user_id, $tx_ref, $filterType = null, $filterId = null) {
    // Fetch user details
    $user_q = mysqli_query($conn, "SELECT * FROM users WHERE id = " . (int)$user_id);
    $user = $user_q ? mysqli_fetch_array($user_q) : null;
    $firstName = $user && isset($user['first_name']) ? $user['first_name'] : '';
    $lastName  = $user && isset($user['last_name']) ? $user['last_name'] : '';
    $payerName = trim(($firstName ?: '') . ' ' . ($lastName ?: ''));
    if ($payerName === '') { $payerName = 'Customer'; }

    // Resolve total amount: by default from transactions; when filtering a single item, compute from item price only
    $total_amount = 0.0;
    $tx_safe = mysqli_real_escape_string($conn, $tx_ref);
    $filtered = ($filterType !== null && $filterId !== null);
    if (!$filtered) {
        $tx_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT amount FROM transactions WHERE ref_id = '$tx_safe' AND user_id = " . (int)$user_id . " LIMIT 1"));
        if ($tx_row && isset($tx_row['amount'])) {
            $total_amount = (float)$tx_row['amount'];
        }
    }

    // Collect items from persisted purchases (manuals_bought, event_tickets)
    $items = [];

    // Manuals purchased under this reference
    // Manuals
    if (!$filtered || strtolower($filterType) === 'manual') {
        $manual_where = "mb.buyer = " . (int)$user_id . " AND mb.ref_id = '$tx_safe'";
        if ($filtered && strtolower($filterType) === 'manual') {
            $manual_where .= " AND mb.manual_id = " . (int)$filterId;
        }
        $mb_rs = mysqli_query($conn, "SELECT mb.manual_id, mb.price, m.title, m.course_code FROM manuals_bought mb JOIN manuals m ON m.id = mb.manual_id WHERE $manual_where");
        if ($mb_rs && mysqli_num_rows($mb_rs) > 0) {
            while ($row = mysqli_fetch_assoc($mb_rs)) {
                $items[] = [
                    'name' => trim(htmlspecialchars($row['title']) . (isset($row['course_code']) && $row['course_code'] !== '' ? ' (' . htmlspecialchars($row['course_code']) . ')' : '')),
                    'type' => 'Material',
                    'price' => isset($row['price']) ? (float)$row['price'] : 0,
                    'meta' => ''
                ];
            }
        }
    }

    // Events
    if (!$filtered || strtolower($filterType) === 'event') {
        $event_where = "et.buyer = " . (int)$user_id . " AND et.ref_id = '$tx_safe'";
        if ($filtered && strtolower($filterType) === 'event') {
            $event_where .= " AND et.event_id = " . (int)$filterId;
        }
        $et_rs = mysqli_query($conn, "SELECT et.event_id, et.price, e.* FROM event_tickets et JOIN events e ON e.id = et.event_id WHERE $event_where");
        if ($et_rs && mysqli_num_rows($et_rs) > 0) {
            while ($event = mysqli_fetch_assoc($et_rs)) {
                $metaBits = [];
                if (!empty($event['event_date'])) {
                    $dateStr = date('j M Y', strtotime($event['event_date']));
                    $timeStr = !empty($event['event_time']) ? date('g:i A', strtotime($event['event_time'])) : '';
                    $metaBits[] = trim($dateStr . ($timeStr ? ' &bull; ' . $timeStr : ''));
                }
                if (isset($event['event_type']) && $event['event_type'] == 'school') {
                    $school_query = mysqli_query($conn, "SELECT name FROM schools WHERE id = " . (int)$event['school']);
                    if ($school_query && mysqli_num_rows($school_query) > 0) {
                        $school_name = mysqli_fetch_array($school_query)['name'];
                        $metaBits[] = 'School: ' . htmlspecialchars($school_name);
                    }
                } elseif (isset($event['event_type']) && $event['event_type'] == 'online') {
                    if (!empty($event['event_link'])) {
                        $metaBits[] = 'Link: ' . htmlspecialchars($event['event_link']);
                    }
                } else {
                    if (!empty($event['location'])) {
                        $metaBits[] = 'Location: ' . htmlspecialchars($event['location']);
                    }
                }
                $items[] = [
                    'name' => htmlspecialchars($event['title']),
                    'type' => 'Event',
                    'price' => isset($event['price']) ? (float)$event['price'] : 0,
                    'meta' => implode(' &bull; ', $metaBits)
                ];
            }
        }
    }

    // If transaction row not found, compute a fallback total using settlement helper
    if ($filtered) {
        // For single-item receipts, total equals the sum of selected items only (no shared fees)
        $base = 0.0;
        foreach ($items as $it) { $base += (float)$it['price']; }
        $total_amount = $base;
    } elseif ($total_amount <= 0) {
        $base = 0.0;
        foreach ($items as $it) { $base += (float)$it['price']; }
        if (function_exists('calculateFlutterwaveSettlement')) {
            $calc = calculateFlutterwaveSettlement($base);
            $total_amount = $calc['total_amount'];
        } else {
            $total_amount = $base; // last resort
        }
    }

    // Build receipt HTML (same visual style as original)
    $currency = '&#8358;';
    $message = '';
    $message .= '<h2 style="margin:0;color:#7a3b73">Payment Receipt</h2>';
    $message .= '<p style="margin:6px 0 18px">Hello ' . htmlspecialchars($firstName ?: $payerName) . ',<br>Thank you for your purchase!</p>';

    $message .= '<div style="background:#f9f4ff;border:1px solid #e8d7f0;border-radius:6px;padding:12px;margin-bottom:16px">'
              . '<div style="margin:4px 0"><strong>Payer Name:</strong> ' . htmlspecialchars($payerName) . '</div>'
              . '<div style="margin:4px 0"><strong>Reference:</strong> #' . htmlspecialchars($tx_ref) . '</div>'
              . '<div style="margin:4px 0"><strong>Total Amount:</strong> ' . $currency . ' ' . number_format((float)$total_amount, 2) . '</div>'
              . '</div>';

    if (!empty($items)) {
        $message .= '<h3 style="margin:12px 0 8px;color:#7a3b73">Items Purchased</h3>';
        $message .= '<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse">';
        $message .= '<thead>'
                  . '<tr>'
                  . '<th align="left" style="border-bottom:1px solid #eee;color:#555">Item</th>'
                  . '<th align="left" style="border-bottom:1px solid #eee;color:#555">Type</th>'
                  . '<th align="right" style="border-bottom:1px solid #eee;color:#555">Price</th>'
                  . '</tr>'
                  . '</thead>';
        $message .= '<tbody>';
        foreach ($items as $it) {
            $nameCell = $it['name'];
            if (!empty($it['meta'])) {
                $nameCell .= '<div style="color:#777;font-size:13px;margin-top:2px">' . $it['meta'] . '</div>';
            }
            $message .= '<tr>'
                      . '<td style="border-bottom:1px solid #f2f2f2;padding:8px 0">' . $nameCell . '</td>'
                      . '<td style="border-bottom:1px solid #f2f2f2;padding:8px 0">' . htmlspecialchars($it['type']) . '</td>'
                      . '<td align="right" style="border-bottom:1px solid #f2f2f2;padding:8px 0">' . $currency . ' ' . number_format((float)$it['price'], 2) . '</td>'
                      . '</tr>';
        }
        $message .= '</tbody>';
        $message .= '</table>';
    }

    $message .= '<p style="margin-top:18px">We hope you enjoy your purchase!<br><br>Best regards,<br><b>Nivasity Team</b></p>';
    return $message;
}

function sendCongratulatoryEmail($conn, $user_id, $tx_ref, $cart_, $cart_2, $total_amount) {
    // Build the receipt using persisted data to keep format consistent
    $message = buildReceiptHtmlFromRef($conn, $user_id, $tx_ref);

    // Resolve recipient email
    $user_q = mysqli_query($conn, "SELECT email FROM users WHERE id = " . (int)$user_id);
    $to = ($user_q && mysqli_num_rows($user_q) > 0) ? mysqli_fetch_array($user_q)['email'] : '';
    if (!$to) { return; }

    $subject = "Payment Receipt - Thank You for Your Purchase";
    sendMail($subject, $message, $to);
}

/**
 * Calculate Flutterwave charge, total and profit given a base amount.
 * Mirrors logic used in handle-fw-payment.php to keep results consistent.
 */
function calculateFlutterwaveSettlement($baseAmount) {
    $baseAmount = (float)$baseAmount;
    $charge = 0.0;
    if ($baseAmount <= 0) {
        $charge = 0.0;
    } elseif ($baseAmount < 2500) {
        // Flat fee for transactions less than 2,500
        $charge = 70.0;
    } else {
        // Percentage + tiered additions
        $charge += ($baseAmount * 0.02);
        if ($baseAmount >= 2500 && $baseAmount < 5000) {
            $charge += 20.0;
        } elseif ($baseAmount >= 5000 && $baseAmount < 10000) {
            $charge += 30.0;
        } else {
            $charge += 50.0;
        }
    }

    $total = $baseAmount + $charge;
    $flutterwave_fee = round($total * 0.02, 2);
    $profit = round(max($charge - $flutterwave_fee, 0), 2);

    return [
        'total_amount' => $total,
        'charge' => $charge,
        'profit' => $profit,
        'flutterwave_fee' => $flutterwave_fee,
    ];
}

?>
