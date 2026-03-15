<?php

require_once __DIR__ . '/payment_manifest.php';

if (!function_exists('paymentVerifierDeliveryState')) {
    function paymentVerifierDeliveryState($conn, $txRef, $userId, array $items) {
        $txRefSafe = mysqli_real_escape_string($conn, (string) $txRef);
        $userId = (int) $userId;
        $missingItems = [];
        $manualIds = [];
        $eventIds = [];

        foreach ($items as $item) {
            $itemId = (int) $item['item_id'];
            $type = $item['type'];
            if ($type === 'manual') {
                $manualIds[] = $itemId;
                $exists = mysqli_query(
                    $conn,
                    "SELECT 1 FROM manuals_bought
                     WHERE ref_id = '$txRefSafe' AND manual_id = $itemId AND buyer = $userId
                     LIMIT 1"
                );
                if (!$exists || mysqli_num_rows($exists) < 1) {
                    $missingItems[] = $item;
                }
            } elseif ($type === 'event') {
                $eventIds[] = $itemId;
                $exists = mysqli_query(
                    $conn,
                    "SELECT 1 FROM event_tickets
                     WHERE ref_id = '$txRefSafe' AND event_id = $itemId AND buyer = $userId
                     LIMIT 1"
                );
                if (!$exists || mysqli_num_rows($exists) < 1) {
                    $missingItems[] = $item;
                }
            }
        }

        return [
            'missing_items' => $missingItems,
            'manual_ids' => array_values(array_unique($manualIds)),
            'event_ids' => array_values(array_unique($eventIds)),
        ];
    }
}

if (!function_exists('paymentVerifierClearSessionCart')) {
    function paymentVerifierClearSessionCart($userId) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION["nivas_cart$userId"] = [];
        $_SESSION["nivas_cart_event$userId"] = [];
    }
}

if (!function_exists('paymentVerifierUpsertTransactionFromManifest')) {
    function paymentVerifierUpsertTransactionFromManifest($conn, $txRef, $userId, $gatewayName, array $manifest, $refundApplied = null, $createdAt = null) {
        $txRefSafe = mysqli_real_escape_string($conn, (string) $txRef);
        $userId = (int) $userId;
        $gatewayUpper = strtoupper((string) $gatewayName);
        $subtotal = (float) ($manifest['subtotal'] ?? 0);
        $totalAmount = (float) ($manifest['total_amount'] ?? 0);
        $charge = (float) ($manifest['charge'] ?? 0);
        $calc = calculateGatewayCharges($subtotal, $gatewayName);
        $profit = (float) ($manifest['profit'] ?? ($calc['profit'] ?? max($charge, 0)));
        $refund = ($refundApplied === null) ? 0 : (int) $refundApplied;
        $createdAt = $createdAt ? (string) $createdAt : date('Y-m-d H:i:s');
        $createdAtSafe = mysqli_real_escape_string($conn, $createdAt);

        $existingTx = mysqli_query($conn, "SELECT id FROM transactions WHERE ref_id = '$txRefSafe' ORDER BY id DESC LIMIT 1");
        if ($existingTx && mysqli_num_rows($existingTx) > 0) {
            $updateTxSql = "UPDATE transactions
                            SET user_id = $userId,
                                amount = $totalAmount,
                                charge = $charge,
                                profit = $profit,
                                refund = $refund,
                                status = 'successful',
                                medium = '$gatewayUpper'
                            WHERE ref_id = '$txRefSafe'";
            if (!mysqli_query($conn, $updateTxSql)) {
                throw new Exception('Failed to update transaction record: ' . mysqli_error($conn));
            }
        } else {
            $insertTxSql = "INSERT INTO transactions (user_id, ref_id, amount, charge, profit, refund, status, medium, created_at)
                            VALUES ($userId, '$txRefSafe', $totalAmount, $charge, $profit, $refund, 'successful', '$gatewayUpper', '$createdAtSafe')";
            if (!mysqli_query($conn, $insertTxSql)) {
                throw new Exception('Failed to create transaction record: ' . mysqli_error($conn));
            }
        }

        return [
            'amount' => (float) $totalAmount,
            'charge' => (float) $charge,
            'profit' => (float) $profit,
            'refund' => (int) $refund,
            'created_at' => $createdAt,
        ];
    }
}

