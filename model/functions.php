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

/**
 * Detect whether the current request is from an Android device.
 *
 * @param string|null $userAgent Optional user agent override for testing.
 * @return bool
 */
function isAndroidDevice($userAgent = null) {
    if ($userAgent === null) {
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
    }

    if (stripos($userAgent, 'android') !== false) {
        return true;
    }

    // Support modern User-Agent Client Hints where available.
    if (isset($_SERVER['HTTP_SEC_CH_UA_PLATFORM']) && stripos((string)$_SERVER['HTTP_SEC_CH_UA_PLATFORM'], 'android') !== false) {
        return true;
    }

    return false;
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

function nivasity_get_support_whatsapp_link() {
    global $conn;
    static $resolvedLink = null;

    if ($resolvedLink !== null) {
        return $resolvedLink;
    }

    $fallbackNumber = '2347052645530';
    if (isset($conn) && $conn) {
        $query = "SELECT whatsapp
                  FROM support_contacts
                  WHERE status = 'active'
                  ORDER BY id DESC
                  LIMIT 1";
        $result = mysqli_query($conn, $query);

        if ($result) {
            $contact = mysqli_fetch_assoc($result);
            $dbNumber = $contact['whatsapp'] ?? '';
            $sanitizedNumber = preg_replace('/\D+/', '', (string) $dbNumber);

            if ($sanitizedNumber !== '') {
                $resolvedLink = 'https://wa.me/' . $sanitizedNumber;
                return $resolvedLink;
            }
        }
    }

    $resolvedLink = 'https://wa.me/' . $fallbackNumber;
    return $resolvedLink;
}

function receiptTableExists(mysqli $conn, $tableName) {
    static $cache = [];
    $key = strtolower((string)$tableName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safeTable = mysqli_real_escape_string($conn, (string)$tableName);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '$safeTable'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

function receiptTableHasColumn(mysqli $conn, $tableName, $columnName) {
    static $cache = [];
    $key = strtolower((string)$tableName . '.' . (string)$columnName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $safeTable = mysqli_real_escape_string($conn, (string)$tableName);
    $safeColumn = mysqli_real_escape_string($conn, (string)$columnName);
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    $cache[$key] = ($res && mysqli_num_rows($res) > 0);
    return $cache[$key];
}

function receiptResolveAuditStatusColumn(mysqli $conn) {
    if (receiptTableHasColumn($conn, 'manual_export_audits', 'grant_status')) {
        return 'grant_status';
    }
    if (receiptTableHasColumn($conn, 'manual_export_audits', 'status')) {
        return 'status';
    }

    return '';
}

function receiptGenerateManualExportCode(mysqli $conn, $length = 10) {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $maxIndex = strlen($alphabet) - 1;
    $attempts = 0;

    do {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $maxIndex)];
        }
        $safeCode = mysqli_real_escape_string($conn, $code);
        $exists = mysqli_query($conn, "SELECT 1 FROM manual_export_audits WHERE code = '$safeCode' LIMIT 1");
        $attempts++;
    } while ($exists && mysqli_num_rows($exists) > 0 && $attempts < 10);

    return $code;
}

function createBulkReceiptExportAudit(mysqli $conn, $payerUserId, $batchRefId) {
    $payerUserId = (int)$payerUserId;
    $batchRefId = trim((string)$batchRefId);
    if ($payerUserId <= 0 || $batchRefId === '') {
        return null;
    }

    if (
        !receiptTableExists($conn, 'manual_bulk_payment_batches')
        || !receiptTableExists($conn, 'manual_bulk_payment_students')
        || !receiptTableExists($conn, 'manual_export_audits')
        || !receiptTableExists($conn, 'manuals_bought')
    ) {
        return null;
    }

    $safeRef = mysqli_real_escape_string($conn, $batchRefId);
    $batchRs = mysqli_query(
        $conn,
        "SELECT id, manual_id, student_count
         FROM manual_bulk_payment_batches
         WHERE ref_id = '$safeRef'
           AND payer_user_id = $payerUserId
           AND payment_status = 'successful'
         LIMIT 1"
    );
    if (!$batchRs || mysqli_num_rows($batchRs) < 1) {
        return null;
    }

    $batchRow = mysqli_fetch_assoc($batchRs) ?: [];
    $batchId = (int)($batchRow['id'] ?? 0);
    $manualId = (int)($batchRow['manual_id'] ?? 0);
    if ($batchId <= 0 || $manualId <= 0) {
        return null;
    }

    $studentRs = mysqli_query(
        $conn,
        "SELECT manuals_bought_id
         FROM manual_bulk_payment_students
         WHERE batch_id = $batchId
         ORDER BY id ASC"
    );
    if (!$studentRs) {
        throw new RuntimeException('Failed to load bulk payment students for export audit: ' . mysqli_error($conn));
    }

    $boughtIdsMap = [];
    while ($studentRow = mysqli_fetch_assoc($studentRs)) {
        $manualsBoughtId = (int)($studentRow['manuals_bought_id'] ?? 0);
        if ($manualsBoughtId > 0) {
            $boughtIdsMap[$manualsBoughtId] = true;
        }
    }

    $boughtIds = array_map('intval', array_keys($boughtIdsMap));
    sort($boughtIds);
    if (count($boughtIds) < 1) {
        return null;
    }

    $boughtIdsCsv = implode(',', $boughtIds);
    $summaryRs = mysqli_query(
        $conn,
        "SELECT
            MIN(id) AS from_bought_id,
            MAX(id) AS to_bought_id,
            COALESCE(SUM(price), 0) AS total_amount
         FROM manuals_bought
         WHERE status = 'successful'
           AND manual_id = $manualId
           AND id IN ($boughtIdsCsv)"
    );
    if (!$summaryRs || mysqli_num_rows($summaryRs) < 1) {
        throw new RuntimeException('Failed to summarize bulk export audit purchases: ' . mysqli_error($conn));
    }

    $summaryRow = mysqli_fetch_assoc($summaryRs) ?: [];
    $fromBoughtId = isset($summaryRow['from_bought_id']) ? (int)$summaryRow['from_bought_id'] : 0;
    $toBoughtId = isset($summaryRow['to_bought_id']) ? (int)$summaryRow['to_bought_id'] : 0;
    $exportTotalAmount = isset($summaryRow['total_amount']) ? (int)$summaryRow['total_amount'] : 0;
    $readyStudentsCount = count($boughtIds);
    $totalStudentsCount = max($readyStudentsCount, (int)($batchRow['student_count'] ?? 0));
    $lastStudentId = 0;

    if ($toBoughtId > 0) {
        $lastStudentRs = mysqli_query($conn, "SELECT buyer FROM manuals_bought WHERE id = $toBoughtId LIMIT 1");
        if ($lastStudentRs && mysqli_num_rows($lastStudentRs) > 0) {
            $lastStudentRow = mysqli_fetch_assoc($lastStudentRs) ?: [];
            $lastStudentId = (int)($lastStudentRow['buyer'] ?? 0);
        }
    }

    $verificationCode = receiptGenerateManualExportCode($conn);
    $safeCode = mysqli_real_escape_string($conn, $verificationCode);
    $downloadedAt = date('Y-m-d H:i:s');
    $safeDownloadedAt = mysqli_real_escape_string($conn, $downloadedAt);
    $boughtIdsJson = json_encode(array_values($boughtIds), JSON_UNESCAPED_SLASHES);
    if ($boughtIdsJson === false) {
        $boughtIdsJson = '[]';
    }
    $safeBoughtIdsJson = mysqli_real_escape_string($conn, $boughtIdsJson);
    $auditStatusColumn = receiptResolveAuditStatusColumn($conn);

    $insertColumns = ['code', 'manual_id', 'hoc_user_id', 'students_count', 'total_amount', 'downloaded_at'];
    $insertValues = [
        "'$safeCode'",
        (string)$manualId,
        (string)$payerUserId,
        (string)$readyStudentsCount,
        (string)$exportTotalAmount,
        "'$safeDownloadedAt'"
    ];

    if (receiptTableHasColumn($conn, 'manual_export_audits', 'last_student_id')) {
        $insertColumns[] = 'last_student_id';
        $insertValues[] = $lastStudentId > 0 ? (string)$lastStudentId : 'NULL';
    }
    if (receiptTableHasColumn($conn, 'manual_export_audits', 'from_bought_id')) {
        $insertColumns[] = 'from_bought_id';
        $insertValues[] = $fromBoughtId > 0 ? (string)$fromBoughtId : 'NULL';
    }
    if (receiptTableHasColumn($conn, 'manual_export_audits', 'to_bought_id')) {
        $insertColumns[] = 'to_bought_id';
        $insertValues[] = $toBoughtId > 0 ? (string)$toBoughtId : 'NULL';
    }
    if (receiptTableHasColumn($conn, 'manual_export_audits', 'bought_ids_json')) {
        $insertColumns[] = 'bought_ids_json';
        $insertValues[] = "'$safeBoughtIdsJson'";
    }
    if ($auditStatusColumn !== '') {
        $insertColumns[] = $auditStatusColumn;
        $insertValues[] = "'pending'";
    }

    $insertColumnSql = implode(', ', array_map(function ($column) {
        return "`$column`";
    }, $insertColumns));
    $insertValueSql = implode(', ', $insertValues);
    $insertSql = "INSERT INTO manual_export_audits ($insertColumnSql) VALUES ($insertValueSql)";
    if (!mysqli_query($conn, $insertSql)) {
        throw new RuntimeException('Failed to create bulk export audit row: ' . mysqli_error($conn));
    }

    $verificationUrl = nivasity_app_url('manual-export-verify.php?code=' . urlencode($verificationCode));
    return [
        'code' => $verificationCode,
        'verification_url' => $verificationUrl,
        'downloaded_at' => $downloadedAt,
        'students_count' => $readyStudentsCount,
        'total_students_count' => $totalStudentsCount,
        'total_amount' => $exportTotalAmount,
    ];
}

function getReceiptDataFromRef($conn, $user_id, $tx_ref, $filterType = null, $filterId = null, array $options = []) {
    // Fetch user details
    $user_q = mysqli_query($conn, "SELECT * FROM users WHERE id = " . (int)$user_id);
    $user = $user_q ? mysqli_fetch_array($user_q) : null;
    $firstName = $user && isset($user['first_name']) ? $user['first_name'] : '';
    $lastName  = $user && isset($user['last_name']) ? $user['last_name'] : '';
    $matricNo = $user && isset($user['matric_no']) ? trim((string)$user['matric_no']) : '';
    $payerName = trim(($firstName ?: '') . ' ' . ($lastName ?: ''));
    if ($payerName === '') { $payerName = 'Customer'; }
    if ($matricNo === '') { $matricNo = 'N/A'; }

    // Resolve total amount: by default from transactions; when filtering a single item, compute from item price only
    $total_amount = 0.0;
    $tx_safe = mysqli_real_escape_string($conn, $tx_ref);
    $filtered = ($filterType !== null && $filterId !== null);
    $shouldCreateBulkExportAudit = !empty($options['create_bulk_export_audit']);
    $exportAudit = null;
    $receiptDate = '';
    $tx_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT amount, created_at FROM transactions WHERE ref_id = '$tx_safe' AND user_id = " . (int)$user_id . " LIMIT 1"));
    if ($tx_row && !empty($tx_row['created_at'])) {
        $receiptDate = (string)$tx_row['created_at'];
    }
    if (!$filtered) {
        if ($tx_row && isset($tx_row['amount'])) {
            $total_amount = (float)$tx_row['amount'];
        }
    }
    $receiptDateFormatted = date('jS F, Y', ($receiptDate && strtotime($receiptDate) !== false) ? strtotime($receiptDate) : time());

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
                    'name' => trim((string) ($row['title'] ?? '') . (isset($row['course_code']) && $row['course_code'] !== '' ? ' (' . (string) $row['course_code'] . ')' : '')),
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
                        $metaBits[] = 'School: ' . (string) $school_name;
                    }
                } elseif (isset($event['event_type']) && $event['event_type'] == 'online') {
                    if (!empty($event['event_link'])) {
                        $metaBits[] = 'Link: ' . (string) $event['event_link'];
                    }
                } else {
                    if (!empty($event['location'])) {
                        $metaBits[] = 'Location: ' . (string) $event['location'];
                    }
                }
                $items[] = [
                    'name' => (string) ($event['title'] ?? ''),
                    'type' => 'Event',
                    'price' => isset($event['price']) ? (float)$event['price'] : 0,
                    'meta' => implode(' &bull; ', $metaBits)
                ];
            }
        }
    }

    // If transaction row not found, compute a fallback total using settlement helper
    if (!$filtered) {
        $bulkTablesReady = false;
        static $receiptTableCache = [];
        if (!array_key_exists('manual_bulk_payment_batches', $receiptTableCache)) {
            $batchTableRs = mysqli_query($conn, "SHOW TABLES LIKE 'manual_bulk_payment_batches'");
            $studentTableRs = mysqli_query($conn, "SHOW TABLES LIKE 'manual_bulk_payment_students'");
            $receiptTableCache['manual_bulk_payment_batches'] = $batchTableRs && mysqli_num_rows($batchTableRs) > 0;
            $receiptTableCache['manual_bulk_payment_students'] = $studentTableRs && mysqli_num_rows($studentTableRs) > 0;
        }
        $bulkTablesReady = !empty($receiptTableCache['manual_bulk_payment_batches']) && !empty($receiptTableCache['manual_bulk_payment_students']);

        if ($bulkTablesReady) {
            $batchRs = mysqli_query(
                $conn,
                "SELECT
                    b.id,
                    b.manual_id,
                    b.student_count,
                    b.subtotal,
                    b.fee_amount,
                    b.total_amount,
                    COALESCE(b.paid_at, b.created_at) AS paid_at,
                    m.title,
                    m.course_code
                 FROM manual_bulk_payment_batches AS b
                 INNER JOIN manuals AS m ON m.id = b.manual_id
                 WHERE b.ref_id = '$tx_safe'
                   AND b.payer_user_id = " . (int) $user_id . "
                 LIMIT 1"
            );

            if ($batchRs && mysqli_num_rows($batchRs) > 0) {
                $batchRow = mysqli_fetch_assoc($batchRs) ?: [];
                $batchId = (int) ($batchRow['id'] ?? 0);
                $studentCount = max(1, (int) ($batchRow['student_count'] ?? 1));
                $unitPrice = (int) round(((float) ($batchRow['subtotal'] ?? 0)) / $studentCount);
                $bulkItems = [];

                if ($receiptDate === '' && !empty($batchRow['paid_at'])) {
                    $receiptDateFormatted = date('jS F, Y', strtotime((string) $batchRow['paid_at']));
                }
                if ($total_amount <= 0 && isset($batchRow['total_amount'])) {
                    $total_amount = (float) $batchRow['total_amount'];
                }

                if ($batchId > 0) {
                    $studentsRs = mysqli_query(
                        $conn,
                        "SELECT first_name, last_name, raw_matric_no, normalized_matric_no
                         FROM manual_bulk_payment_students
                         WHERE batch_id = {$batchId}
                         ORDER BY id ASC"
                    );
                    if ($studentsRs) {
                        while ($studentRow = mysqli_fetch_assoc($studentsRs)) {
                            $studentName = trim((string) ($studentRow['first_name'] ?? '') . ' ' . (string) ($studentRow['last_name'] ?? ''));
                            if ($studentName === '') {
                                $studentName = 'Pending student';
                            }
                            $studentMatric = trim((string) ($studentRow['raw_matric_no'] ?? ''));
                            if ($studentMatric === '') {
                                $studentMatric = trim((string) ($studentRow['normalized_matric_no'] ?? ''));
                            }

                            $bulkItems[] = [
                                'name' => trim((string) ($batchRow['title'] ?? '') . (!empty($batchRow['course_code']) ? ' (' . (string) $batchRow['course_code'] . ')' : '')),
                                'type' => 'Bulk Payment',
                                'price' => (float) $unitPrice,
                                'meta' => 'Paid for: ' . $studentName . ($studentMatric !== '' ? ' (Matric No.: ' . $studentMatric . ')' : ''),
                            ];
                        }
                    }
                }

                if ($shouldCreateBulkExportAudit) {
                    $exportAudit = createBulkReceiptExportAudit($conn, (int)$user_id, (string)$tx_ref);
                }

                if (!empty($batchRow['fee_amount']) && (float) $batchRow['fee_amount'] > 0) {
                    $bulkItems[] = [
                        'name' => 'Bulk payment fee',
                        'type' => 'Charge',
                        'price' => (float) $batchRow['fee_amount'],
                        'meta' => 'Handling fee applied to this bulk payment',
                    ];
                }

                if (!empty($bulkItems)) {
                    $items = $bulkItems;
                }
            }
        }
    }

    if ($filtered) {
        // For single-item receipts, total equals the sum of selected items only (no shared fees)
        $base = 0.0;
        foreach ($items as $it) { $base += (float)$it['price']; }
        $total_amount = $base;
    } elseif ($total_amount <= 0) {
        $base = 0.0;
        foreach ($items as $it) { $base += (float)$it['price']; }
        // Use active gateway pricing; fallback to Flutterwave calculation only if needed
        if (function_exists('calculateGatewayCharges')) {
            $calc = calculateGatewayCharges($base);
            $total_amount = $calc['total_amount'] ?? $base;
        } elseif (function_exists('calculateFlutterwaveSettlement')) {
            $calc = calculateFlutterwaveSettlement($base);
            $total_amount = $calc['total_amount'];
        } else {
            $total_amount = $base; // last resort
        }
    }

    return [
        'payer_name' => $payerName,
        'matric_no' => $matricNo,
        'reference' => $tx_ref,
        'receipt_date' => $receiptDateFormatted,
        'total_amount' => (float) $total_amount,
        'export_audit' => $exportAudit,
        'items' => $items,
    ];
}

