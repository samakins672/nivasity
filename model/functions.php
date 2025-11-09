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

function sendCongratulatoryEmail($conn, $user_id, $tx_ref, $cart_, $cart_2, $total_amount) {
    // Fetch user details
    $user_ = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
    $user = mysqli_fetch_array($user_);
    $to = $user['email'];
    $firstName = isset($user['first_name']) ? $user['first_name'] : '';
    $lastName  = isset($user['last_name']) ? $user['last_name'] : '';
    $payerName = trim($firstName . ' ' . $lastName);
    if ($payerName === '') {
        $payerName = 'Customer';
    }

    $subject = "Payment Receipt - Thank You for Your Purchase";

    // Collect items into a single list for rendering
    $items = [];

    // Manuals (materials)
    if (!empty($cart_)) {
        foreach ($cart_ as $manual_id) {
            $manual_query = mysqli_query($conn, "SELECT * FROM manuals WHERE id = $manual_id");
            if ($manual_query && mysqli_num_rows($manual_query) > 0) {
                $manual = mysqli_fetch_array($manual_query);
                $items[] = [
                    'name' => trim(htmlspecialchars($manual['title']) . (isset($manual['course_code']) && $manual['course_code'] !== '' ? ' (' . htmlspecialchars($manual['course_code']) . ')' : '')),
                    'type' => 'Material',
                    'price' => isset($manual['price']) ? (float)$manual['price'] : 0,
                    'meta' => ''
                ];
            }
        }
    }

    // Events
    if (!empty($cart_2)) {
        foreach ($cart_2 as $event_id) {
            $event_query = mysqli_query($conn, "SELECT * FROM events WHERE id = $event_id");
            if ($event_query && mysqli_num_rows($event_query) > 0) {
                $event = mysqli_fetch_array($event_query);

                $metaBits = [];
                if (!empty($event['event_date'])) {
                    $dateStr = date('j M Y', strtotime($event['event_date']));
                    $timeStr = !empty($event['event_time']) ? date('g:i A', strtotime($event['event_time'])) : '';
                    $metaBits[] = trim($dateStr . ($timeStr ? ' &bull; ' . $timeStr : ''));
                }
                if (isset($event['event_type']) && $event['event_type'] == 'school') {
                    $school_query = mysqli_query($conn, "SELECT * FROM schools WHERE id = " . (int)$event['school']);
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

    // Build a cleaner receipt layout
    $currency = '&#8358;'; // Naira (NGN)

    $message = '';
    $message .= '<h2 style="margin:0;color:#7a3b73">Payment Receipt</h2>';
    $message .= '<p style="margin:6px 0 18px">Hello ' . htmlspecialchars($firstName ?: $payerName) . ',<br>Thank you for your purchase!</p>';

    // Summary panel
    $message .= '<div style="background:#f9f4ff;border:1px solid #e8d7f0;border-radius:6px;padding:12px;margin-bottom:16px">'
              . '<div style="margin:4px 0"><strong>Payer Name:</strong> ' . htmlspecialchars($payerName) . '</div>'
              . '<div style="margin:4px 0"><strong>Reference:</strong> #' . htmlspecialchars($tx_ref) . '</div>'
              . '<div style="margin:4px 0"><strong>Total Amount:</strong> ' . $currency . ' ' . number_format((float)$total_amount, 2) . '</div>'
              . '</div>';

    // Items table
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

    // Send email
    sendMail($subject, $message, $to);
}

?>