if (!function_exists('paymentVerifyAndFulfill')) {
    function paymentVerifyAndFulfill($conn, $txRef, $userId, $schoolId, $gatewayName, array $gatewayData = [], array $options = []) {
        $txRef = trim((string) $txRef);
        $txRefSafe = mysqli_real_escape_string($conn, $txRef);
        $userId = (int) $userId;
        $schoolId = (int) $schoolId;
        $gatewayName = paymentManifestNormalizeGateway($gatewayName);
        $gatewayUpper = strtoupper($gatewayName);
        $sendEmail = array_key_exists('send_email', $options) ? (bool) $options['send_email'] : true;
        $sendNotification = array_key_exists('send_notification', $options) ? (bool) $options['send_notification'] : true;
        $clearSession = array_key_exists('clear_session', $options) ? (bool) $options['clear_session'] : true;
        $notifyStatus = $options['notify_status'] ?? 'success';

        $processedQuery = mysqli_query(
            $conn,
            "SELECT id, amount, created_at FROM transactions WHERE ref_id = '$txRefSafe' ORDER BY id DESC LIMIT 1"
        );
        $txExists = $processedQuery && mysqli_num_rows($processedQuery) > 0;
        $existingTransaction = $txExists ? mysqli_fetch_assoc($processedQuery) : null;

        $storedManifest = paymentManifestFetchByRef($conn, $txRef);
        $storedManifestValid = $storedManifest ? paymentManifestIsValid($storedManifest) : false;

        if ($storedManifest && !$storedManifestValid) {
            paymentManifestAuditRepair(
                $conn,
                $txRef,
                $userId,
                $gatewayUpper,
                'stored_manifest_invalid',
                paymentManifestSnapshotCartRows($conn, $txRef, $userId),
                paymentManifestGatewayPayload($storedManifest),
                'blocked_verification'
            );

            return [
                'status' => 'error',
                'message' => 'Stored payment manifest failed validation',
            ];
        }

        $gatewayManifest = paymentManifestExtractGatewayPayload($gatewayData);
        if ($gatewayManifest !== null) {
            if (!$storedManifest || !$storedManifestValid || !paymentManifestGatewayMatchesStored($gatewayManifest, $storedManifest)) {
                paymentManifestAuditRepair(
                    $conn,
                    $txRef,
                    $userId,
                    $gatewayUpper,
                    'gateway_manifest_mismatch',
                    paymentManifestSnapshotCartRows($conn, $txRef, $userId),
                    $gatewayManifest,
                    'blocked_verification'
                );

                return [
                    'status' => 'error',
                    'message' => 'Gateway payment manifest did not match the stored payment manifest',
                ];
            }
        }

        $effectiveManifest = null;
        if ($storedManifest && $storedManifestValid) {
            $effectiveManifest = $storedManifest;

            $cartSnapshot = paymentManifestSnapshotCartRows($conn, $txRef, $userId);
            if (!paymentManifestCartMatchesItems($cartSnapshot, $storedManifest['items'])) {
                $repaired = paymentManifestRepairCartRows($conn, $txRef, $userId, $gatewayUpper, $storedManifest['items']);
                paymentManifestAuditRepair(
                    $conn,
                    $txRef,
                    $userId,
                    $gatewayUpper,
                    'cart_manifest_mismatch',
                    $cartSnapshot,
                    paymentManifestGatewayPayload($storedManifest),
                    $repaired ? 'cart_repaired_from_manifest' : 'blocked_cart_repair_failed'
                );

                if (!$repaired) {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to repair cart from payment manifest',
                    ];
                }
            }
        } else {
            $cartSnapshot = paymentManifestSnapshotCartRows($conn, $txRef, $userId);
            if (empty($cartSnapshot)) {
                return [
                    'status' => 'error',
                    'message' => 'Cart data not found for transaction reference',
                ];
            }

            $legacyManifestResult = paymentManifestBuildFromRequestedItems(
                $conn,
                $userId,
                $schoolId,
                $gatewayName,
                $cartSnapshot,
                $txRef
            );
            if (!$legacyManifestResult['status']) {
                return [
                    'status' => 'error',
                    'message' => $legacyManifestResult['message'] ?? 'Unable to rebuild cart data for verification',
                ];
            }

            $effectiveManifest = $legacyManifestResult['manifest'];
        }

        $items = paymentManifestCanonicalizeItems($effectiveManifest['items'] ?? []);
        if (empty($items)) {
            return [
                'status' => 'error',
                'message' => 'No purchased items were found for this payment reference',
            ];
        }

        $deliveryState = paymentVerifierDeliveryState($conn, $txRef, $userId, $items);
        if (count($deliveryState['missing_items']) === 0) {
            $date = $existingTransaction && !empty($existingTransaction['created_at']) ? (string) $existingTransaction['created_at'] : date('Y-m-d H:i:s');

            mysqli_begin_transaction($conn);
            try {
                paymentVerifierUpsertTransactionFromManifest(
                    $conn,
                    $txRef,
                    $userId,
                    $gatewayUpper,
                    $effectiveManifest,
                    $existingTransaction['refund'] ?? 0,
                    $date
                );
                mysqli_commit($conn);
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                return [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }

            mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$txRefSafe'");
            $refundApplied = (int) consumeReservationsForSettledTx($conn, $txRef);
            if ($clearSession) {
                paymentVerifierClearSessionCart($userId);
            }

            return [
                'status' => 'success',
                'message' => 'Already processed',
                'already_processed' => true,
                'amount' => (float) ($effectiveManifest['total_amount'] ?? 0),
                'processed_at' => $date,
                'refund_applied' => $refundApplied,
            ];
        }

        $itemsProcessed = 0;
        foreach ($deliveryState['missing_items'] as $item) {
            $itemId = (int) $item['item_id'];
            $price = (float) $item['price'];
            $sellerId = (int) $item['seller_id'];

            if ($item['type'] === 'manual') {
                $insertSql = "INSERT INTO manuals_bought (manual_id, price, buyer, seller, ref_id, status, school_id, created_at)
                              VALUES ($itemId, $price, $userId, $sellerId, '$txRefSafe', 'successful', $schoolId, NOW())";
                if (!mysqli_query($conn, $insertSql)) {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to deliver purchased material',
                    ];
                }
            } elseif ($item['type'] === 'event') {
                $insertSql = "INSERT INTO event_tickets (event_id, price, buyer, seller, ref_id, status, created_at)
                              VALUES ($itemId, $price, $userId, $sellerId, '$txRefSafe', 'successful', NOW())";
                if (!mysqli_query($conn, $insertSql)) {
                    return [
                        'status' => 'error',
                        'message' => 'Failed to deliver purchased event ticket',
                    ];
                }
            }

            $itemsProcessed++;
        }

        if ($itemsProcessed < 1) {
            return [
                'status' => 'error',
                'message' => 'No cart items were fulfilled; transaction not recorded',
            ];
        }

        $totalAmount = (float) ($effectiveManifest['total_amount'] ?? 0);
        $date = date('Y-m-d H:i:s');
        $refundApplied = 0;

        mysqli_begin_transaction($conn);
        try {
            $refundApplied = consumeReservationsCore($conn, $txRef);
            paymentVerifierUpsertTransactionFromManifest($conn, $txRef, $userId, $gatewayUpper, $effectiveManifest, $refundApplied, $date);
            mysqli_commit($conn);
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        mysqli_query($conn, "UPDATE cart SET status = 'confirmed' WHERE ref_id = '$txRefSafe'");

        if ($sendEmail && function_exists('sendCongratulatoryEmail')) {
            sendCongratulatoryEmail(
                $conn,
                $userId,
                $txRef,
                $deliveryState['manual_ids'],
                $deliveryState['event_ids'],
                $totalAmount
            );
        }

        if ($sendNotification && function_exists('notifyUser')) {
            notifyUser(
                $conn,
                $userId,
                'Payment Successful',
                "Your payment of ₦" . number_format((float) $totalAmount, 2) . " has been confirmed.",
                'payment',
                [
                    'action' => 'order_receipt',
                    'tx_ref' => $txRef,
                    'amount' => (float) $totalAmount,
                    'status' => $notifyStatus,
                ]
            );
        }

        if ($clearSession) {
            paymentVerifierClearSessionCart($userId);
        }

        return [
            'status' => 'success',
            'message' => 'Payment confirmed and items delivered',
            'already_processed' => false,
            'amount' => (float) $totalAmount,
            'processed_at' => $date,
            'refund_applied' => (int) $refundApplied,
        ];
    }
}