function buildReceiptHtmlFromData(array $receiptData) {
    // Build receipt HTML (same visual style as original)
    $currency = '&#8358;';
    $exportAudit = isset($receiptData['export_audit']) && is_array($receiptData['export_audit']) ? $receiptData['export_audit'] : [];
    $exportCode = trim((string)($exportAudit['code'] ?? ''));
    $verificationUrl = trim((string)($exportAudit['verification_url'] ?? ''));
    $exportStudentsCount = (int)($exportAudit['students_count'] ?? 0);
    $exportTotalStudentsCount = (int)($exportAudit['total_students_count'] ?? 0);
    $message = '';
    $message .= '<h2 style="margin:0;color:#7a3b73">Payment Receipt</h2>';
    if ($exportCode !== '') {
        $message .= '<div style="background:#fff6db;border:1px solid #f2d58a;border-radius:6px;padding:12px;margin:12px 0 16px">'
                  . '<div style="margin:0 0 6px"><strong>Export Code:</strong> ' . htmlspecialchars($exportCode) . '</div>';
        if ($verificationUrl !== '') {
            $message .= '<div style="margin:0 0 6px"><strong>Verify Link:</strong> <a href="' . htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#7a3b73;word-break:break-all">' . htmlspecialchars($verificationUrl) . '</a></div>';
        }
        if ($exportStudentsCount > 0) {
            $message .= '<div style="margin:0;color:#6b5b2a;font-size:13px"><strong>Students In Export:</strong> ' . number_format($exportStudentsCount);
            if ($exportTotalStudentsCount > $exportStudentsCount) {
                $message .= ' of ' . number_format($exportTotalStudentsCount) . ' currently ready for grant';
            }
            $message .= '</div>';
        }
        $message .= '</div>';
    }
    $message .= '<p style="margin:6px 0 18px">Thank you for your purchase!</p>';

    $message .= '<div style="background:#f9f4ff;border:1px solid #e8d7f0;border-radius:6px;padding:12px;margin-bottom:16px">'
              . '<div style="margin:4px 0"><strong>Payer Name:</strong> ' . htmlspecialchars((string) $receiptData['payer_name']) . '</div>'
              . '<div style="margin:4px 0"><strong>Matric No.:</strong> ' . htmlspecialchars((string) $receiptData['matric_no']) . '</div>'
              . '<div style="margin:4px 0"><strong>Reference:</strong> #' . htmlspecialchars((string) $receiptData['reference']) . '</div>'
              . '<div style="margin:4px 0"><strong>Date:</strong> ' . htmlspecialchars((string) $receiptData['receipt_date']) . '</div>'
              . '<div style="margin:4px 0"><strong>Total Amount:</strong> ' . $currency . ' ' . number_format((float)$receiptData['total_amount'], 2) . '</div>'
              . '</div>';

    if (!empty($receiptData['items'])) {
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
        foreach ($receiptData['items'] as $it) {
            $nameCell = htmlspecialchars((string) $it['name']);
            if (!empty($it['meta'])) {
                $nameCell .= '<div style="color:#777;font-size:13px;margin-top:2px">' . htmlspecialchars((string) $it['meta']) . '</div>';
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

function buildReceiptHtmlFromRef($conn, $user_id, $tx_ref, $filterType = null, $filterId = null) {
    $receiptData = getReceiptDataFromRef($conn, $user_id, $tx_ref, $filterType, $filterId);
    return buildReceiptHtmlFromData($receiptData);
}

function sendCongratulatoryEmail($conn, $user_id, $tx_ref, $cart_, $cart_2, $total_amount) {
    // Build the receipt using persisted data to keep format consistent
    $message = buildReceiptHtmlFromRef($conn, $user_id, $tx_ref);

    // Resolve recipient email
    $user_q = mysqli_query($conn, "SELECT email FROM users WHERE id = " . (int)$user_id);
    $to = ($user_q && mysqli_num_rows($user_q) > 0) ? mysqli_fetch_array($user_q)['email'] : '';
    if (!$to) { return; }

    $subject = "Payment Receipt - Thank You for Your Purchase";
    $mailStatus = sendBrevoMail($subject, $message, $to);
    if ($mailStatus !== "success") {
        $mailStatus = sendMail($subject, $message, $to);
    }

    if ($mailStatus !== "success") {
        error_log("Payment receipt email failed for user_id={$user_id}, tx_ref={$tx_ref}, email={$to}");
    }
}

/**
 * Calculate Flutterwave charge, total and profit given a base amount.
 * Mirrors logic used in handle-fw-payment.php to keep results consistent.
 * Updated to use 2.15% percentage fee
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
        // Percentage + tiered additions (2.15% instead of 2%)
        $charge += ($baseAmount * 0.0215);
        if ($baseAmount >= 2500 && $baseAmount < 5000) {
            $charge += 20.0;
        } elseif ($baseAmount >= 5000 && $baseAmount < 10000) {
            $charge += 30.0;
        } else {
            $charge += 50.0;
        }
    }

    $total = $baseAmount + $charge;
    // Round to whole numbers for consistency
    $charge = round($charge);
    $total = round($total);
    $flutterwave_fee = round($total * 0.0215);
    $base_profit = max($charge - $flutterwave_fee, 0);
    $profit_bonus = 0;
    if ($baseAmount >= 50000) {
        $profit_bonus = 50;
    } elseif ($baseAmount >= 20000) {
        $profit_bonus = 20;
    }
    $profit = round($base_profit + $profit_bonus);

    return [
        'total_amount' => $total,
        'charge' => $charge,
        'profit' => $profit,
        'flutterwave_fee' => $flutterwave_fee,
    ];
}

/**
 * Calculate charges using the active payment gateway
 * This function uses the gateway abstraction to apply the correct pricing logic
 */
function calculateGatewayCharges($baseAmount, $gatewayName = null) {
    require_once __DIR__ . '/PaymentGatewayFactory.php';
    
    try {
        if ($gatewayName === null) {
            $gateway = PaymentGatewayFactory::getActiveGateway();
        } else {
            $gateway = PaymentGatewayFactory::getGateway($gatewayName);
        }
        
        $result = $gateway->calculateCharges($baseAmount);
        // Normalize rounding to whole numbers for consistency
        $result['charge'] = round($result['charge'] ?? 0);
        $result['total_amount'] = round($result['total_amount'] ?? ($baseAmount + ($result['charge'] ?? 0)));
        $result['profit'] = round($result['profit'] ?? max(($result['charge'] ?? 0) - ($result['gateway_fee'] ?? 0), 0));
        if (isset($result['gateway_fee'])) {
            $result['gateway_fee'] = round($result['gateway_fee']);
        }
        if (isset($result['flutterwave_fee'])) {
            $result['flutterwave_fee'] = round($result['flutterwave_fee']);
        }
        return $result;
    } catch (Exception $e) {
        // Fallback to Flutterwave calculation if gateway factory fails
        return calculateFlutterwaveSettlement($baseAmount);
    }
}

/**
 * Get settlement account subaccount code for the active gateway
 * 
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param int $schoolId School ID (optional, for school-level accounts)
 * @param string $gatewayName Gateway name (optional, defaults to active gateway)
 * @return string|null Subaccount code or null if not found
 */
function getSettlementSubaccount($conn, $userId, $schoolId = null, $gatewayName = null) {
    require_once __DIR__ . '/PaymentGatewayFactory.php';
    
    if ($gatewayName === null) {
        $gatewayName = PaymentGatewayFactory::getActiveGatewayName();
    }
    
    $gatewayName = mysqli_real_escape_string($conn, $gatewayName);
    
    // Try school account first if school_id is provided
    if ($schoolId !== null) {
        $schoolId = (int)$schoolId;
        $query = "SELECT subaccount_code FROM settlement_accounts 
                  WHERE school_id = $schoolId AND type = 'school' AND gateway = '$gatewayName' 
                  ORDER BY id DESC LIMIT 1";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            return mysqli_fetch_array($result)['subaccount_code'];
        }
    }
    
    // Fallback to user account
    $userId = (int)$userId;
    $query = "SELECT subaccount_code FROM settlement_accounts 
              WHERE user_id = $userId AND gateway = '$gatewayName' 
              ORDER BY id DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_array($result)['subaccount_code'];
    }
    
    return null;
}

?>
